<?php
/**
 * Alesta AI Pro — Meta AI module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~360 lignes).
 * Génère titles + meta descriptions optimisées via Claude API.
 * BYOK Anthropic via APIKeyVault::get('anthropic').
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Seo;
use AlestaAI\Core\ExtensionsAPI;
use AlestaAI\Core\APIKeyVault;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class MetaAIModule {
	public function __construct() {
		// Injecte bouton "✨ Générer Title+Meta IA" dans le metabox SEO du module Free seo-meta-box
		add_action( 'alesta_ai/admin/seo-meta-box/actions', [ $this, 'render_inject_button' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['meta-ai'] = [
				'label' => 'Meta AI', 'category' => 'seo',
				'description' => 'Génère titles + meta descriptions optimisés via Claude',
			];
			return $f;
		} );
	}
	public function render_inject_button( array $ctx ): void {
		if ( ! LicenseManager::instance()->is_valid() || ! APIKeyVault::has( 'anthropic' ) ) return;
		echo '<button class="button button-primary" data-post="' . esc_attr( $ctx['post_id'] ?? 0 ) . '">✨ Générer Title+Meta IA</button>';
	}
	public function register_rest(): void {
		register_rest_route( 'alesta-ai-pro/v1', '/meta/generate', [
			'methods' => 'POST',
			'callback' => fn() => new \WP_REST_Response( [ 'todo' => 'porter logique depuis class-meta-module.php' ], 200 ),
			'permission_callback' => fn() => current_user_can( 'manage_alesta_ai' ),
		] );
	}
}
