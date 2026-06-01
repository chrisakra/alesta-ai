<?php
/**
 * Alesta AI Pro — GlitchTip Reporter (mini SDK Sentry-compatible)
 *
 * Envoie les exceptions et erreurs PHP fatales du plugin au serveur
 * GlitchTip self-hosted de Galiance (monitor.galiance.fr).
 *
 * Avantages vs SDK Sentry-PHP officiel :
 *   - 0 dépendance Composer (lib autonome ~150 lignes)
 *   - 0 impact perf (envoi fire-and-forget via wp_remote_post non-bloquant)
 *   - 0 leak vers serveur tiers (uniquement vers monitor.galiance.fr de Galiance)
 *   - Désactivé par défaut (opt-in via constante)
 *
 * ACTIVATION : définir dans wp-config.php du client :
 *
 *   define( 'ALESTA_GLITCHTIP_DSN', 'https://abc@monitor.galiance.fr/2' );
 *
 * Pour les sites hébergés sur Galiance, la constante est injectée
 * automatiquement par le worker de provisioning.
 *
 * DONNÉES ENVOYÉES :
 *   - Stack trace + message d'erreur (PHP)
 *   - Version du plugin, WP, PHP
 *   - Hostname du site (juste home_url host, pas de path/query)
 *   - PAS de données client, PAS de PII, PAS de contenu de posts
 *
 * @package AlestaAIPro\ErrorReporter
 * @since   2.0.3
 */

namespace AlestaAIPro\ErrorReporter;

defined( 'ABSPATH' ) || exit;

final class GlitchTipReporter {

	private static ?self $instance = null;

	private string $host;
	private string $public_key;
	private string $project_id;
	private bool $enabled = false;

	/**
	 * Singleton. Retourne null si DSN non configuré (= reporter désactivé).
	 *
	 * @return self|null
	 */
	public static function instance(): ?self {
		if ( null === self::$instance ) {
			if ( ! defined( 'ALESTA_GLITCHTIP_DSN' ) || ! ALESTA_GLITCHTIP_DSN ) {
				return null;
			}
			self::$instance = new self( ALESTA_GLITCHTIP_DSN );
		}
		return self::$instance;
	}

	private function __construct( string $dsn ) {
		$parsed = wp_parse_url( $dsn );
		if ( ! is_array( $parsed )
			|| empty( $parsed['host'] )
			|| empty( $parsed['user'] )
			|| empty( $parsed['path'] ) ) {
			return;
		}
		$this->host       = $parsed['host'];
		$this->public_key = $parsed['user'];
		$this->project_id = ltrim( $parsed['path'], '/' );
		$this->enabled    = true;
	}

	/**
	 * Hooks WordPress pour catcher les exceptions/fatals automatiquement.
	 * À appeler dans le bootstrap du plugin si DSN configuré.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! $this->enabled ) {
			return;
		}
		// Catch les exceptions non-attrapées
		set_exception_handler( [ $this, 'handle_exception' ] );
		// Catch les fatals au shutdown
		register_shutdown_function( [ $this, 'handle_shutdown' ] );
	}

	/**
	 * Capture explicite d'une exception (à appeler depuis try/catch).
	 *
	 * @param \Throwable $e
	 * @param array      $tags  Tags additionnels (ex: ['module' => 'keywords-ai'])
	 * @param array      $extra Données contextuelles non-PII
	 * @return void
	 */
	public function capture_exception( \Throwable $e, array $tags = [], array $extra = [] ): void {
		if ( ! $this->enabled ) {
			return;
		}
		$this->send( $this->build_event_from_exception( $e, $tags, $extra ) );
	}

	/**
	 * Capture d'un message custom (warning, info).
	 *
	 * @param string $message
	 * @param string $level   'error' | 'warning' | 'info' | 'debug'
	 * @param array  $tags
	 * @param array  $extra
	 * @return void
	 */
	public function capture_message( string $message, string $level = 'info', array $tags = [], array $extra = [] ): void {
		if ( ! $this->enabled ) {
			return;
		}
		$this->send( [
			'event_id'  => $this->uuid4(),
			'timestamp' => time(),
			'level'     => in_array( $level, [ 'error', 'warning', 'info', 'debug', 'fatal' ], true ) ? $level : 'info',
			'platform'  => 'php',
			'logger'    => 'alesta-ai-pro',
			'message'   => $message,
			'tags'      => array_merge( $this->default_tags(), $tags ),
			'extra'     => $extra,
		] );
	}

