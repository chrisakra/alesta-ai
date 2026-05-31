<?php
/**
 * Alesta AI Pro — PDF Report module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~410 lignes).
 * Génère rapport PDF mensuel SEO/perf/sécu, avec synthèse exécutive via Claude.
 * Inclus dans Pro/Agency/Founders, Solo inclut version simplifiée.
 *
 * @package AlestaAIPro\Modules\Reports
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Reports;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class PdfReportModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['reports-pdf'] = [ 'label' => 'Rapports PDF mensuels', 'category' => 'reports', 'icon' => 'pdf',
				'description' => 'Rapport PDF mensuel SEO/perf/sécu + synthèse exécutive IA, envoi automatique' ];
			return $f;
		} );
		// Cron mensuel pour génération + envoi
		add_action( 'alesta_ai_pro_monthly_report', [ $this, 'generate_and_send' ] );
		if ( ! wp_next_scheduled( 'alesta_ai_pro_monthly_report' ) ) {
			wp_schedule_event( strtotime( 'first day of next month 09:00' ), 'monthly', 'alesta_ai_pro_monthly_report' );
		}
	}
	public function generate_and_send(): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		// TODO Phase S3 : porter génération PDF via FPDF/TCPDF + résumé Claude + envoi email
	}
}
