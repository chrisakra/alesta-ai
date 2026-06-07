<?php
/**
 * Alesta AI Pro — AI Metadata
 *
 * OG, Twitter Cards, schema.org, alt text via Claude
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Seo;

use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class AiMetadataModule {

	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['seo-ai-metadata'] = [
				'label'       => 'AI Metadata',
				'category'    => 'seo',
				'icon'        => 'admin-customizer',
				'description' => 'Génère Open Graph, Twitter Cards, schema.org, alt text via Claude',
			];
			return $f;
		} );

		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'wp_head', [ $this, 'inject_meta_tags' ], 5 );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'AI Metadata',
			'AI Metadata',
			'manage_alesta_ai',
			'alesta-ai-ai-metadata',
			[ $this, 'render_page' ]
		);
	}

	public function inject_meta_tags(): void {
		if ( ! LicenseManager::instance()->is_valid() ) {
			return;
		}
		$settings = get_option( 'alesta_ai_ai_metadata_settings', $this->default_settings() );
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		// TODO Phase S3 : récup meta cacheée en post_meta, render <meta property="og:..."> + <meta name="twitter:..."> + schema.org JSON-LD
	}

	public function render_page(): void {
		$settings = get_option( 'alesta_ai_ai_metadata_settings', $this->default_settings() );

		if (
			isset( $_POST['alesta_ai_ai_metadata_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_ai_metadata_nonce'] ) ), 'save_ai_metadata_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_ai_metadata_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}

		$models = [
			'claude-haiku-4-5'   => 'Claude Haiku 4.5 (rapide, économique)',
			'claude-sonnet-4-5'  => 'Claude Sonnet 4.5 (équilibré)',
			'claude-sonnet-4-6'  => 'Claude Sonnet 4.6 (recommandé)',
		];

		$post_types_available = get_post_types( [ 'public' => true ], 'objects' );
		$selected_post_types  = $settings['post_types'] ?? [ 'post', 'page' ];
		?>
		<div class="wrap">
			<h1>
				AI Metadata
				<span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span>
			</h1>
			<p class="description" style="max-width:720px;">
				Génère automatiquement via Claude les balises <strong>Open Graph</strong>, <strong>Twitter Cards</strong>, les données structurées <strong>schema.org</strong> et les attributs <strong>alt text</strong> d'images — sans plugin SEO tiers.
			</p>
			<hr/>

			<form method="post">
				<?php wp_nonce_field( 'save_ai_metadata_settings', 'alesta_ai_ai_metadata_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tbody>

						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="enabled"
										id="enabled"
										value="1"
										<?php checked( ! empty( $settings['enabled'] ) ); ?>
									/>
									Active la génération automatique des métadonnées via Claude
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="model">Modèle Claude</label></th>
							<td>
								<select name="model" id="model">
									<?php foreach ( $models as $model_id => $model_label ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $settings['model'] ?? 'claude-sonnet-4-6', $model_id ); ?>>
											<?php echo esc_html( $model_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Modèle utilisé pour la génération. Haiku = moins de tokens, Sonnet = meilleure qualité.</p>
							</td>
						</tr>

						<tr>
							<th scope="row">Types de contenu</th>
							<td>
								<?php foreach ( $post_types_available as $pt ) : ?>
									<label style="display:block;margin-bottom:4px;">
										<input
											type="checkbox"
											name="post_types[]"
											value="<?php echo esc_attr( $pt->name ); ?>"
											<?php checked( in_array( $pt->name, $selected_post_types, true ) ); ?>
										/>
										<?php echo esc_html( $pt->labels->singular_name ); ?> (<code><?php echo esc_html( $pt->name ); ?></code>)
									</label>
								<?php endforeach; ?>
								<p class="description">Types de contenus pour lesquels les métadonnées seront générées.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="og_prompt_template">Template de prompt OG/Twitter</label></th>
							<td>
								<textarea
									name="og_prompt_template"
									id="og_prompt_template"
									rows="5"
									class="large-text code"
									placeholder="Laisse vide pour utiliser le prompt par défaut."
								><?php echo esc_textarea( $settings['og_prompt_template'] ?? '' ); ?></textarea>
								<p class="description">
									Variables disponibles : <code>{title}</code>, <code>{content}</code>, <code>{excerpt}</code>, <code>{url}</code>.<br/>
									Claude génèrera : <code>og:title</code>, <code>og:description</code>, <code>twitter:title</code>, <code>twitter:description</code>.
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="schema_type">Type schema.org par défaut</label></th>
							<td>
								<select name="schema_type" id="schema_type">
									<?php
									$schema_types = [
										'Article'       => 'Article',
										'BlogPosting'   => 'BlogPosting',
										'WebPage'       => 'WebPage',
										'Product'       => 'Product',
										'Organization'  => 'Organization',
									];
									foreach ( $schema_types as $val => $label ) :
										?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['schema_type'] ?? 'Article', $val ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Type schema.org injecté en JSON-LD dans <code>&lt;head&gt;</code> pour les pages concernées.</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>

			<hr/>
			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Active le module</strong> via la case ci-dessus, puis sélectionne les types de contenu à couvrir.</li>
				<li>Lors de la <strong>publication ou mise à jour</strong> d'un article/page, Claude génère automatiquement les balises OG, Twitter Cards et le bloc JSON-LD schema.org à partir du titre et du contenu.</li>
				<li>Les métadonnées générées sont <strong>stockées en post_meta</strong> (<code>_alesta_og_meta</code>) et injectées dans <code>&lt;head&gt;</code> via le hook <code>wp_head</code>, sans conflit avec Yoast/RankMath.</li>
				<li>Pour les <strong>images</strong> sans attribut <code>alt</code>, Claude analyse le contexte de l'article et propose un alt text accessible, enregistré directement dans la médiathèque WP.</li>
				<li>Tu peux <strong>personnaliser le prompt</strong> dans le champ dédié pour adapter le ton, la longueur ou inclure des mots-clés SEO cibles.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
				<br/>
				<strong>Claude API à configurer</strong> — vérifie que ta clé API est renseignée dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages &rarr; Clés API</a>
				avant d'activer la génération automatique.
			</p>
		</div>
		<?php
	}

	private function default_settings(): array {
		return [
			'enabled'            => false,
			'model'              => 'claude-sonnet-4-6',
			'post_types'         => [ 'post', 'page' ],
			'og_prompt_template' => '',
			'schema_type'        => 'Article',
		];
	}

	private function sanitize_settings( array $input ): array {
		$allowed_models = [
			'claude-haiku-4-5',
			'claude-sonnet-4-5',
			'claude-sonnet-4-6',
		];

		$allowed_schema_types = [ 'Article', 'BlogPosting', 'WebPage', 'Product', 'Organization' ];

		$raw_post_types = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? $input['post_types']
			: [];

		$sanitized_post_types = array_filter(
			array_map( 'sanitize_key', $raw_post_types ),
			'post_type_exists'
		);

		$model = sanitize_text_field( $input['model'] ?? 'claude-sonnet-4-6' );
		if ( ! in_array( $model, $allowed_models, true ) ) {
			$model = 'claude-sonnet-4-6';
		}

		$schema_type = sanitize_text_field( $input['schema_type'] ?? 'Article' );
		if ( ! in_array( $schema_type, $allowed_schema_types, true ) ) {
			$schema_type = 'Article';
		}

		return [
			'enabled'            => ! empty( $input['enabled'] ),
			'model'              => $model,
			'post_types'         => array_values( $sanitized_post_types ),
			'og_prompt_template' => sanitize_textarea_field( $input['og_prompt_template'] ?? '' ),
			'schema_type'        => $schema_type,
		];
	}
}
