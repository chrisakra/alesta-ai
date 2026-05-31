<?php
/**
 * Alesta AI Pro — FAQ AI module (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~280 lignes).
 * Génère Q/R FAQ à partir du contenu d'un post + JSON-LD FAQPage.
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Seo;
use AlestaAI\Core\ExtensionsAPI;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class FaqAIModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['faq-ai'] = [ 'label' => 'FAQ IA', 'category' => 'seo',
				'description' => 'Génère 5-10 questions/réponses pertinentes via Claude + JSON-LD FAQPage' ];
			return $f;
		} );
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
	}
	public function add_metabox(): void {
		add_meta_box( 'alesta-ai-faq', '✨ FAQ AI', [ $this, 'render_metabox' ], [ 'post', 'page' ], 'side' );
	}
	public function render_metabox(): void {
		if ( ! LicenseManager::instance()->is_valid() ) {
			echo '<p>Licence Pro requise.</p>'; return;
		}
		echo '<button type="button" class="button button-primary">✨ Générer FAQ</button>';
		// TODO Phase S3 : ajax handler + render des Q/R + sauvegarde post_meta
	}
}
