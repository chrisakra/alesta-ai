<?php
/**
 * Alesta AI — Rate Limiter (générique)
 *
 * Compteur dans WordPress transients : N actions par fenêtre temporelle Y.
 * Utilisable pour brute-force login, formulaires anti-spam, appels API, etc.
 *
 * Pattern :
 *   $rl = new RateLimiter( 'login_attempt', 5, 15 * MINUTE_IN_SECONDS );
 *   if ( ! $rl->allow( $user_ip ) ) {
 *     wp_die( 'Trop de tentatives. Réessayez plus tard.' );
 *   }
 *
 * @package AlestaAI\Core
 * @since   2.0.0
 */

namespace AlestaAI\Core;

defined( 'ABSPATH' ) || exit;

final class RateLimiter {

	private string $bucket;
	private int $max;
	private int $window_sec;

	/**
	 * @param string $bucket     ID logique du bucket (ex. "login_attempt", "api_anthropic")
	 * @param int    $max        Nombre max d'actions autorisées par fenêtre
	 * @param int    $window_sec Durée de la fenêtre en secondes
	 */
	public function __construct( string $bucket, int $max, int $window_sec ) {
		$this->bucket     = sanitize_key( $bucket );
		$this->max        = max( 1, $max );
		$this->window_sec = max( 1, $window_sec );
	}

	/**
	 * Incrémente le compteur pour cet identifiant et retourne true si l'action
	 * est autorisée, false si la limite est dépassée.
	 *
	 * @param string $identifier Typiquement IP, user_id ou hash composite
	 * @return bool
	 */
	public function allow( string $identifier ): bool {
		$key   = $this->transient_key( $identifier );
		$state = get_transient( $key );

		if ( ! is_array( $state ) ) {
			set_transient( $key, [ 'count' => 1, 'resets_at' => time() + $this->window_sec ], $this->window_sec );
			return true;
		}

		if ( $state['count'] >= $this->max ) {
			return false;
		}

		$state['count']++;
		set_transient( $key, $state, max( 1, $state['resets_at'] - time() ) );
		return true;
	}

	/**
	 * Combien d'actions restent autorisées pour cet identifiant (sans incrémenter).
	 *
	 * @param string $identifier
	 * @return int
	 */
	public function remaining( string $identifier ): int {
		$state = get_transient( $this->transient_key( $identifier ) );
		if ( ! is_array( $state ) ) {
			return $this->max;
		}
		return max( 0, $this->max - (int) $state['count'] );
	}

	/**
	 * Reset manuellement le compteur (ex. après une auth réussie pour le brute-force).
	 *
	 * @param string $identifier
	 */
	public function reset( string $identifier ): void {
		delete_transient( $this->transient_key( $identifier ) );
	}

	private function transient_key( string $identifier ): string {
		// Hash l'identifier pour ne pas leak des IPs/emails dans wp_options keys
		// (qui peuvent être consultées par d'autres plugins WP).
		return 'alesta_ai_rl_' . $this->bucket . '_' . substr( md5( $identifier ), 0, 16 );
	}
}
