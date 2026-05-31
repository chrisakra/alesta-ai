<?php
/**
 * Alesta AI Free — Security Audit module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~260 lignes).
 * Audit statique : permissions files, WP_DEBUG, user "admin", DB prefix, etc.
 *
 * @package AlestaAI\Modules\Security
 * @since   2.0.0
 */
namespace AlestaAI\Modules\Security;
use AlestaAI\Core\ExtensionsAPI;
defined( 'ABSPATH' ) || exit;

final class SecurityAudit {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai', 'Security Audit', 'Security Audit', 'manage_alesta_ai',
			'alesta-ai-security-audit', [ $this, 'render_admin_page' ],
		);
	}

	public function render_admin_page(): void {
		echo '<div class="wrap"><h1>Security Audit — Checklist de sécurité WordPress</h1>';
		echo '<div class="notice notice-warning"><p><strong>Module en cours de migration (S3 Phase 2).</strong></p></div>';
		ExtensionsAPI::render_module_actions( 'security-audit', [ 'page' => 'alesta-ai-security-audit' ] );
		echo '</div>';
	}
}
