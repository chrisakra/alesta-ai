<?php
/**
 * Alesta AI Pro — Redirects AI (STUB v2.0, module SPLIT).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~180 lignes).
 *
 * Le module Free performance/redirects-core gère les redirections 301/302 manuelles.
 * Le Pro ajoute "suggérer URL de redirection via IA" quand un 404 est détecté
 * (Claude analyse l'URL cassée + contenu site + propose la meilleure cible).
 *
 * @package AlestaAIPro\Modules\Performance
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Performance;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class RedirectsAIModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['performance-redirects-ai'] = [ 'label' => 'Redirections IA', 'category' => 'performance', 'icon' => 'redo',
				'description' => 'Suggère automatiquement la meilleure cible de redirect 301 quand un 404 est détecté' ];
			return $f;
		} );
		add_action( 'alesta_ai/admin/performance-redirects/actions', [ $this, 'render_inject_button' ] );
	}
	public function render_inject_button( array $ctx ): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		echo '<button type="button" class="button button-primary">✨ Suggérer redirect via IA</button>';
		// TODO Phase S3 : porter handler + Claude API + auto-création de redirect
	}
}
