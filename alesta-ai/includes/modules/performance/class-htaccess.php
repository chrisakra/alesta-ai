<?php
/**
 * Alesta AI Free — .htaccess editor (STUB — portage S3 Phase 2)
 *
 * Module de gestion des règles .htaccess :
 *   - Cache navigateur (Expires headers par type MIME)
 *   - GZIP compression
 *   - Sécurité (HTTPS force, hide author archives, no-listing)
 *   - WebP rewrite rules (synergique avec module media/webp)
 *
 * 471 lignes dans le Pro 1.3.22 — à porter intégralement en S3 Phase 2.
 * Le squelette ci-dessous démontre le pattern d'enregistrement registry.
 *
 * Pour porter :
 *   1. Copier le contenu de `Alesta AI Pro version/.../performance/class-htaccess-module.php`
 *   2. Renamespacer toutes les classes en \AlestaAI\Modules\Performance\
 *   3. Remplacer Alesta_AI_Audit_Log par le mécanisme natif WP (ou nouveau Event_Log core)
 *   4. Ajouter ExtensionsAPI::render_module_actions('performance-htaccess', $ctx)
 *      dans le render_admin_page pour permettre l'injection Pro
 *      (ex. Pro peut ajouter un bouton "✨ Suggérer règles via IA")
 *
 * @package AlestaAI\Modules\Performance
 * @since   2.0.0
 */

namespace AlestaAI\Modules\Performance;

use AlestaAI\Core\ExtensionsAPI;

defined( 'ABSPATH' ) || exit;

final class Htaccess {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		// TODO Phase S3 : add_action('admin_init', [ $this, 'maybe_write_htaccess' ]);
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'.htaccess editor',
			'.htaccess',
			'manage_alesta_ai',
			'alesta-ai-htaccess',
			[ $this, 'render_admin_page' ]
		);
	}

	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1>.htaccess editor</h1>
			<div class="notice notice-warning">
				<p><strong>Module en cours de migration (S3 Phase 2).</strong></p>
				<p>Référence : <code>Alesta AI Pro version/.../performance/class-htaccess-module.php</code> (471 lignes).</p>
			</div>

			<?php
			// Hook prêt à recevoir l'injection Pro
			ExtensionsAPI::render_module_actions( 'performance-htaccess', [
				'page' => 'alesta-ai-htaccess',
			] );
			?>
		</div>
		<?php
	}
}
