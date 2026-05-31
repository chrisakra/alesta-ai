<?php
/**
 * Alesta AI Pro — Schema.org AI module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~520 lignes).
 * Génère JSON-LD Schema.org (Article, Product, FAQ, HowTo, Recipe...) via Claude.
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Seo;
use AlestaAI\Core\ExtensionsAPI;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class SchemaAIModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['schema-ai'] = [ 'label' => 'Schema.org IA', 'category' => 'seo',
				'description' => 'Génère JSON-LD Schema.org enrichi par Claude (Article, Product, FAQ, HowTo)' ];
			return $f;
		} );
		add_action( 'wp_head', [ $this, 'inject_schema_jsonld' ] );
	}
	public function inject_schema_jsonld(): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		// TODO Phase S3 : récupérer le schema cacheé du post + render <script type="application/ld+json">
	}
}
