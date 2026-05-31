<?php
/**
 * Alesta AI Pro — Editorial Calendar AI (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~490 lignes).
 * Calendrier éditorial : génère 5-10 idées d'articles via Claude, planning, gaps SEO.
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Content;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class EditorialModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-editorial'] = [ 'label' => 'Calendrier éditorial', 'category' => 'content', 'icon' => 'calendar-alt',
				'description' => 'Génère idées d\'articles via Claude basés sur ton site + tendances + gaps SEO' ];
			return $f;
		} );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}
	public function register_admin_page(): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		add_submenu_page( 'alesta-ai', 'Calendrier éditorial AI', 'Éditorial', 'manage_alesta_ai',
			'alesta-ai-editorial', fn() => print( '<div class="wrap"><h1>Calendrier éditorial AI</h1><p>Module en cours de migration (S3 Phase 2).</p></div>' ) );
	}
}
