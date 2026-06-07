<?php
/**
 * Alesta AI Pro — Tags AI
 *
 * Tags et catégories auto via Claude
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Content;

defined( 'ABSPATH' ) || exit;

final class TagsAIModule {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Tags AI',
			'Tags AI',
			'manage_alesta_ai',
			'alesta-ai-tags-ai',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		$settings = get_option( 'alesta_ai_tags_ai_settings', $this->default_settings() );

		if ( isset( $_POST['alesta_ai_tags_ai_nonce'] ) &&
			 wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_tags_ai_nonce'] ) ), 'save_tags_ai_settings' ) ) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_tags_ai_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>Tags AI <span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span></h1>
			<p class="description" style="max-width:720px;">Génère automatiquement des tags et catégories pertinents pour vos articles WordPress grâce à Claude (Anthropic). Optimisez votre référencement et la navigation de votre site sans effort.</p>
			<hr/>
			<form method="post">
				<?php wp_nonce_field( 'save_tags_ai_settings', 'alesta_ai_tags_ai_nonce' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Active la génération automatique de tags et catégories via Claude
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<option value="claude-haiku-4-5" <?php selected( $settings['claude_model'], 'claude-haiku-4-5' ); ?>>Claude Haiku 4.5 (rapide, économique)</option>
									<option value="claude-sonnet-4-5" <?php selected( $settings['claude_model'], 'claude-sonnet-4-5' ); ?>>Claude Sonnet 4.5 (équilibré)</option>
									<option value="claude-opus-4-5" <?php selected( $settings['claude_model'], 'claude-opus-4-5' ); ?>>Claude Opus 4.5 (puissant)</option>
								</select>
								<p class="description">Choisissez le modèle Claude utilisé pour analyser le contenu et suggérer les tags.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="max_tags">Nombre maximum de tags</label></th>
							<td>
								<input type="number" name="max_tags" id="max_tags" value="<?php echo esc_attr( $settings['max_tags'] ); ?>" min="1" max="20" style="width:80px;" />
								<p class="description">Nombre de tags générés par article (entre 1 et 20). Recommandé : 5.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="auto_assign">Assignation automatique</label></th>
							<td>
								<label>
									<input type="checkbox" name="auto_assign" id="auto_assign" value="1" <?php checked( ! empty( $settings['auto_assign'] ) ); ?> />
									Assigner automatiquement les tags à la publication de l'article
								</label>
								<p class="description">Si désactivé, les suggestions apparaissent dans la metabox de l'article pour validation manuelle.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="prompt_template">Prompt personnalisé</label></th>
							<td>
								<textarea name="prompt_template" id="prompt_template" rows="5" cols="60" class="large-text"><?php echo esc_textarea( $settings['prompt_template'] ); ?></textarea>
								<p class="description">
									Personnalisez l'instruction envoyée à Claude pour adapter les tags à votre ligne éditoriale.<br/>
									Utilisez <code>{title}</code> et <code>{content}</code> comme variables dynamiques.
								</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr/>
			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Activez le module</strong> via la case ci-dessus, puis enregistrez.</li>
				<li><strong>Rédigez ou ouvrez un article</strong> WordPress : une metabox "Tags AI" apparaît dans l'éditeur.</li>
				<li><strong>Cliquez sur "Générer les tags"</strong> dans la metabox : Claude analyse le titre et le contenu de l'article, puis propose une liste de tags et de catégories pertinents.</li>
				<li><strong>Validez ou ajustez</strong> les suggestions directement dans la metabox avant de publier (si l'assignation automatique est désactivée).</li>
				<li><strong>Publiez l'article</strong> : les tags retenus sont automatiquement associés, améliorant la navigation et le SEO de votre site.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
			Vérifie que ta clé API est configurée dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages &rarr; Clés API</a>.</p>
			<div class="notice notice-info inline" style="margin:12px 0;">
				<p><strong>Note :</strong> L'appel Claude API sera disponible dans une prochaine version. Configurez dès maintenant vos réglages pour être prêt au lancement.</p>
			</div>
		</div>
		<?php
	}

	private function default_settings(): array {
		return [
			'enabled'         => false,
			'claude_model'    => 'claude-haiku-4-5',
			'max_tags'        => 5,
			'auto_assign'     => false,
			'prompt_template' => "Analyse le contenu de cet article WordPress et propose {max_tags} tags SEO pertinents en français.\n\nTitre : {title}\n\nContenu : {content}\n\nRéponds uniquement avec une liste de tags séparés par des virgules, sans explication.",
		];
	}

	private function sanitize_settings( array $input ): array {
		return [
			'enabled'         => ! empty( $input['enabled'] ),
			'claude_model'    => in_array( $input['claude_model'] ?? '', [ 'claude-haiku-4-5', 'claude-sonnet-4-5', 'claude-opus-4-5' ], true )
				? $input['claude_model']
				: 'claude-haiku-4-5',
			'max_tags'        => max( 1, min( 20, intval( $input['max_tags'] ?? 5 ) ) ),
			'auto_assign'     => ! empty( $input['auto_assign'] ),
			'prompt_template' => sanitize_textarea_field( wp_unslash( $input['prompt_template'] ?? '' ) ),
		];
	}
}
