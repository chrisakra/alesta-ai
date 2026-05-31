<?php
/**
 * Alesta AI Pro — Comments AI moderation (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~410 lignes).
 * Modération automatique commentaires (spam/toxic) + génération réponses suggérées.
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Content;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class CommentsModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-comments'] = [ 'label' => 'Modération commentaires', 'category' => 'content', 'icon' => 'admin-comments',
				'description' => 'Filtrage automatique spam/toxic + suggestions de réponse via Claude' ];
			return $f;
		} );
		add_filter( 'pre_comment_approved', [ $this, 'auto_moderate' ], 10, 2 );
	}
	public function auto_moderate( $approved, $commentdata ) {
		if ( ! LicenseManager::instance()->is_valid() ) return $approved;
		// TODO Phase S3 : Claude classify spam/toxic + retourner 0 (spam) ou 1 (approved) ou 'hold'
		return $approved;
	}
}
