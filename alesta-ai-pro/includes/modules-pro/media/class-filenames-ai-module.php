<?php
/**
 * Alesta AI Pro — Filenames AI suggestions (STUB v2.0, module SPLIT).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~290 lignes).
 *
 * Le module Free media/filenames-core renomme manuellement (slugify, sanitize).
 * Le Pro injecte un bouton "✨ Suggérer 3 noms SEO" via Claude qui analyse l'image.
 *
 * @package AlestaAIPro\Modules\Media
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Media;
use AlestaAI\Core\ExtensionsAPI;
use AlestaAIPro\License\LicenseManager;
defined( 'ABSPATH' ) || exit;

final class FilenamesAIModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['media-filenames-ai'] = [ 'label' => 'Filenames AI', 'category' => 'media', 'icon' => 'format-image',
				'description' => 'Suggère 3 noms de fichier SEO-friendly par image via Claude (alt + contexte post)' ];
			return $f;
		} );
		// Injection UI dans le module Free media/filenames-core
		add_action( 'alesta_ai/admin/media-filenames/actions', [ $this, 'render_inject_button' ] );
	}
	public function render_inject_button( array $ctx ): void {
		if ( ! LicenseManager::instance()->is_valid() ) return;
		echo '<button type="button" class="button button-primary">✨ Suggérer 3 noms SEO via IA</button>';
		// TODO Phase S3 : porter handler AJAX + appel Claude vision
	}
}
