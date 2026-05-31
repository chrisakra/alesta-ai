<?php
/**
 * Alesta AI Pro — Claude Provider (BYOK Anthropic).
 *
 * Implémentation concrète du provider IA Claude qui :
 *  1. Récupère la clé Anthropic du user depuis APIKeyVault (Free)
 *  2. Appelle l'API Anthropic /v1/messages avec retry exponentiel
 *  3. Track la consommation (tokens) en option Wp pour budget monitoring
 *  4. Cache les réponses identiques 1h (réduit coût)
 *
 * Modèles supportés (sync avec Anthropic API) :
 *   - claude-sonnet-4-5 (défaut, équilibre coût/qualité)
 *   - claude-haiku-4-5 (plus rapide/moins cher pour suggestions courtes)
 *   - claude-opus-4-5 (max qualité pour rapports longs)
 *
 * Erreurs courantes :
 *   - 401 : clé invalide → message clair à l'user (vérifier sa clé)
 *   - 429 : rate limit Anthropic → retry avec backoff
 *   - 500/503 : Anthropic down → message d'erreur + fallback message générique
 *
 * @package AlestaAIPro\Providers
 * @since   2.0.1
 */

namespace AlestaAIPro\Providers;

use AlestaAI\Core\APIKeyVault;
use AlestaAI\Core\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class ClaudeProvider {

	private const API_URL  = 'https://api.anthropic.com/v1/messages';
	private const API_VERSION = '2023-06-01';
	private const DEFAULT_MODEL = 'claude-sonnet-4-5';

	/** Cache TTL en secondes — réponses identiques cachées 1h pour réduire coût */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/** Rate limit interne : 30 calls/min/user (sécurité) */
	private RateLimiter $rateLimiter;

	public function __construct() {
		$this->rateLimiter = new RateLimiter( 'claude_provider', 30, MINUTE_IN_SECONDS );
	}

	/**
	 * Envoie un prompt à Claude et retourne la réponse texte.
	 *
	 * @param string $prompt        Prompt utilisateur (système + question, déjà formaté)
	 * @param array  $opts          Options : { model, max_tokens, temperature, system, cache_key }
	 * @return string|WP_Error      Réponse text de Claude, ou WP_Error en cas d'échec
	 */
	public function complete( string $prompt, array $opts = [] ) {
		// 1. Récup la clé Anthropic via Vault (BYOK)
		$api_key = APIKeyVault::get( 'anthropic' );
		if ( ! $api_key ) {
			return new \WP_Error(
				'no_api_key',
				'Aucune clé Anthropic configurée. Allez dans Alesta AI → Réglages pour ajouter votre clé.',
			);
		}

		// 2. Rate limit interne (anti-abuse local)
		$user_id = (string) get_current_user_id() ?: 'guest';
		if ( ! $this->rateLimiter->allow( $user_id ) ) {
			return new \WP_Error(
				'rate_limited',
				'Trop de requêtes IA (max 30/min). Réessayez dans 1 minute.',
			);
		}

		// 3. Cache check (si cache_key fourni)
		if ( ! empty( $opts['cache_key'] ) ) {
			$cached = get_transient( 'alesta_ai_claude_' . md5( $opts['cache_key'] ) );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		// 4. Build request body
		$body = [
			'model'       => $opts['model'] ?? self::DEFAULT_MODEL,
			'max_tokens'  => $opts['max_tokens'] ?? 1024,
			'temperature' => $opts['temperature'] ?? 0.7,
			'messages'    => [
				[ 'role' => 'user', 'content' => $prompt ],
			],
		];
		if ( ! empty( $opts['system'] ) ) {
			$body['system'] = $opts['system'];
		}

		// 5. Call API avec retry exponentiel (3 tentatives sur 429/503)
		$response = $this->call_with_retry( $api_key, $body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// 6. Parse réponse Anthropic
		$text = $this->extract_text( $response );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		// 7. Track usage (tokens input/output) pour budget
		$this->track_usage( $response );

		// 8. Cache si demandé
		if ( ! empty( $opts['cache_key'] ) ) {
			set_transient( 'alesta_ai_claude_' . md5( $opts['cache_key'] ), $text, self::CACHE_TTL );
		}

		return $text;
	}

	/**
	 * Helper : génération JSON structurée (force Claude à retourner du JSON parseable).
	 * Utile pour les modules qui ont besoin de structure (keywords list, FAQ Q/R, etc.).
	 *
	 * @param string $prompt
	 * @param array  $opts
	 * @return array|WP_Error
	 */
	public function complete_json( string $prompt, array $opts = [] ) {
		// Force le format JSON dans le system prompt
		$opts['system'] = ( $opts['system'] ?? '' ) . "\n\nRetourne UNIQUEMENT un JSON valide, sans aucun texte avant ou après. Pas de markdown ```json```.";

		$text = $this->complete( $prompt, $opts );
		if ( is_wp_error( $text ) ) return $text;

		// Strip markdown code fences si Claude en a mis quand même
		$text = preg_replace( '/^```(?:json)?\n?/', '', trim( $text ) );
		$text = preg_replace( '/\n?```$/', '', $text );

		$data = json_decode( $text, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'json_parse_error',
				'Claude a retourné du JSON invalide : ' . json_last_error_msg(),
				[ 'raw' => substr( $text, 0, 200 ) ],
			);
		}
		return $data;
	}

	// =========================================================================
	// INTERNAL
	// =========================================================================

	private function call_with_retry( string $api_key, array $body ) {
		$max_attempts = 3;
		$attempt = 0;
		$last_error = null;

		while ( $attempt < $max_attempts ) {
			$attempt++;
			$response = wp_remote_post( self::API_URL, [
				'timeout' => 30,
				'headers' => [
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
					'Content-Type'      => 'application/json',
				],
				'body' => wp_json_encode( $body ),
			] );

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				// Retry sur erreur réseau
				sleep( 1 << ( $attempt - 1 ) ); // 1, 2, 4 secondes
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$json = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code === 200 ) {
				return $json;
			}

			// Erreurs typées
			if ( $code === 401 ) {
				return new \WP_Error(
					'invalid_api_key',
					'Clé Anthropic invalide ou révoquée. Vérifiez dans Alesta AI → Réglages.',
				);
			}
			if ( $code === 400 ) {
				return new \WP_Error(
					'bad_request',
					'Requête invalide : ' . ( $json['error']['message'] ?? 'erreur inconnue' ),
				);
			}
			if ( $code === 429 || $code === 503 ) {
				// Retry sur rate limit / service unavailable
				$last_error = new \WP_Error(
					"http_$code",
					$json['error']['message'] ?? "HTTP $code",
				);
				sleep( 1 << ( $attempt - 1 ) );
				continue;
			}

			return new \WP_Error(
				"http_$code",
				'Erreur API Anthropic : ' . ( $json['error']['message'] ?? "HTTP $code" ),
			);
		}

		return $last_error ?? new \WP_Error( 'max_retries', 'Échec après 3 tentatives' );
	}

	/**
	 * Extrait le text d'une réponse Anthropic /v1/messages.
	 * Format : { content: [ { type: "text", text: "..." } ], usage: {...} }
	 */
	private function extract_text( array $response ) {
		$content = $response['content'] ?? [];
		if ( ! is_array( $content ) || empty( $content ) ) {
			return new \WP_Error( 'no_content', 'Pas de contenu dans la réponse Anthropic' );
		}
		$text = '';
		foreach ( $content as $block ) {
			if ( ( $block['type'] ?? '' ) === 'text' ) {
				$text .= $block['text'] ?? '';
			}
		}
		return $text ?: new \WP_Error( 'no_text', 'Pas de bloc text dans la réponse' );
	}

	/**
	 * Stocke les tokens consommés dans wp_options pour monitoring budget.
	 * Format : { 'YYYY-MM' => { input: N, output: M } }
	 */
	private function track_usage( array $response ): void {
		$usage = $response['usage'] ?? null;
		if ( ! is_array( $usage ) ) return;

		$month = gmdate( 'Y-m' );
		$key   = 'alesta_ai_claude_usage_' . $month;
		$stats = get_option( $key, [ 'input' => 0, 'output' => 0, 'requests' => 0 ] );
		$stats['input']    += (int) ( $usage['input_tokens'] ?? 0 );
		$stats['output']   += (int) ( $usage['output_tokens'] ?? 0 );
		$stats['requests']++;
		update_option( $key, $stats, false );
	}

	/**
	 * Stats consommation mensuelle pour affichage admin.
	 * Tokens × prix Anthropic = coût estimé pour l'user.
	 */
	public static function get_usage_stats( string $month = null ): array {
		$month = $month ?: gmdate( 'Y-m' );
		$stats = get_option( 'alesta_ai_claude_usage_' . $month, [ 'input' => 0, 'output' => 0, 'requests' => 0 ] );
		// Prix Sonnet 4.5 : $3/M input + $15/M output (Mai 2026)
		$cost_usd = ( $stats['input'] * 3 / 1_000_000 ) + ( $stats['output'] * 15 / 1_000_000 );
		return array_merge( $stats, [ 'cost_usd_estimated' => round( $cost_usd, 4 ) ] );
	}
}
