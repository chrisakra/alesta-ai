<?php
/**
 * Alesta AI Pro — FAQ IA
 *
 * Génère 5-10 questions/réponses pertinentes via Claude + JSON-LD FAQPage.
 * Injecte automatiquement le schema markup sur les posts/pages activés.
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Seo;

use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class FaqAIModule {

	public function __construct() {
		// Enregistrement de la feature dans le registre Pro
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['faq-ai'] = [
				'label'       => 'FAQ IA',
				'category'    => 'seo',
				'description' => 'Génère 5-10 questions/réponses pertinentes via Claude + JSON-LD FAQPage',
			];
			return $f;
		} );

		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );

		// Injection JSON-LD en front si le module est actif
		$settings = get_option( 'alesta_ai_faq_ai_settings', $this->default_settings() );
		if ( ! empty( $settings['enabled'] ) && ! empty( $settings['inject_jsonld'] ) ) {
			add_action( 'wp_head', [ $this, 'inject_jsonld' ] );
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Admin menu                                                          */
	/* ------------------------------------------------------------------ */

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'FAQ IA',
			'FAQ IA',
			'manage_alesta_ai',
			'alesta-ai-faq-ai',
			[ $this, 'render_page' ]
		);
	}

	/* ------------------------------------------------------------------ */
	/*  Page admin                                                          */
	/* ------------------------------------------------------------------ */

	public function render_page(): void {
		$settings = get_option( 'alesta_ai_faq_ai_settings', $this->default_settings() );

		if (
			isset( $_POST['alesta_ai_faq_ai_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_faq_ai_nonce'] ) ), 'save_faq_ai_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_faq_ai_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}

		$models = [
			'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (rapide)',
			'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (recommandé)',
			'claude-opus-4-5'            => 'Claude Opus 4 (premium)',
		];

		$post_types_available = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<div class="wrap">
			<h1>
				FAQ IA
				<span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span>
			</h1>
			<p class="description" style="max-width:720px;">
				Génère automatiquement 5-10 questions/réponses pertinentes à partir du contenu de vos pages et articles, puis injecte un schema <code>JSON-LD FAQPage</code> pour améliorer votre référencement.
			</p>
			<hr/>

			<form method="post">
				<?php wp_nonce_field( 'save_faq_ai_settings', 'alesta_ai_faq_ai_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tbody>

						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1"
										<?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Active la génération de FAQ IA sur votre site
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php foreach ( $models as $model_id => $model_label ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>"
											<?php selected( $settings['claude_model'], $model_id ); ?>>
											<?php echo esc_html( $model_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Haiku est plus rapide et économique ; Sonnet offre une meilleure qualité rédactionnelle.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="faq_count">Nombre de Q/R à générer</label></th>
							<td>
								<input type="number" name="faq_count" id="faq_count" min="3" max="10"
									value="<?php echo esc_attr( $settings['faq_count'] ); ?>" class="small-text" />
								<p class="description">Entre 3 et 10. Recommandé : 5 à 7 pour un bon équilibre contenu/UX.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="prompt_template">Prompt template</label></th>
							<td>
								<textarea name="prompt_template" id="prompt_template" rows="5"
									class="large-text" style="font-family:monospace;font-size:12px;"><?php echo esc_textarea( $settings['prompt_template'] ); ?></textarea>
								<p class="description">
									Utilisez <code>{post_title}</code> et <code>{post_content}</code> comme variables.<br/>
									Laissez vide pour utiliser le prompt par défaut.
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="inject_jsonld">Injection JSON-LD automatique</label></th>
							<td>
								<label>
									<input type="checkbox" name="inject_jsonld" id="inject_jsonld" value="1"
										<?php checked( ! empty( $settings['inject_jsonld'] ) ); ?> />
									Injecter le schema <code>FAQPage</code> dans <code>&lt;head&gt;</code> des posts/pages concernés
								</label>
								<p class="description">Améliore la visibilité dans les résultats de recherche Google (rich snippets).</p>
							</td>
						</tr>

						<tr>
							<th scope="row">Types de contenu</th>
							<td>
								<?php foreach ( $post_types_available as $pt ) : ?>
									<label style="display:inline-block;margin-right:16px;">
										<input type="checkbox" name="post_types[]"
											value="<?php echo esc_attr( $pt->name ); ?>"
											<?php checked( in_array( $pt->name, (array) $settings['post_types'], true ) ); ?> />
										<?php echo esc_html( $pt->labels->singular_name ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description">Types de contenus sur lesquels le module sera disponible (meta box éditeur).</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>

			<hr/>
			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Active le module</strong> via la case ci-dessus et choisis le modèle Claude adapté à ton budget/qualité.</li>
				<li><strong>Ouvre un article ou une page</strong> dans l'éditeur WordPress — un bloc <em>FAQ IA</em> apparaît dans la colonne latérale.</li>
				<li><strong>Clique sur "Générer FAQ"</strong> : Claude analyse le contenu de la page et produit les Q/R en quelques secondes.</li>
				<li><strong>Relis et ajuste</strong> les questions générées directement dans les champs du bloc avant de publier.</li>
				<li>Si l'option <em>Injection JSON-LD</em> est activée, le schema <code>FAQPage</code> est automatiquement inséré dans le <code>&lt;head&gt;</code> de la page publiée — aucune action supplémentaire requise.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <strong>Claude</strong> (Anthropic) via le provider Pro Addon.<br/>
				Vérifie que ta clé API est configurée dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages → Clés API</a>.
			</p>
			<p><em style="color:#888;">Note : la génération Claude sera disponible en Phase S3. Pour l'instant, le bouton "Générer FAQ" dans l'éditeur est un placeholder.</em></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/*  Meta box éditeur                                                    */
	/* ------------------------------------------------------------------ */

	public function add_metabox(): void {
		$settings   = get_option( 'alesta_ai_faq_ai_settings', $this->default_settings() );
		$post_types = ! empty( $settings['post_types'] ) ? (array) $settings['post_types'] : [ 'post', 'page' ];

		add_meta_box(
			'alesta-ai-faq',
			'FAQ IA',
			[ $this, 'render_metabox' ],
			$post_types,
			'side'
		);
	}

	public function render_metabox(): void {
		if ( ! LicenseManager::instance()->is_valid() ) {
			echo '<p>Licence Pro requise.</p>';
			return;
		}
		echo '<button type="button" class="button button-primary">Générer FAQ</button>';
		echo '<p style="margin-top:8px;font-size:11px;color:#888;">Génération Claude disponible en Phase S3.</p>';
		// TODO Phase S3 : ajax handler + render des Q/R + sauvegarde post_meta
	}

	/* ------------------------------------------------------------------ */
	/*  JSON-LD front-end                                                   */
	/* ------------------------------------------------------------------ */

	public function inject_jsonld(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_the_ID();
		$faq     = get_post_meta( $post_id, '_alesta_ai_faq', true );
		if ( empty( $faq ) || ! is_array( $faq ) ) {
			return;
		}
		$entities = [];
		foreach ( $faq as $item ) {
			if ( empty( $item['q'] ) || empty( $item['a'] ) ) {
				continue;
			}
			$entities[] = [
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $item['q'] ),
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $item['a'] ),
				],
			];
		}
		if ( empty( $entities ) ) {
			return;
		}
		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		];
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/* ------------------------------------------------------------------ */
	/*  Helpers                                                             */
	/* ------------------------------------------------------------------ */

	private function default_settings(): array {
		return [
			'enabled'         => false,
			'claude_model'    => 'claude-3-5-sonnet-20241022',
			'faq_count'       => 5,
			'prompt_template' => '',
			'inject_jsonld'   => true,
			'post_types'      => [ 'post', 'page' ],
		];
	}

	private function sanitize_settings( array $input ): array {
		$allowed_models = [
			'claude-3-5-haiku-20241022',
			'claude-3-5-sonnet-20241022',
			'claude-opus-4-5',
		];

		$model = isset( $input['claude_model'] ) ? sanitize_text_field( $input['claude_model'] ) : 'claude-3-5-sonnet-20241022';
		if ( ! in_array( $model, $allowed_models, true ) ) {
			$model = 'claude-3-5-sonnet-20241022';
		}

		$faq_count = isset( $input['faq_count'] ) ? (int) $input['faq_count'] : 5;
		$faq_count = max( 3, min( 10, $faq_count ) );

		$post_types_raw = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? $input['post_types']
			: [];
		$post_types = array_map( 'sanitize_key', $post_types_raw );

		return [
			'enabled'         => ! empty( $input['enabled'] ),
			'claude_model'    => $model,
			'faq_count'       => $faq_count,
			'prompt_template' => isset( $input['prompt_template'] ) ? sanitize_textarea_field( wp_unslash( $input['prompt_template'] ) ) : '',
			'inject_jsonld'   => ! empty( $input['inject_jsonld'] ),
			'post_types'      => $post_types,
		];
	}
}
