<?php
/**
 * Alesta AI Pro — Chatbot Claude (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~520 lignes).
 * Widget chatbot frontend qui répond aux visiteurs via Claude, contexte = contenu site.
 *
 * ⚠ Pro 1.3.22 appelait wp_remote_post('https://api.anthropic.com/...') DIRECTEMENT
 *    (court-circuit du tracking budget). À UNIFORMISER lors du portage : passer
 *    par le provider Claude enregistré (ExtensionsAPI::get_ai_provider('claude')).
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Content;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class ChatbotModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-chatbot'] = [ 'label' => 'Chatbot Claude', 'category' => 'content', 'icon' => 'format-chat',
				'description' => 'Widget chatbot frontend qui répond aux visiteurs en s\'appuyant sur ton contenu' ];
			return $f;
		} );
		add_action( 'wp_footer', [ $this, 'render_widget' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
	}
	public function render_widget(): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		// TODO Phase S3 : enqueue chatbot.js + render <div id="alesta-chatbot-root"></div>
	}
	public function register_rest(): void {
		register_rest_route( 'alesta-ai-pro/v1', '/chatbot/message', [
			'methods' => 'POST',
			'callback' => fn() => new \WP_REST_Response( [ 'todo' => 'porter logique chatbot-module.php + uniformiser via $api->ask()' ], 200 ),
			'permission_callback' => '__return_true',
		] );
	}
}
