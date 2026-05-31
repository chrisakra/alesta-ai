<?php
/**
 * Alesta AI Pro — Performance Audit AI (STUB v2.0, module SPLIT).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~210 lignes).
 *
 * Le module Free performance/perf-audit-core fait le scoring statique (Lighthouse-like).
 * Le Pro ajoute "recommandations priorisées via IA" — Claude analyse le rapport
 * et propose les 3 actions qui auront le plus d'impact pour ce site spécifique.
 *
 * @package AlestaAIPro\Modules\Performance
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Performance;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class PerfAuditAIModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['performance-audit-ai'] = [ 'label' => 'Audit perf priorisé IA', 'category' => 'performance', 'icon' => 'performance',
				'description' => 'Claude analyse ton rapport Lighthouse + propose les 3 actions à plus fort impact' ];
			return $f;
		} );
		add_action( 'alesta_ai/admin/performance-perf-audit/actions', [ $this, 'render_inject_button' ] );
	}
	public function render_inject_button( array $ctx ): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		echo '<button type="button" class="button button-primary">✨ Recommandations priorisées via IA</button>';
		// TODO Phase S3 : Claude analyse $ctx['audit_results'] + retourne top 3 actions
	}
}
