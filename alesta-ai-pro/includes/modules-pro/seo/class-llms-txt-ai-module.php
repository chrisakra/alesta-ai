<?php
/**
 * Alesta AI Pro — LLMs.txt AI
 *
 * llms.txt enrichi par Claude (descriptions + hiérarchie pages).
 * Le Free génère une version statique simple (liste URLs). Le Pro ajoute l'IA :
 * descriptions enrichies, résumés, hiérarchie sémantique des pages.
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Seo;

defined( 'ABSPATH' ) || exit;

final class LlmsTxtAIModule {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );

		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['seo-llms-txt'] = [
				'label'       => 'LLMs.txt AI',
				'category'    => 'seo',
				'icon'        => 'text',
				'description' => 'llms.txt enrichi par Claude (descriptions IA + hiérarchie pages)',
			];
			return $f;
		} );

		// Override le hook Free qui génère le llms.txt basique
		add_filter( 'alesta_ai/seo/llms_txt_content', [ $this, 'enhance_with_ai' ], 10, 2 );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'LLMs.txt AI',
			'LLMs.txt AI',
			'manage_alesta_ai',
			'alesta-ai-llms-txt-ai',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		$option_key = 'alesta_ai_llms_txt_ai_settings';
		$nonce_action = 'save_llms_txt_ai_settings';
		$nonce_field  = 'alesta_ai_llms_txt_ai_nonce';

		$settings = get_option( $option_key, $this->default_settings() );

		if ( isset( $_POST[ $nonce_field ] ) &&
			 wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( $option_key, $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}

		$model_options = [
			'claude-haiku-4-5'   => 'Claude Haiku 4.5 (rapide, économique)',
			'claude-sonnet-4-5'  => 'Claude Sonnet 4.5 (équilibré)',
			'claude-sonnet-4-6'  => 'Claude Sonnet 4.6 (recommandé)',
		];

		$frequency_options = [
			'manual'  => 'Manuellement (déclenchement manuel uniquement)',
			'daily'   => 'Quotidiennement (WP Cron)',
			'weekly'  => 'Hebdomadairement (WP Cron)',
			'monthly' => 'Mensuellement (WP Cron)',
		];
		?>
		<div class="wrap">
			<h1>LLMs.txt AI <span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span></h1>
			<p class="description" style="max-width:720px;">
				Génère automatiquement un fichier <code>/llms.txt</code> enrichi par Claude : descriptions sémantiques de chaque page,
				hiérarchie du contenu et résumés compréhensibles par les LLMs (ChatGPT, Gemini, Claude, Perplexity…).
				La version Free génère une liste statique d'URLs ; le Pro ajoute l'IA.
			</p>
			<hr/>
			<form method="post">
				<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>
				<table class="form-table">
					<tbody>

						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1"
										<?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Active la génération IA du fichier <code>llms.txt</code>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php foreach ( $model_options as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>"
											<?php selected( $settings['claude_model'], $val ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Modèle utilisé pour générer les descriptions et résumés de chaque page.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="frequency">Fréquence de régénération</label></th>
							<td>
								<select name="frequency" id="frequency">
									<?php foreach ( $frequency_options as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>"
											<?php selected( $settings['frequency'], $val ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">À quelle fréquence le fichier <code>llms.txt</code> est régénéré automatiquement.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="prompt_template">Prompt personnalisé (optionnel)</label></th>
							<td>
								<textarea name="prompt_template" id="prompt_template" rows="5" class="large-text"
									placeholder="Laisse vide pour utiliser le prompt par défaut."><?php echo esc_textarea( $settings['prompt_template'] ); ?></textarea>
								<p class="description">
									Personnalise les instructions envoyées à Claude pour décrire chaque page.
									Utilise <code>{url}</code> et <code>{title}</code> comme variables dynamiques.
									Laisse vide pour le prompt par défaut.
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="include_pages">Types de contenus inclus</label></th>
							<td>
								<?php
								$post_types = [
									'page' => 'Pages',
									'post' => 'Articles',
								];
								$included = isset( $settings['include_post_types'] ) ? (array) $settings['include_post_types'] : [ 'page', 'post' ];
								foreach ( $post_types as $pt_val => $pt_label ) :
									?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="include_post_types[]"
											value="<?php echo esc_attr( $pt_val ); ?>"
											<?php checked( in_array( $pt_val, $included, true ) ); ?> />
										<?php echo esc_html( $pt_label ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description">Types de contenus analysés par Claude pour construire le <code>llms.txt</code>.</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>

			<hr/>

			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Active le module</strong> via la case "Activer le module" ci-dessus et enregistre.</li>
				<li><strong>Choisis le modèle Claude</strong> selon ton budget et ta tolérance à la latence (Haiku = rapide et économique, Sonnet 4.6 = meilleure qualité).</li>
				<li><strong>Configure la fréquence</strong> : le module planifie automatiquement via WP Cron la régénération du fichier <code>/llms.txt</code> à la racine de ton site.</li>
				<li><strong>Déclenche manuellement</strong> la première génération depuis le bouton "Générer maintenant" (disponible une fois la clé API configurée). Claude analyse titre, contenu et structure de chaque page/article inclus.</li>
				<li><strong>Vérifie le résultat</strong> en visitant <code><?php echo esc_url( home_url( '/llms.txt' ) ); ?></code> — les LLMs (ChatGPT, Perplexity, Gemini…) pourront désormais mieux indexer et comprendre ton site.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
				Vérifie que ta clé API est configurée dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages → Clés API</a>.
			</p>
			<div style="background:#fff8e1;border-left:4px solid #f59e0b;padding:10px 14px;margin-top:12px;max-width:720px;">
				<strong>Note Phase S3 :</strong> La génération Claude réelle est à porter depuis Pro 1.3.22.
				Pour l'instant, le module enregistre les réglages et prépare la structure — la logique d'appel API sera activée lors du déploiement Phase S3.
			</div>
		</div>
		<?php
	}

	/**
	 * Enrichit le contenu llms.txt basique du Free avec des descriptions IA.
	 * TODO Phase S3 : porter classe-llms.php → ajout descriptions IA pour chaque URL.
	 *
	 * @param string $content Contenu llms.txt généré par le Free.
	 * @param array  $urls    Liste des URLs indexées.
	 * @return string Contenu enrichi (ou contenu original si Claude API non configurée).
	 */
	public function enhance_with_ai( string $content, array $urls ): string {
		$settings = get_option( 'alesta_ai_llms_txt_ai_settings', $this->default_settings() );

		if ( empty( $settings['enabled'] ) ) {
			return $content;
		}

		// TODO Phase S3 : appel Claude API pour enrichir $content avec descriptions sémantiques
		return $content;
	}

	private function default_settings(): array {
		return [
			'enabled'           => false,
			'claude_model'      => 'claude-sonnet-4-6',
			'frequency'         => 'weekly',
			'prompt_template'   => '',
			'include_post_types' => [ 'page', 'post' ],
		];
	}

	private function sanitize_settings( array $input ): array {
		$allowed_models = [
			'claude-haiku-4-5',
			'claude-sonnet-4-5',
			'claude-sonnet-4-6',
		];
		$allowed_frequencies = [ 'manual', 'daily', 'weekly', 'monthly' ];

		$model = isset( $input['claude_model'] ) ? sanitize_text_field( $input['claude_model'] ) : 'claude-sonnet-4-6';
		if ( ! in_array( $model, $allowed_models, true ) ) {
			$model = 'claude-sonnet-4-6';
		}

		$frequency = isset( $input['frequency'] ) ? sanitize_text_field( $input['frequency'] ) : 'weekly';
		if ( ! in_array( $frequency, $allowed_frequencies, true ) ) {
			$frequency = 'weekly';
		}

		$raw_types    = isset( $input['include_post_types'] ) && is_array( $input['include_post_types'] )
			? $input['include_post_types']
			: [];
		$allowed_types = [ 'page', 'post' ];
		$post_types   = array_values( array_intersect( array_map( 'sanitize_key', $raw_types ), $allowed_types ) );

		return [
			'enabled'            => ! empty( $input['enabled'] ),
			'claude_model'       => $model,
			'frequency'          => $frequency,
			'prompt_template'    => isset( $input['prompt_template'] ) ? sanitize_textarea_field( $input['prompt_template'] ) : '',
			'include_post_types' => $post_types,
		];
	}
}
