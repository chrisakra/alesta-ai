<?php
/**
 * Alesta AI Free — Dashboard Widget
 *
 * Widget WordPress Dashboard (visible sur /wp-admin) qui affiche un résumé :
 *   - Sites du module Alesta AI (sitemap, RGPD, etc. actifs)
 *   - Liens rapides vers les pages de réglages
 *   - Si Pro non actif : encart upsell vers alesta-ai.com
 *   - Si Pro actif : stats usage IA mensuelles (via hook que le Pro fournit)
 *
 * Migré du Pro 1.3.22 — pas de dépendance IA, c'est juste un widget UI → FREE.
 *
 * @package AlestaAI\Dashboard
 * @since   2.0.0
 */

namespace AlestaAI\Dashboard;

use AlestaAI\Core\ExtensionsAPI;
use AlestaAI\Core\ModuleRegistry;

defined( 'ABSPATH' ) || exit;

final class DashboardWidget {

	public function __construct() {
		add_action( 'wp_dashboard_setup', [ $this, 'register_widget' ] );
	}

	public function register_widget(): void {
		wp_add_dashboard_widget(
			'alesta_ai_dashboard_widget',
			'⚡ Alesta AI — Vue d\'ensemble',
			[ $this, 'render' ],
		);
	}

	public function render(): void {
		$registry = ModuleRegistry::instance();
		$modules  = $registry->all();
		$pro_active = ExtensionsAPI::is_pro_active();
		?>
		<div style="font-family:-apple-system,sans-serif;">

			<!-- Stats modules -->
			<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;">
				<div style="text-align:center;padding:10px;background:rgba(167,139,250,.08);border-radius:8px;">
					<div style="font-size:24px;font-weight:700;color:#a78bfa;line-height:1;"><?php echo count( $modules ); ?></div>
					<div style="font-size:11px;color:#666;margin-top:4px;text-transform:uppercase;">Modules</div>
				</div>
				<div style="text-align:center;padding:10px;background:rgba(16,185,129,.08);border-radius:8px;">
					<div style="font-size:24px;font-weight:700;color:#10b981;line-height:1;">
						<?php echo count( $registry->all( [ 'source' => 'free' ] ) ); ?>
					</div>
					<div style="font-size:11px;color:#666;margin-top:4px;text-transform:uppercase;">Free</div>
				</div>
				<div style="text-align:center;padding:10px;background:rgba(139,92,246,.08);border-radius:8px;">
					<div style="font-size:24px;font-weight:700;color:#8b5cf6;line-height:1;">
						<?php echo count( $registry->all( [ 'source' => 'pro' ] ) ); ?>
					</div>
					<div style="font-size:11px;color:#666;margin-top:4px;text-transform:uppercase;">Pro</div>
				</div>
			</div>

			<!-- Status Pro -->
			<?php if ( $pro_active ): ?>
				<div style="padding:12px;background:linear-gradient(135deg,rgba(167,139,250,.1),rgba(139,92,246,.05));border-left:3px solid #a78bfa;border-radius:6px;margin-bottom:16px;">
					<strong style="color:#5b21b6;">✓ Alesta AI Pro actif</strong>
					<div style="font-size:12px;color:#666;margin-top:4px;">
						Version <?php echo esc_html( ExtensionsAPI::pro_version() ?? '?' ); ?> — toutes les fonctionnalités IA débloquées.
					</div>
				</div>
			<?php else: ?>
				<div style="padding:12px;background:#f3f0ff;border-left:3px solid #a78bfa;border-radius:6px;margin-bottom:16px;">
					<strong>✨ Débloquez Alesta AI Pro</strong>
					<div style="font-size:12px;color:#444;margin-top:4px;">
						17 modules IA premium : génération SEO, content, chatbot Claude…
						<a href="https://www.alesta-ai.com/pricing?utm_source=plugin&utm_campaign=dashboard-widget" target="_blank" style="color:#7c3aed;font-weight:600;">Découvrir →</a>
					</div>
				</div>
			<?php endif; ?>

			<!-- Liens rapides -->
			<div style="display:flex;gap:8px;flex-wrap:wrap;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai' ) ); ?>" class="button">Tableau de bord</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-sitemap' ) ); ?>" class="button">Sitemap</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-rgpd' ) ); ?>" class="button">RGPD</a>
			</div>

			<?php
			// Hook d'injection : le Pro peut ajouter ses stats usage IA mensuelles ici
			ExtensionsAPI::render_module_actions( 'dashboard-widget', [
				'modules_count' => count( $modules ),
			] );
			?>
		</div>
		<?php
	}
}
