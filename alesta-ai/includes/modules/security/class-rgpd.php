<?php
/**
 * Alesta AI Free — RGPD banner module
 *
 * Bannière cookies souveraine personnalisable.
 * Migré du Pro 1.3.22 — aucune dépendance IA, 100% statique → FREE pur.
 *
 * @package AlestaAI\Modules\Security
 * @since   2.0.0
 */

namespace AlestaAI\Modules\Security;

use AlestaAI\Core\ExtensionsAPI;

defined( 'ABSPATH' ) || exit;

final class Rgpd {

	public function __construct() {
		$s = $this->get_settings();
		if ( ! empty( $s['enabled'] ) ) {
			add_action( 'wp_footer', [ $this, 'inject_banner' ], 100 );
		}
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	// =========================================================================
	// FRONTEND : injection bannière
	// =========================================================================

	public function inject_banner(): void {
		$s = $this->get_settings();
		if ( empty( $s['enabled'] ) ) return;

		// Note : assets/rgpd-banner.{css,js} doivent être copiés depuis le Pro 1.3.x
		// lors du build du Free 2.0 (cf script package-zips.sh).
		wp_enqueue_style( 'alesta-ai-rgpd', ALESTA_AI_URL . 'assets/rgpd-banner.css', [], ALESTA_AI_VERSION );
		wp_enqueue_script( 'alesta-ai-rgpd', ALESTA_AI_URL . 'assets/rgpd-banner.js', [], ALESTA_AI_VERSION, true );

		wp_localize_script( 'alesta-ai-rgpd', 'AlestaRGPD', [
			'lifetime'       => (int) ( $s['cookie_lifetime'] ?? 365 ),
			'hasAnalytics'   => ! empty( $s['cat_analytics_label'] ),
			'hasMarketing'   => ! empty( $s['cat_marketing_label'] ),
			'hasPreferences' => ! empty( $s['cat_preferences_label'] ),
		] );

		// Le rendu HTML complet est trop long pour ce fichier — on délègue à un template
		require ALESTA_AI_DIR . 'includes/modules/security/templates/rgpd-banner.php';
	}

	// =========================================================================
	// ADMIN
	// =========================================================================

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'RGPD — Bannière cookies',
			'RGPD',
			'manage_alesta_ai',
			'alesta-ai-rgpd',
			[ $this, 'render_admin_page' ]
		);
	}

	public function render_admin_page(): void {
		// Hook d'injection pour le Pro (ex. générer auto la politique cookie via IA)
		ExtensionsAPI::render_module_actions( 'security-rgpd', [ 'page' => 'alesta-ai-rgpd' ] );
		// Placeholder simple — le formulaire complet est à porter depuis le Pro 1.3.x
		echo '<div class="wrap"><h1>RGPD — Bannière cookies</h1>';
		echo '<p>Configuration de la bannière de consentement cookies, conforme RGPD.</p>';
		echo '<p><em>UI complète à finaliser en S3 — phase 2 de la migration.</em></p>';
		echo '</div>';
	}

	// =========================================================================
	// SETTINGS
	// =========================================================================

	public function get_settings(): array {
		$defaults = [
			'enabled'              => false,
			'cookie_lifetime'      => 365,
			'layout'               => 'bar',
			'position'             => 'bottom',
			'color_bg'             => '#1a1a1a',
			'color_text'           => '#ffffff',
			'color_accent'         => '#a78bfa',
			'color_accent_text'    => '#ffffff',
			'color_secondary'      => 'transparent',
			'color_secondary_text' => '#ffffff',
			'color_border'         => 'rgba(255,255,255,.15)',
			'cat_analytics_label'  => '',
			'cat_marketing_label'  => '',
			'cat_preferences_label' => '',
		];
		return wp_parse_args( get_option( 'alesta_ai_rgpd_settings', [] ), $defaults );
	}
}
