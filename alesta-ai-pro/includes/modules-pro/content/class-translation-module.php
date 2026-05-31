<?php
/**
 * Alesta AI Pro — Translation 20 langues (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~380 lignes).
 * Traduction posts/pages via Claude. Solo=5 langues, Pro/Agency=20+, Unlimited=illimité.
 *
 * ⚠ Pro 1.3.22 appelait wp_remote_post Anthropic DIRECTEMENT (idem chatbot).
 *    À uniformiser via ExtensionsAPI::get_ai_provider('claude') lors du portage.
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Content;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class TranslationModule {
	private const LANG_LIMITS = [
		'AAP_SOLO' => 5, 'AAP_PRO' => 20, 'AAP_AGENCY' => 20, 'AAP_FOUNDERS' => 20, 'AAP_UNLIMITED' => 9999,
	];

	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-translation'] = [ 'label' => 'Traduction 20 langues', 'category' => 'content', 'icon' => 'translation',
				'description' => 'Traduction automatique posts/pages via Claude, jusqu\'à 20 langues' ];
			return $f;
		} );
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
	}
	public function add_metabox(): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		add_meta_box( 'alesta-ai-translation', '✨ Traduire', fn() => print( '<button type="button" class="button">✨ Traduire</button>' ),
			[ 'post', 'page' ], 'side' );
	}
}
