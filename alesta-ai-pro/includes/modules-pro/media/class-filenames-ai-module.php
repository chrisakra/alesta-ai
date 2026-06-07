<?php
/**
 * Alesta AI Pro — Filenames AI
 *
 * Suggère 3 noms de fichier SEO-friendly par image via Claude (alt + contexte post).
 * Le module Free media/filenames-core renomme manuellement (slugify, sanitize).
 * Ce Pro Addon injecte un bouton "✨ Suggérer 3 noms SEO" via Claude Vision.
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Media
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Media;

use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class FilenamesAIModule {

	public function __construct() {
		// Enregistrement de la feature dans le registre Pro
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['media-filenames-ai'] = [
				'label'       => 'Filenames AI',
				'category'    => 'media',
				'icon'        => 'format-image',
				'description' => 'Suggère 3 noms de fichier SEO-friendly par image via Claude (alt + contexte post)',
			];
			return $f;
		} );

		// Injection du bouton dans l'UI du module Free media/filenames-core
		add_action( 'alesta_ai/admin/media-filenames/actions', [ $this, 'render_inject_button' ] );

		// Page admin dédiée
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	/**
	 * Ajoute la sous-page dans le menu Alesta AI.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Filenames AI',
			'Filenames AI',
			'manage_alesta_ai',
			'alesta-ai-filenames-ai',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Bouton injecté dans le module Free (sidebar Media).
	 */
	public function render_inject_button( array $ctx ): void {
		if ( ! LicenseManager::instance()->is_valid() ) {
			return;
		}
		echo '<button type="button" class="button button-primary">&#10024; Suggérer 3 noms SEO via IA</button>';
		// TODO Phase S3 : porter handler AJAX + appel Claude Vision
	}

	/**
	 * Rendu de la page admin.
	 */
	public function render_page(): void {
		$option_key = 'alesta_ai_filenames_ai_settings';
		$settings   = get_option( $option_key, $this->default_settings() );

		// Sauvegarde si POST valide
		if (
			isset( $_POST['alesta_ai_filenames_ai_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_filenames_ai_nonce'] ) ), 'save_filenames_ai_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( $option_key, $settings );
			echo '<div class="notice notice-success is-dismissible"><p>R&eacute;glages enregistr&eacute;s.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>
				Filenames AI
				<span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span>
			</h1>
			<p class="description" style="max-width:720px;">
				Ce module analyse chaque image via <strong>Claude Vision</strong> et propose jusqu&agrave; 3 noms de fichier SEO-friendly
				bas&eacute;s sur le contenu visuel, l&apos;alt text et le contexte de la page ou publication associ&eacute;e.
			</p>
			<hr/>

			<form method="post">
				<?php wp_nonce_field( 'save_filenames_ai_settings', 'alesta_ai_filenames_ai_nonce' ); ?>
				<table class="form-table">
					<tbody>

						<tr>
							<th scope="row"><label for="filenames_enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox"
										   name="enabled"
										   id="filenames_enabled"
										   value="1"
										   <?php checked( ! empty( $settings['enabled'] ) ); ?>/>
									Active le bouton &laquo;&nbsp;Suggérer 3 noms SEO&nbsp;&raquo; dans la médiathèque
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php
									$models = [
										'claude-opus-4-5'      => 'Claude Opus 4.5 (meilleure qualité)',
										'claude-sonnet-4-5'    => 'Claude Sonnet 4.5 (recommandé)',
										'claude-haiku-4-5'     => 'Claude Haiku 4.5 (rapide / économique)',
									];
									foreach ( $models as $value => $label ) {
										printf(
											'<option value="%s"%s>%s</option>',
											esc_attr( $value ),
											selected( $settings['claude_model'], $value, false ),
											esc_html( $label )
										);
									}
									?>
								</select>
								<p class="description">Utilisez Haiku pour un traitement rapide en lot, Sonnet pour un équilibre qualité/coût.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="suggestions_count">Nombre de suggestions</label></th>
							<td>
								<select name="suggestions_count" id="suggestions_count">
									<?php foreach ( [ 1, 2, 3 ] as $n ) : ?>
										<option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $settings['suggestions_count'], $n ); ?>>
											<?php echo esc_html( $n ); ?> suggestion<?php echo $n > 1 ? 's' : ''; ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Nombre de noms SEO proposés par image (1 à 3).</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="prompt_extra">Instructions supplémentaires (optionnel)</label></th>
							<td>
								<textarea name="prompt_extra"
										  id="prompt_extra"
										  class="large-text"
										  rows="4"
										  placeholder="Ex : Utilise des tirets, inclure la marque &laquo;monsite&raquo;, limiter à 60 caractères..."><?php
									echo esc_textarea( $settings['prompt_extra'] );
								?></textarea>
								<p class="description">Indications libres ajoutées au prompt Claude pour affiner les suggestions (langue, format, longueur, etc.).</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>

			<hr/>
			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Active le module</strong> via la case ci-dessus et enregistre les réglages.</li>
				<li><strong>Ouvre la Médiathèque</strong> (<em>Médias → Bibliothèque</em>) et sélectionne une image.</li>
				<li>Dans le panneau latéral de l&apos;image, clique sur le bouton <strong>&laquo;&nbsp;&#10024; Suggérer 3 noms SEO via IA&nbsp;&raquo;</strong> qui apparaît en bas des actions.</li>
				<li><strong>Claude Vision analyse</strong> l&apos;image (contenu visuel + alt text + contexte de la publication liée) et retourne jusqu&apos;à 3 noms de fichier SEO-friendly en kebab-case.</li>
				<li><strong>Sélectionne le nom</strong> de ton choix : il est automatiquement copié dans le champ nom de fichier, prêt à être appliqué via le module Free Filenames Core.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude Vision</code> (Anthropic) via le provider Pro Addon.<br/>
				V&eacute;rifie que ta cl&eacute; API est configur&eacute;e dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">
					R&eacute;glages &rarr; Cl&eacute;s API
				</a>.
			</p>
			<p style="padding:12px 16px;background:#fff3cd;border-left:4px solid #ffc107;border-radius:2px;max-width:720px;">
				<strong>Note :</strong> L&apos;int&eacute;gration Claude Vision (appel AJAX + handler) est pr&eacute;vue en Phase S3.
				La page de configuration est d&eacute;j&agrave; active &mdash; les suggestions seront disponibles d&egrave;s la prochaine mise &agrave; jour du Pro Addon.
			</p>
		</div>
		<?php
	}

	/**
	 * Valeurs par défaut des réglages.
	 *
	 * @return array<string, mixed>
	 */
	private function default_settings(): array {
		return [
			'enabled'           => false,
			'claude_model'      => 'claude-sonnet-4-5',
			'suggestions_count' => 3,
			'prompt_extra'      => '',
		];
	}

	/**
	 * Sanitize les données POST avant sauvegarde.
	 *
	 * @param  array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( array $input ): array {
		$allowed_models = [ 'claude-opus-4-5', 'claude-sonnet-4-5', 'claude-haiku-4-5' ];
		$model          = sanitize_text_field( wp_unslash( $input['claude_model'] ?? 'claude-sonnet-4-5' ) );
		if ( ! in_array( $model, $allowed_models, true ) ) {
			$model = 'claude-sonnet-4-5';
		}

		$count = (int) ( $input['suggestions_count'] ?? 3 );
		if ( $count < 1 || $count > 3 ) {
			$count = 3;
		}

		return [
			'enabled'           => ! empty( $input['enabled'] ),
			'claude_model'      => $model,
			'suggestions_count' => $count,
			'prompt_extra'      => sanitize_textarea_field( wp_unslash( $input['prompt_extra'] ?? '' ) ),
		];
	}
}
