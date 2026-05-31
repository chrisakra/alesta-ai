<?php
/**
 * Alesta AI Free — DB Cleaner module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~190 lignes).
 * Nettoie : révisions posts, transients expirés, spam/trash comments, drafts orphelins.
 *
 * @package AlestaAI\Modules\Performance
 * @since   2.0.0
 */
namespace AlestaAI\Modules\Performance;
use AlestaAI\Core\ExtensionsAPI;
defined( 'ABSPATH' ) || exit;

final class DbCleaner {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'alesta_ai_db_cleaner_cron', [ $this, 'run_cleanup' ] );
		if ( ! wp_next_scheduled( 'alesta_ai_db_cleaner_cron' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'alesta_ai_db_cleaner_cron' );
		}
	}

	public function run_cleanup(): void {
		// TODO Phase S3 : porter la logique depuis class-db-cleaner-module.php
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai', 'DB Cleaner', 'DB Cleaner', 'manage_alesta_ai',
			'alesta-ai-db-cleaner', [ $this, 'render_admin_page' ],
		);
	}

	public function render_admin_page(): void {
		echo '<div class="wrap"><h1>DB Cleaner — Nettoyage base de données</h1>';
		echo '<div class="notice notice-warning"><p><strong>Module en cours de migration (S3 Phase 2).</strong></p></div>';
		ExtensionsAPI::render_module_actions( 'performance-db-cleaner', [ 'page' => 'alesta-ai-db-cleaner' ] );
		echo '</div>';
	}
}
