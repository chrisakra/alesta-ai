<?php
/**
 * Alesta AI Pro — AI Metadata module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~580 lignes).
 * Génère via Claude : Open Graph, Twitter Cards, schema.org, alt text d'images.
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Seo;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class AiMetadataModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['seo-ai-metadata'] = [ 'label' => 'AI Metadata', 'category' => 'seo', 'icon' => 'admin-customizer',
				'description' => 'Génère Open Graph, Twitter Cards, schema.org, alt text via Claude' ];
			return $f;
		} );
		add_action( 'wp_head', [ $this, 'inject_meta_tags' ], 5 );
	}
	public function inject_meta_tags(): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		// TODO Phase S3 : récup meta cacheé en post_meta, render <meta property="og:..."> + <meta name="twitter:...">
	}
}
