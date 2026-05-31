<?php
/**
 * Alesta AI Free — Minify HTML/CSS/JS module
 *
 * STUB v2.0 — squelette d'enregistrement + admin page + hook d'injection.
 * Portage métier (~280 lignes du Pro 1.3.22) à faire en S3 Phase 2 :
 *   - Hook wp_loaded pour minifier le HTML output (ob_start)
 *   - Filter style_loader_tag pour combiner les CSS
 *   - Filter script_loader_tag pour minify+combine les JS
 *
 * @package AlestaAI\Modules\Performance
 * @since   2.0.0
 */

namespace AlestaAI\Modules\Performance;

use AlestaAI\Core\ExtensionsAPI;

defined( 'ABSPATH' ) || exit;

final class Minify {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		// TODO Phase S3 : porter minify_html/css/js depuis Pro 1.3.22
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai', 'Minify HTML/CSS/JS', 'Minify', 'manage_alesta_ai',
			'alesta-ai-minify', [ $this, 'render_admin_page' ],
		);
	}

	public function render_admin_page(): void {
		echo '<div class="wrap"><h1>Minify HTML/CSS/JS</h1>';
		echo '<div class="notice notice-warning"><p><strong>Module en cours de migration (S3 Phase 2).</strong></p></div>';
		ExtensionsAPI::render_module_actions( 'performance-minify', [ 'page' => 'alesta-ai-minify' ] );
		echo '</div>';
	}
}
