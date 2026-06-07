<?php
/**
 * Alesta AI Pro — Schema.org IA
 *
 * JSON-LD Schema.org enrichi par Claude (Article, Product, FAQ, HowTo)
 *
 * Tier : pro
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Seo;

use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class SchemaAIModule {

	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['schema-ai'] = [
				'label'       => 'Schema.org IA',
				'category'    => 'seo',
				'description' => 'Génère JSON-LD Schema.org enrichi par Claude (Article, Product, FAQ, HowTo)',
			];
			return $f;
		} );

		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'wp_head',    [ $this, 'inject_schema_jsonld' ] );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Schema.org IA',
			'Schema.org IA',
			'manage_alesta_ai',
			'alesta-ai-schema-ai',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		$settings = get_option( 'alesta_ai_schema_ai_settings', $this->default_settings() );

		if (
			isset( $_POST['alesta_ai_schema_ai_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_schema_ai_nonce'] ) ), 'save_schema_ai_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_schema_ai_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}

		$schema_types = [
			'Article'      => 'Article (blog, actualité)',
			'Product'      => 'Product (fiche produit)',
			'FAQPage'      => 'FAQPage (questions-réponses)',
			'HowTo'        => 'HowTo (tutoriel étape par étape)',
			'Recipe'       => 'Recipe (recette)',
			'LocalBusiness'=> 'LocalBusiness (entreprise locale)',
		];

		$claude_models = [
			'claude-haiku-4-5'   => 'Claude Haiku (rapide, économique)',
			'claude-sonnet-4-5'  => 'Claude Sonnet (équilibré) — recommandé',
			'claude-opus-4-5'    => 'Claude Opus (plus précis, plus lent)',
		];
		?>
		<div class="wrap">
			<h1>
				Schema.org IA
				<span style="display:inline-block;background:#1e3a5f;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">PRO</span>
			</h1>
			<p class="description" style="max-width:720px;">
				Génère et injecte automatiquement des balises <code>&lt;script type="application/ld+json"&gt;</code> Schema.org enrichies par Claude dans l'en-tête de vos pages WordPress — pour améliorer l'indexation et les rich snippets Google.
			</p>
			<hr/>

			<form method="post">
				<?php wp_nonce_field( 'save_schema_ai_settings', 'alesta_ai_schema_ai_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tbody>

						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Active la génération et l'injection automatique des schemas JSON-LD
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php foreach ( $claude_models as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['claude_model'], $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Modèle utilisé pour analyser le contenu de la page et générer le schema.</p>
							</td>
						</tr>

						<tr>
							<th scope="row">Types de schema actifs</th>
							<td>
								<?php foreach ( $schema_types as $type => $label ) : ?>
									<label style="display:block;margin-bottom:6px;">
										<input
											type="checkbox"
											name="schema_types[]"
											value="<?php echo esc_attr( $type ); ?>"
											<?php checked( in_array( $type, $settings['schema_types'], true ) ); ?>
										/>
										<strong><?php echo esc_html( $type ); ?></strong> — <?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description">Claude choisira le type le plus adapté parmi ceux cochés, selon le contenu de la page.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="cache_duration">Durée du cache (heures)</label></th>
							<td>
								<input
									type="number"
									name="cache_duration"
									id="cache_duration"
									value="<?php echo esc_attr( $settings['cache_duration'] ); ?>"
									min="1"
									max="720"
									style="width:80px;"
								/>
								<p class="description">Les schemas générés sont mis en cache pour éviter les appels API répétés. Recommandé : 24h.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="custom_prompt">Instructions personnalisées (optionnel)</label></th>
							<td>
								<textarea
									name="custom_prompt"
									id="custom_prompt"
									rows="4"
									cols="60"
									placeholder="Ex : Inclus toujours le nom de l'auteur. Utilise le logo https://... pour Organization."
								><?php echo esc_textarea( $settings['custom_prompt'] ); ?></textarea>
								<p class="description">Consignes supplémentaires transmises à Claude lors de la génération du schema.</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>

			<hr/>

			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Active le module</strong> via la case ci-dessus et enregistre.</li>
				<li><strong>Sélectionne les types de schema</strong> que tu veux couvrir (Article, FAQ, Product…). Claude choisira le plus pertinent pour chaque page.</li>
				<li>Lors de la <strong>première visite d'une page</strong>, Claude analyse son contenu (titre, corps, métadonnées) et génère le JSON-LD Schema.org adapté.</li>
				<li>Le schema est <strong>mis en cache</strong> pour la durée configurée, puis injecté silencieusement dans <code>&lt;head&gt;</code> sans ralentir l'affichage.</li>
				<li>Vérifie le résultat avec l'<a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">outil Rich Results de Google</a> — les rich snippets apparaissent généralement sous 1 à 2 semaines dans la Search Console.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.<br/>
				<?php if ( ! LicenseManager::instance()->is_valid() ) : ?>
					<span style="color:#c00;font-weight:600;">&#9888; Licence Pro invalide ou absente.</span> Ce module est inactif.
				<?php else : ?>
					<span style="color:#0a0;font-weight:600;">&#10003; Licence Pro active.</span>
				<?php endif; ?>
				<br/>
				Vérifie que ta clé API Anthropic est configurée dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages &rarr; Clés API</a>.
			</p>

			<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px 16px;margin-top:16px;max-width:720px;">
				<strong>Phase d'implémentation :</strong> La génération Claude automatique sera active en Phase S3.
				Les réglages que tu configures ici seront appliqués dès l'activation de cette phase.
			</div>
		</div>
		<?php
	}

	public function inject_schema_jsonld(): void {
		if ( ! LicenseManager::instance()->is_valid() ) {
			return;
		}

		$settings = get_option( 'alesta_ai_schema_ai_settings', $this->default_settings() );
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		// TODO Phase S3 : récupérer le schema mis en cache pour le post courant
		// et injecter <script type="application/ld+json">...</script> dans <head>.
	}

	private function default_settings(): array {
		return [
			'enabled'         => false,
			'claude_model'    => 'claude-sonnet-4-5',
			'schema_types'    => [ 'Article', 'FAQPage' ],
			'cache_duration'  => 24,
			'custom_prompt'   => '',
		];
	}

	private function sanitize_settings( array $input ): array {
		$allowed_models = [
			'claude-haiku-4-5',
			'claude-sonnet-4-5',
			'claude-opus-4-5',
		];
		$allowed_types = [
			'Article', 'Product', 'FAQPage', 'HowTo', 'Recipe', 'LocalBusiness',
		];

		$selected_types = isset( $input['schema_types'] ) && is_array( $input['schema_types'] )
			? array_values( array_intersect( $input['schema_types'], $allowed_types ) )
			: [];

		$model = isset( $input['claude_model'] ) && in_array( $input['claude_model'], $allowed_models, true )
			? $input['claude_model']
			: 'claude-sonnet-4-5';

		$cache_duration = isset( $input['cache_duration'] )
			? max( 1, min( 720, (int) $input['cache_duration'] ) )
			: 24;

		return [
			'enabled'        => ! empty( $input['enabled'] ),
			'claude_model'   => $model,
			'schema_types'   => $selected_types,
			'cache_duration' => $cache_duration,
			'custom_prompt'  => isset( $input['custom_prompt'] )
				? sanitize_textarea_field( wp_unslash( $input['custom_prompt'] ) )
				: '',
		];
	}
}
