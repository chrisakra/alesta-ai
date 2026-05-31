<?php
/**
 * Alesta AI Pro — License Manager
 *
 * Vérifie la validité de la license Alesta AI Pro auprès du serveur Galiance
 * (https://app.galiance.fr/api/license/verify) toutes les 12h et cache le
 * résultat localement.
 *
 * Sources de la license key (par priorité) :
 *  1. Constante PHP wp-config.php : ALESTA_AI_PRO_LICENSE_KEY (recommandée
 *     pour les sites Galiance — injection auto par Galiance Cockpit)
 *  2. Option WordPress : alesta_ai_pro_license_key (saisie manuelle par l'user
 *     dans la page admin "Alesta AI > Licence")
 *
 * Si la verify échoue (réseau down, etc.), on tolère un offline grace period
 * de 7 jours : le plugin reste fonctionnel le temps que le réseau revienne.
 * Au-delà, on désactive les features Pro (le Free continue à marcher).
 *
 * @package AlestaAIPro\License
 * @since   2.0.0
 */

namespace AlestaAIPro\License;

defined( 'ABSPATH' ) || exit;

final class LicenseManager {

	private const VERIFY_ENDPOINT = 'https://app.galiance.fr/api/license/verify';

	/** Cache transient — refresh toutes les 6h (le serveur peut être down un peu) */
	private const CACHE_KEY     = 'alesta_ai_pro_license_state';
	private const CACHE_TTL_SEC = 6 * HOUR_IN_SECONDS;

	/** Backup du dernier état valide pour offline grace period */
	private const LAST_VALID_OPT  = 'alesta_ai_pro_license_last_valid';
	private const OFFLINE_GRACE_SEC = 7 * DAY_IN_SECONDS;

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		// Cron quotidien pour refresh proactif (avant que le cache TTL expire)
		add_action( 'alesta_ai_pro_license_refresh', [ $this, 'refresh' ] );
		if ( ! wp_next_scheduled( 'alesta_ai_pro_license_refresh' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'alesta_ai_pro_license_refresh' );
		}
	}

	// =========================================================================
	// API PUBLIQUE
	// =========================================================================

	/**
	 * True si la license est valide et le plan permet d'utiliser le Pro.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		$state = $this->get_state();
		return ! empty( $state['valid'] );
	}

	/**
	 * Récupère le plan actif (ex. "AAP_SOLO", "AAP_AGENCY", "AAP_UNLIMITED")
	 * ou null si pas de license valide.
	 *
	 * @return string|null
	 */
	public function get_plan(): ?string {
		$state = $this->get_state();
		return $state['plan'] ?? null;
	}

	/**
	 * Date d'expiration en timestamp Unix, ou null.
	 *
	 * @return int|null
	 */
	public function get_expires_at(): ?int {
		$state = $this->get_state();
		if ( empty( $state['expires_at'] ) ) {
			return null;
		}
		return strtotime( $state['expires_at'] );
	}

	/**
	 * Détails complets de l'état (pour page admin license).
	 *
	 * @return array
	 */
	public function get_state(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		return $this->refresh();
	}

	/**
	 * Récupère la license key depuis sa source.
	 *
	 * @return string|null
	 */
	public function get_key(): ?string {
		// Priorité 1 : constante wp-config (injectée par Galiance Cockpit)
		if ( defined( 'ALESTA_AI_PRO_LICENSE_KEY' ) && is_string( ALESTA_AI_PRO_LICENSE_KEY ) ) {
			return ALESTA_AI_PRO_LICENSE_KEY;
		}
		// Priorité 2 : option WP (saisie utilisateur)
		$opt = get_option( 'alesta_ai_pro_license_key', '' );
		return is_string( $opt ) && $opt ? $opt : null;
	}

	/**
	 * Set la license key (depuis le formulaire admin).
	 * Force un refresh immédiat.
	 *
	 * @param string $key
	 * @return array Nouvel état
	 */
	public function set_key( string $key ): array {
		update_option( 'alesta_ai_pro_license_key', $key, false );
		delete_transient( self::CACHE_KEY );
		return $this->refresh();
	}

	// =========================================================================
	// VERIFY DISTANT
	// =========================================================================

	/**
	 * Appel HTTP au serveur Galiance pour vérifier la license + cache le résultat.
	 *
	 * @return array
	 */
	public function refresh(): array {
		$key = $this->get_key();
		if ( ! $key ) {
			$state = [
				'valid'   => false,
				'reason'  => 'no_key',
				'message' => 'Aucune licence configurée. Achetez-en une sur alesta-ai.com',
			];
			set_transient( self::CACHE_KEY, $state, self::CACHE_TTL_SEC );
			return $state;
		}

		$response = wp_remote_post( self::VERIFY_ENDPOINT, [
			'timeout' => 10,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'license_key'    => $key,
				'domain'         => wp_parse_url( home_url(), PHP_URL_HOST ),
				'site_url'       => home_url(),
				'wp_version'     => get_bloginfo( 'version' ),
				'plugin_version' => defined( 'ALESTA_AI_PRO_VERSION' ) ? ALESTA_AI_PRO_VERSION : '2.0.0',
			] ),
		] );

		// Erreur réseau → offline grace period si on a un état précédent valide
		if ( is_wp_error( $response ) ) {
			return $this->offline_fallback( 'network_error', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 500 || ! is_array( $body ) ) {
			return $this->offline_fallback( 'server_error', "HTTP $code" );
		}

		// Réponse OK (peut être valid:false avec une raison claire)
		$state = [
			'valid'                 => ! empty( $body['valid'] ),
			'plan'                  => $body['plan'] ?? null,
			'expires_at'            => $body['expires_at'] ?? null,
			'activations_remaining' => $body['activations_remaining'] ?? null,
			'reason'                => $body['reason'] ?? null,
			'message'               => $body['message'] ?? null,
			'checked_at'            => current_time( 'mysql', true ),
		];

		set_transient( self::CACHE_KEY, $state, self::CACHE_TTL_SEC );

		// Si valid, on sauvegarde le state pour offline fallback futur
		if ( $state['valid'] ) {
			update_option( self::LAST_VALID_OPT, [
				'state'   => $state,
				'cached_at' => time(),
			], false );
		}

		return $state;
	}

	/**
	 * Si serveur Galiance down : on accepte la dernière license valide pendant 7j.
	 * Au-delà, on désactive le Pro (le Free continue à marcher de toute façon).
	 */
	private function offline_fallback( string $reason, string $detail ): array {
		$last_valid = get_option( self::LAST_VALID_OPT, null );
		if ( is_array( $last_valid ) && isset( $last_valid['cached_at'], $last_valid['state'] ) ) {
			$age = time() - (int) $last_valid['cached_at'];
			if ( $age < self::OFFLINE_GRACE_SEC ) {
				$state = $last_valid['state'];
				$state['offline_mode'] = true;
				$state['offline_age_hours'] = (int) ( $age / 3600 );
				// Cache court (1h) pour réessayer rapidement si le réseau revient
				set_transient( self::CACHE_KEY, $state, HOUR_IN_SECONDS );
				return $state;
			}
		}

		// Pas de dernier état valide OU grace period dépassé
		$state = [
			'valid'   => false,
			'reason'  => $reason,
			'message' => "Impossible de vérifier la licence : $detail",
		];
		set_transient( self::CACHE_KEY, $state, HOUR_IN_SECONDS );
		return $state;
	}
}
