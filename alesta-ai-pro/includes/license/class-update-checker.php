<?php
/**
 * Alesta AI Pro — Update Checker (custom WP plugin updater)
 *
 * Interface WordPress avec un serveur d'updates custom (api.galiance.fr) au
 * lieu de wordpress.org. Pattern standard pour plugins payants hors-wp.org.
 *
 * 3 hooks WordPress utilisés :
 *  - pre_set_site_transient_update_plugins → annonce une nouvelle version dispo
 *  - plugins_api                            → retourne le changelog/screenshots
 *  - upgrader_pre_download                  → injecte la license key dans l'URL
 *
 * Endpoint distant : GET https://app.galiance.fr/api/alesta-ai-pro/latest
 * Réponse :
 *   {
 *     version: "2.0.1",
 *     download_url: "https://app.galiance.fr/api/alesta-ai-pro/download/2.0.1",
 *     wp_tested: "6.7",
 *     wp_required: "6.0",
 *     description_html: "...",
 *     changelog_html: "..."
 *   }
 *
 * @package AlestaAIPro\License
 * @since   2.0.0
 */

namespace AlestaAIPro\License;

defined( 'ABSPATH' ) || exit;

final class UpdateChecker {

	private const RELEASE_ENDPOINT = 'https://app.galiance.fr/api/alesta-ai-pro/latest';
	private const CACHE_KEY        = 'alesta_ai_pro_release_info';
	private const CACHE_TTL_SEC    = 12 * HOUR_IN_SECONDS;

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update_info' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_details' ], 10, 3 );
		add_filter( 'upgrader_pre_download', [ $this, 'inject_license_in_download_url' ], 10, 3 );
	}

	// =========================================================================
	// HOOK 1 — Annonce une mise à jour dispo dans Tableau de bord > Mises à jour
	// =========================================================================

	public function inject_update_info( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->fetch_release_info();
		if ( ! $release || empty( $release['version'] ) ) {
			return $transient;
		}

		$current_version = defined( 'ALESTA_AI_PRO_VERSION' ) ? ALESTA_AI_PRO_VERSION : '0';
		if ( version_compare( $release['version'], $current_version, '<=' ) ) {
			return $transient;
		}

		$basename = plugin_basename( ALESTA_AI_PRO_FILE );

		$transient->response[ $basename ] = (object) [
			'id'           => $basename,
			'slug'         => 'alesta-ai-pro',
			'plugin'       => $basename,
			'new_version'  => $release['version'],
			'url'          => 'https://www.alesta-ai.com/tarifs.html',
			'package'      => $release['download_url'], // license sera injectée par hook 3
			'tested'       => $release['wp_tested'] ?? '',
			'requires'     => $release['wp_required'] ?? '6.0',
			'requires_php' => $release['php_required'] ?? '7.4',
		];

		return $transient;
	}

	// =========================================================================
	// HOOK 2 — "View version X details" popup
	// =========================================================================

	public function plugin_details( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $args->slug !== 'alesta-ai-pro' ) {
			return $result;
		}

		$release = $this->fetch_release_info();
		if ( ! $release ) {
			return $result;
		}

		return (object) [
			'name'          => 'Alesta AI Pro',
			'slug'          => 'alesta-ai-pro',
			'version'       => $release['version'],
			'author'        => '<a href="https://www.alesta-ai.com">Alesta Computer</a>',
			'homepage'      => 'https://www.alesta-ai.com/tarifs.html',
			'requires'      => $release['wp_required'] ?? '6.0',
			'tested'        => $release['wp_tested'] ?? '',
			'requires_php'  => $release['php_required'] ?? '7.4',
			'last_updated'  => $release['released_at'] ?? '',
			'download_link' => $release['download_url'],
			'sections'      => [
				'description' => $release['description_html'] ?? '',
				'changelog'   => $release['changelog_html'] ?? '',
			],
		];
	}

	// =========================================================================
	// HOOK 3 — Injecte la license key dans l'URL au moment du download
	// =========================================================================

	public function inject_license_in_download_url( $reply, $package, $upgrader ) {
		// On n'intervient que si le package vient de notre serveur Galiance
		if ( strpos( $package, 'app.galiance.fr/api/alesta-ai-pro/download/' ) === false ) {
			return $reply;
		}

		$license_key = LicenseManager::instance()->get_key();
		if ( ! $license_key ) {
			// Pas de license → on laisse WP télécharger (le serveur retournera 403)
			return $reply;
		}

		// Ajoute ?license=aap_live_xxx au package URL
		$separator = strpos( $package, '?' ) === false ? '?' : '&';
		$signed_package = $package . $separator . 'license=' . rawurlencode( $license_key );

		// On modifie l'URL avant que download_url() la consomme. Pour cela on
		// retourne le résultat de download_url() nous-même (= $reply non-false).
		$skin = property_exists( $upgrader, 'skin' ) ? $upgrader->skin : null;
		if ( $skin && method_exists( $skin, 'feedback' ) ) {
			$skin->feedback( 'Downloading update package from Galiance (license-authenticated)…' );
		}

		return download_url( $signed_package );
	}

	// =========================================================================
	// FETCH release info (cache 12h)
	// =========================================================================

	private function fetch_release_info(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['version'] ) ) {
			return $cached;
		}

		$response = wp_remote_get( self::RELEASE_ENDPOINT, [
			'timeout' => 10,
			'headers' => [ 'Accept' => 'application/json' ],
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['version'] ) ) {
			return null;
		}

		set_transient( self::CACHE_KEY, $body, self::CACHE_TTL_SEC );
		return $body;
	}
}
