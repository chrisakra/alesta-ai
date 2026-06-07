<?php
/**
 * Alesta AI Pro — Modération commentaires
 *
 * Modération IA spam/toxique/légitime via Claude
 *
 * Tier : pro
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Content;

defined( 'ABSPATH' ) || exit;

final class CommentsAIModule {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Modération commentaires',
			'Modération commentaires',
			'manage_alesta_ai',
			'alesta-ai-comments-ai',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		// Récupère les settings sauvegardés (option WP)
		$settings = get_option( 'alesta_ai_comments_ai_settings', $this->default_settings() );

		// Si POST → sauvegarde
		if ( isset( $_POST['alesta_ai_comments_ai_nonce'] ) &&
			 wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_comments_ai_nonce'] ) ), 'save_comments_ai_settings' ) ) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_comments_ai_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>Modération commentaires <span style="display:inline-block;background:#1e3a5f;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">PRO</span></h1>
			<p class="description" style="max-width:720px;">Analyse automatique des nouveaux commentaires WordPress par Claude : chaque commentaire est classifié <strong>légitime</strong>, <strong>spam</strong> ou <strong>toxique</strong>, et placé en file d'attente ou rejeté selon votre seuil de confiance.</p>
			<hr/>
			<form method="post">
				<?php wp_nonce_field( 'save_comments_ai_settings', 'alesta_ai_comments_ai_nonce' ); ?>
				<table class="form-table">
					<tbody>

						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Active la modération IA des commentaires
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php
									$models = [
										'claude-haiku-4-5'  => 'Claude Haiku 4.5 (rapide, économique)',
										'claude-sonnet-4-5' => 'Claude Sonnet 4.5 (équilibré)',
										'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (recommandé)',
									];
									foreach ( $models as $value => $label ) {
										printf(
											'<option value="%s" %s>%s</option>',
											esc_attr( $value ),
											selected( $settings['claude_model'], $value, false ),
											esc_html( $label )
										);
									}
									?>
								</select>
								<p class="description">Modèle utilisé pour analyser chaque commentaire. Haiku est plus rapide ; Sonnet est plus précis.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="action_spam">Action si spam détecté</label></th>
							<td>
								<select name="action_spam" id="action_spam">
									<?php
									$actions = [
										'hold'  => 'Mettre en attente (modération manuelle)',
										'trash' => 'Mettre à la corbeille automatiquement',
										'spam'  => 'Marquer comme spam automatiquement',
									];
									foreach ( $actions as $value => $label ) {
										printf(
											'<option value="%s" %s>%s</option>',
											esc_attr( $value ),
											selected( $settings['action_spam'], $value, false ),
											esc_html( $label )
										);
									}
									?>
								</select>
								<p class="description">Comportement appliqué automatiquement quand Claude classe un commentaire comme spam.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="action_toxic">Action si contenu toxique</label></th>
							<td>
								<select name="action_toxic" id="action_toxic">
									<?php
									$actions_toxic = [
										'hold'  => 'Mettre en attente (modération manuelle)',
										'trash' => 'Mettre à la corbeille automatiquement',
										'spam'  => 'Marquer comme spam automatiquement',
									];
									foreach ( $actions_toxic as $value => $label ) {
										printf(
											'<option value="%s" %s>%s</option>',
											esc_attr( $value ),
											selected( $settings['action_toxic'], $value, false ),
											esc_html( $label )
										);
									}
									?>
								</select>
								<p class="description">Comportement appliqué automatiquement quand Claude détecte un commentaire toxique (insultes, harcèlement, discours haineux).</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="confidence_threshold">Seuil de confiance (%)</label></th>
							<td>
								<input
									type="number"
									name="confidence_threshold"
									id="confidence_threshold"
									min="50"
									max="99"
									step="1"
									value="<?php echo esc_attr( $settings['confidence_threshold'] ); ?>"
									class="small-text"
								/>
								<p class="description">En dessous de ce seuil, le commentaire est toujours mis en attente même si Claude a une classification. Recommandé : 75 %.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="custom_prompt">Instructions supplémentaires (prompt)</label></th>
							<td>
								<textarea
									name="custom_prompt"
									id="custom_prompt"
									rows="4"
									cols="60"
									class="large-text"
								><?php echo esc_textarea( $settings['custom_prompt'] ); ?></textarea>
								<p class="description">Contexte métier à transmettre à Claude (ex : "Ce blog traite de finances — tout commentaire promotionnel est du spam."). Laissez vide pour utiliser le prompt par défaut.</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr/>
			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Activation :</strong> Cochez "Activer le module" et sauvegardez. À partir de ce moment, chaque commentaire soumis déclenche une analyse Claude avant d'être publié.</li>
				<li><strong>Classification IA :</strong> Claude lit le contenu du commentaire et le classe en trois catégories — <em>légitime</em>, <em>spam</em> (publicité, liens hors-sujet, robot) ou <em>toxique</em> (insultes, harcèlement, discours haineux) — avec un score de confiance (0–100 %).</li>
				<li><strong>Application automatique :</strong> Si le score dépasse votre seuil de confiance, l'action configurée (attente / corbeille / spam) est appliquée automatiquement. En dessous du seuil, le commentaire part en attente pour modération manuelle.</li>
				<li><strong>Commentaires légitimes :</strong> Les commentaires classifiés légitimes avec un score suffisant suivent le flux WordPress normal (publication directe ou modération selon vos réglages WordPress existants).</li>
				<li><strong>Audit :</strong> Chaque décision est enregistrée dans les métadonnées du commentaire (<code>_alesta_ai_moderation</code>) : classe, score de confiance, modèle utilisé — visible dans la liste des commentaires WP Admin.</li>
			</ol>

			<div class="notice notice-warning inline" style="margin-top:16px;">
				<p><strong>Claude API à configurer.</strong> La connexion à l'API Anthropic n'est pas encore active dans cette version. Configure ta clé dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages → Clés API</a> pour activer l'analyse réelle des commentaires.</p>
			</div>

			<h2>Statut Claude API</h2>
			<p>Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
			Vérifie que ta clé API est configurée dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages → Clés API</a>.</p>
		</div>
		<?php
	}

	private function default_settings(): array {
		return [
			'enabled'              => false,
			'claude_model'         => 'claude-sonnet-4-6',
			'action_spam'          => 'spam',
			'action_toxic'         => 'hold',
			'confidence_threshold' => 75,
			'custom_prompt'        => '',
		];
	}

	private function sanitize_settings( array $input ): array {
		$allowed_models = [
			'claude-haiku-4-5',
			'claude-sonnet-4-5',
			'claude-sonnet-4-6',
		];
		$allowed_actions = [ 'hold', 'trash', 'spam' ];

		$model = isset( $input['claude_model'] ) ? sanitize_text_field( $input['claude_model'] ) : 'claude-sonnet-4-6';
		if ( ! in_array( $model, $allowed_models, true ) ) {
			$model = 'claude-sonnet-4-6';
		}

		$action_spam = isset( $input['action_spam'] ) ? sanitize_text_field( $input['action_spam'] ) : 'spam';
		if ( ! in_array( $action_spam, $allowed_actions, true ) ) {
			$action_spam = 'spam';
		}

		$action_toxic = isset( $input['action_toxic'] ) ? sanitize_text_field( $input['action_toxic'] ) : 'hold';
		if ( ! in_array( $action_toxic, $allowed_actions, true ) ) {
			$action_toxic = 'hold';
		}

		$threshold = isset( $input['confidence_threshold'] ) ? (int) $input['confidence_threshold'] : 75;
		$threshold = max( 50, min( 99, $threshold ) );

		return [
			'enabled'              => ! empty( $input['enabled'] ),
			'claude_model'         => $model,
			'action_spam'          => $action_spam,
			'action_toxic'         => $action_toxic,
			'confidence_threshold' => $threshold,
			'custom_prompt'        => isset( $input['custom_prompt'] ) ? sanitize_textarea_field( $input['custom_prompt'] ) : '',
		];
	}
}
