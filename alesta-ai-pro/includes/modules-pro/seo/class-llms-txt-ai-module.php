<?php
/**
 * Alesta AI Pro — LLMs.txt AI Generator (STUB v2.0).
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~220 lignes).
 * Génère via Claude le fichier /llms.txt enrichi (descriptions, hiérarchie pages).
 * Le Free génère une version statique simple (liste URLs). Le Pro ajoute IA.
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.0
 */
namespace AlestaAIPro\Modules\Seo;
defined( 'ABSPATH' ) || exit;

final class LlmsTxtAIModule {
	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['seo-llms-txt'] = [ 'label' => 'LLMs.txt AI', 'category' => 'seo', 'icon' => 'text',
				'description' => 'llms.txt enrichi par Claude (descriptions IA + hiérarchie pages)' ];
			return $f;
		} );
		// Override le hook Free qui génère le llms.txt basique
		add_filter( 'alesta_ai/seo/llms_txt_content', [ $this, 'enhance_with_ai' ], 10, 2 );
	}
	public function enhance_with_ai( string $content, array $urls ): string {
		// TODO Phase S3 : porter classe-llms.php → ajout descriptions IA pour chaque URL
		return $content;
	}
}
