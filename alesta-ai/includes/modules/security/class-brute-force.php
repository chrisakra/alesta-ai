<?php
/**
 * Alesta AI Free — Brute Force Protection module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~210 lignes).
 * Utilise le nouveau RateLimiter core : max 5 tentatives login/15 min/IP.
 *
 * @package AlestaAI\Modules\Security
 * @since   2.0.0
 */
namespace AlestaAI\Modules\Security;
use AlestaAI\Core\ExtensionsAPI;
use AlestaAI\Core\RateLimiter;
defined( 'ABSPATH' ) || exit;

final class BruteForce {
	private RateLimiter $rl;

	public function __construct() {
		$this->rl = new RateLimiter( 'login_attempt', 5, 15 * MINUTE_IN_SECONDS );
		add_filter( 'authenticate', [ $this, 'check_before_auth' ], 1, 1 );
		add_action( 'wp_login_failed', [ $this, 'record_failed_login' ] );
		add_action( 'wp_login', [ $this, 'reset_on_success' ], 10, 1 );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	public function check_before_auth( $user ) {
		// TODO Phase S3 : reject avant verif credentials si rate limit dépassé
		return $user;
	}
	public function record_failed_login(): void {
		// TODO Phase S3 : $this->rl->allow($_SERVER['REMOTE_ADDR'])
	}
	public function reset_on_success( string $username ): void {
		// TODO Phase S3 : $this->rl->reset($_SERVER['REMOTE_ADDR'])
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai', 'Brute Force Protection', 'Brute Force', 'manage_alesta_ai',
			'alesta-ai-brute-force', [ $this, 'render_admin_page' ],
		);
	}
	public function render_admin_page(): void {
		echo '<div class="wrap"><h1>Brute Force Protection</h1>';
		echo '<div class="notice notice-warning"><p><strong>Module en cours de migration (S3 Phase 2).</strong></p></div>';
		ExtensionsAPI::render_module_actions( 'security-brute-force', [ 'page' => 'alesta-ai-brute-force' ] );
		echo '</div>';
	}
}
