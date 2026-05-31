<?php
/**
 * Alesta AI Pro — Summaries module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~310 lignes).
 * Génère résumé auto (TL;DR) à la fin de chaque post long via Claude.
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Content;
use AlestaAI\Core\ExtensionsAPI;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class SummariesModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-summaries'] = [ 'label' => 'Résumés auto', 'category' => 'content',
				'description' => 'TL;DR généré par Claude au début des posts longs (>1500 mots), cacheé post_meta' ];
			return $f;
		} );
		add_filter( 'the_content', [ $this, 'maybe_prepend_summary' ], 5 );
	}
	public function maybe_prepend_summary( string $content ): string {
		if ( ! is_singular( 'post' ) ) return $content;
		if ( ! LicenseManager::instance()->is_valid() ) return $content;
		// TODO Phase S3 : récup summary depuis post_meta, fallback génération lazy via Claude
		return $content;
	}
}
