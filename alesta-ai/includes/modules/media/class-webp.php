<?php
/**
 * Alesta AI Free — WebP converter module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~340 lignes).
 * Convertit JPEG/PNG → WebP via GD/Imagick + rewrite rules .htaccess.
 *
 * @package AlestaAI\Modules\Media
 * @since   2.0.0
 */
namespace AlestaAI\Modules\Media;
use AlestaAI\Core\ExtensionsAPI;
defined( 'ABSPATH' ) || exit;

final class Webp {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		// TODO Phase S3 : add_filter('wp_handle_upload', ...) pour convertir à l'upload
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai', 'WebP — Conversion images', 'WebP', 'manage_alesta_ai',
			'alesta-ai-webp', [ $this, 'render_admin_page' ],
		);
	}

	public function render_admin_page(): void {
		echo '<div class="wrap"><h1>WebP — Conversion automatique des images</h1>';
		echo '<div class="notice notice-warning"><p><strong>Module en cours de migration (S3 Phase 2).</strong></p></div>';
		ExtensionsAPI::render_module_actions( 'media-webp', [ 'page' => 'alesta-ai-webp' ] );
		echo '</div>';
	}
}
