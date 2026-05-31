<?php
/**
 * Alesta AI Free — Alerts cron (STUB — portage S3 Phase 2)
 *
 * Cron WordPress qui tourne toutes les 15 min et envoie des alerts email :
 *   - Site down (curl HEAD home_url, retry 3x avant alerte)
 *   - SSL expire dans moins de 7 jours
 *   - Disk usage > 80% (uniquement WP Galiance qui expose la stat)
 *   - Brute force ban actif (intégration avec module security/brute-force)
 *
 * 285 lignes dans le Pro 1.3.22 — à porter en S3 Phase 2.
 *
 * Note : utilise le nouveau RateLimiter (\AlestaAI\Core\RateLimiter) pour
 * éviter le spam d'emails sur des alerts qui flapent (max 1 email/15min/type).
 *
 * @package AlestaAI\Modules\Settings
 * @since   2.0.0
 */

namespace AlestaAI\Modules\Settings;

use AlestaAI\Core\ExtensionsAPI;
use AlestaAI\Core\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class Alerts {

	private const CRON_HOOK = 'alesta_ai_alerts_check';

	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run_checks' ] );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'fifteen_minutes', self::CRON_HOOK );
		}
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_filter( 'cron_schedules', [ $this, 'register_cron_interval' ] );
	}

	public function register_cron_interval( array $schedules ): array {
		if ( ! isset( $schedules['fifteen_minutes'] ) ) {
			$schedules['fifteen_minutes'] = [
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => 'Toutes les 15 minutes',
			];
		}
		return $schedules;
	}

	public function run_checks(): void {
		// TODO Phase S3 : porter le code depuis class-alerts-module.php
		// Pattern :
		//   $rl = new RateLimiter( 'alert_site_down', 1, 15 * MINUTE_IN_SECONDS );
		//   if ( $this->is_site_down() && $rl->allow( 'global' ) ) {
		//     $this->send_alert( 'Site down', ... );
		//   }
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Alerts',
			'Alerts',
			'manage_alesta_ai',
			'alesta-ai-alerts',
			[ $this, 'render_admin_page' ]
		);
	}

	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1>Alerts — Surveillance & notifications</h1>
			<div class="notice notice-warning">
				<p><strong>Module en cours de migration (S3 Phase 2).</strong></p>
				<p>Référence : <code>Alesta AI Pro version/.../settings/class-alerts-module.php</code> (285 lignes).</p>
			</div>

			<?php ExtensionsAPI::render_module_actions( 'settings-alerts', [ 'page' => 'alesta-ai-alerts' ] ); ?>
		</div>
		<?php
	}
}
