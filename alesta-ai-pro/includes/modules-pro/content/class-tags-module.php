<?php
/**
 * Alesta AI Pro — Tags AI suggestions (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~240 lignes).
 * Suggère automatiquement les tags WordPress les plus pertinents pour un post via Claude.
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Content;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class TagsModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-tags'] = [ 'label' => 'Tags AI suggestions', 'category' => 'content', 'icon' => 'tag',
				'description' => 'Suggère les meilleurs tags WordPress pour chaque post via Claude' ];
			return $f;
		} );
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
	}
	public function add_metabox(): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		add_meta_box( 'alesta-ai-tags', '✨ Tags AI', fn() => print( '<button type="button" class="button">✨ Suggérer tags</button>' ),
			[ 'post' ], 'side' );
	}
}
