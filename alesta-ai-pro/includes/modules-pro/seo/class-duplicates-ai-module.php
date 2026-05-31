<?php
/**
 * Alesta AI Pro — Duplicates AI module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~340 lignes).
 * Détecte contenus dupliqués (Levenshtein + semantic via Claude) + suggestions canonical/merge.
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Seo;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class DuplicatesAIModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['seo-duplicates'] = [ 'label' => 'Détection duplicates SEO', 'category' => 'seo', 'icon' => 'admin-page',
				'description' => 'Détection IA de contenus similaires + suggestions canonical ou merge' ];
			return $f;
		} );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}
	public function register_admin_page(): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		add_submenu_page( 'alesta-ai', 'SEO Duplicates', 'SEO Duplicates', 'manage_alesta_ai',
			'alesta-ai-seo-duplicates', fn() => print( '<div class="wrap"><h1>SEO Duplicates AI</h1><p>Module en cours de migration (S3 Phase 2).</p></div>' ) );
	}
}