	// =========================================================================
	// HANDLERS auto WP
	// =========================================================================

	/** @param \Throwable $e */
	public function handle_exception( $e ): void {
		if ( $e instanceof \Throwable ) {
			$this->capture_exception( $e, [ 'origin' => 'set_exception_handler' ] );
		}
	}

	public function handle_shutdown(): void {
		$err = error_get_last();
		if ( ! $err ) {
			return;
		}
		// Seulement les fatals (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR)
		$fatal_types = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
		if ( ! ( $err['type'] & $fatal_types ) ) {
			return;
		}
		// Ne tracker que les erreurs qui viennent de notre plugin (sinon trop bruyant)
		if ( strpos( $err['file'] ?? '', 'alesta-ai-pro' ) === false ) {
			return;
		}
		$this->send( [
			'event_id'  => $this->uuid4(),
			'timestamp' => time(),
			'level'     => 'fatal',
			'platform'  => 'php',
			'logger'    => 'alesta-ai-pro',
			'message'   => $err['message'] ?? 'PHP fatal error',
			'exception' => [
				'values' => [
					[
						'type'  => 'PHPFatalError',
						'value' => $err['message'] ?? '',
						'stacktrace' => [
							'frames' => [
								[
									'filename' => $err['file'] ?? '?',
									'lineno'   => (int) ( $err['line'] ?? 0 ),
									'in_app'   => true,
								],
							],
						],
					],
				],
			],
			'tags' => array_merge( $this->default_tags(), [ 'origin' => 'shutdown' ] ),
		] );
	}

	// =========================================================================
	// INTERNAL
	// =========================================================================

	private function default_tags(): array {
		return [
			'plugin_version' => defined( 'ALESTA_AI_PRO_VERSION' ) ? ALESTA_AI_PRO_VERSION : 'unknown',
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'site'           => wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'unknown',
		];
	}

	private function build_event_from_exception( \Throwable $e, array $tags, array $extra ): array {
		return [
			'event_id'  => $this->uuid4(),
			'timestamp' => time(),
			'level'     => 'error',
			'platform'  => 'php',
			'logger'    => 'alesta-ai-pro',
			'exception' => [
				'values' => [
					[
						'type'       => get_class( $e ),
						'value'      => $e->getMessage(),
						'stacktrace' => [
							'frames' => $this->build_frames( $e->getTrace() ),
						],
					],
				],
			],
			'tags'  => array_merge( $this->default_tags(), $tags ),
			'extra' => $extra,
		];
	}

	private function build_frames( array $trace ): array {
		$frames = [];
		// Inverser pour avoir l'ordre "caller -> callee" (convention Sentry)
		foreach ( array_reverse( $trace ) as $frame ) {
			$frames[] = [
				'filename' => $frame['file'] ?? '?',
				'lineno'   => (int) ( $frame['line'] ?? 0 ),
				'function' => isset( $frame['class'] )
					? $frame['class'] . ( $frame['type'] ?? '::' ) . ( $frame['function'] ?? '?' )
					: ( $frame['function'] ?? '?' ),
				'in_app'   => isset( $frame['file'] ) && strpos( $frame['file'], 'alesta-ai-pro' ) !== false,
			];
		}
		return $frames;
	}

	private function send( array $event ): void {
		$url = sprintf(
			'https://%s/api/%s/store/?sentry_key=%s&sentry_version=7',
			$this->host,
			$this->project_id,
			$this->public_key
		);
		wp_remote_post(
			$url,
			[
				'timeout'  => 3,
				'blocking' => false, // fire-and-forget : on n'attend pas la réponse
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode( $event ),
				'sslverify' => true,
			]
		);
	}

	private function uuid4(): string {
		// UUID v4 sans dashes (32 hex chars) — format event_id Sentry
		try {
			$bytes = random_bytes( 16 );
		} catch ( \Exception $e ) {
			$bytes = openssl_random_pseudo_bytes( 16 );
		}
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		return bin2hex( $bytes );
	}
}
