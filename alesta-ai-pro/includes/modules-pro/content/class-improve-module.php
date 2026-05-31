<?php
/**
 * Alesta AI Pro — Improve module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~600 lignes).
 * Réécrit / améliore / simplifie / résume des paragraphes via Claude.
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Content;
use AlestaAI\Core\ExtensionsAPI;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class ImproveModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-improve'] = [ 'label' => 'Améliorer texte IA', 'category' => 'content',
				'description' => 'Bouton dans l\'éditeur Gutenberg pour réécrire/simplifier/améliorer un paragraphe' ];
			return $f;
		} );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_plugin' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
	}
	public function enqueue_block_plugin(): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		// TODO Phase S3 : enqueue le JS qui ajoute le sidebar Alesta AI dans Gutenberg
		// avec actions "Améliorer", "Simplifier", "Résumer", "Étendre"
	}
	public function register_rest(): void {
		register_rest_route( 'alesta-ai-pro/v1', '/improve', [
			'methods' => 'POST',
			'callback' => fn() => new \WP_REST_Response( [ 'todo' => 'porter logique depuis class-improve-module.php' ], 200 ),
			'permission_callback' => fn() => current_user_can( 'manage_alesta_ai' ),
		] );
	}
}
