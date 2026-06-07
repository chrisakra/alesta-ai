<?php
/**
 * Alesta AI Pro — Rapports PDF mensuels
 *
 * PDF mensuel SEO/perf/sécu + synthèse exécutive IA
 *
 * Tier : pro
 *
 * @package AlestaAIPro\Modules\Reports
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Reports;

defined( 'ABSPATH' ) || exit;

final class PdfReportModule {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Rapports PDF mensuels',
			'Rapports PDF mensuels',
			'manage_alesta_ai',
			'alesta-ai-pdf-reports',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		$settings = get_option( 'alesta_ai_pdf_reports_settings', $this->default_settings() );

		if (
			isset( $_POST['alesta_ai_pdf_reports_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_pdf_reports_nonce'] ) ), 'save_pdf_reports_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_pdf_reports_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}

		$claude_models = [
			'claude-sonnet-4-5'  => 'Claude Sonnet 4.5 (recommandé)',
			'claude-opus-4-5'    => 'Claude Opus 4.5 (plus précis, plus lent)',
			'claude-haiku-4-5'   => 'Claude Haiku 4.5 (rapide, économique)',
		];

		$frequencies = [
			'monthly'  => 'Mensuel (1er du mois)',
			'weekly'   => 'Hebdomadaire (chaque lundi)',
			'manual'   => 'Manuel uniquement',
		];
		?>
		<div class="wrap">
			<h1>Rapports PDF mensuels <span style="display:inline-block;background:#1e3a5f;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">PRO</span></h1>
			<p class="description" style="max-width:720px;">
				Générez automatiquement un rapport PDF complet (SEO, performance, sécurité) avec une synthèse exécutive rédigée par Claude (Anthropic). Envoyez-le directement à vos clients ou téléchargez-le depuis le tableau de bord.
			</p>
			<hr/>

			<form method="post">
				<?php wp_nonce_field( 'save_pdf_reports_settings', 'alesta_ai_pdf_reports_nonce' ); ?>
				<table class="form-table">
					<tbody>

						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Active la génération automatique de rapports PDF mensuels
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model" style="min-width:280px;">
									<?php foreach ( $claude_models as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['claude_model'], $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Choisissez le modèle utilisé pour la synthèse exécutive IA du rapport.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="frequency">Fréquence de génération</label></th>
							<td>
								<select name="frequency" id="frequency" style="min-width:280px;">
									<?php foreach ( $frequencies as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['frequency'], $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Définit à quelle fréquence le rapport est généré automatiquement.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="recipient_email">E-mail destinataire</label></th>
							<td>
								<input
									type="email"
									name="recipient_email"
									id="recipient_email"
									value="<?php echo esc_attr( $settings['recipient_email'] ); ?>"
									class="regular-text"
									placeholder="client@exemple.com"
								/>
								<p class="description">Si renseigné, le rapport PDF est envoyé par e-mail à cette adresse après génération. Laissez vide pour un téléchargement manuel uniquement.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="prompt_template">Template de synthèse IA</label></th>
							<td>
								<textarea
									name="prompt_template"
									id="prompt_template"
									rows="5"
									class="large-text code"
									placeholder="Rédige une synthèse exécutive professionnelle en français à partir des données SEO, performance et sécurité suivantes : {{data}}"
								><?php echo esc_textarea( $settings['prompt_template'] ); ?></textarea>
								<p class="description">
									Personnalisez le prompt envoyé à Claude pour générer la synthèse. Utilisez <code>{{data}}</code> comme emplacement des données du site.
									Laissez vide pour utiliser le prompt par défaut.
								</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>

			<hr/>
			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Activez le module</strong> via la case ci-dessus et choisissez la fréquence de génération (mensuelle, hebdomadaire ou manuelle).</li>
				<li><strong>Configurez votre clé API Anthropic</strong> dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages → Clés API</a> — Claude en a besoin pour rédiger la synthèse exécutive.</li>
				<li><strong>À chaque génération</strong>, Alesta AI collecte automatiquement les données SEO (score, mots-clés, liens), performance (Core Web Vitals, temps de chargement) et sécurité (plugins vulnérables, SSL, mises à jour manquantes) du site.</li>
				<li><strong>Claude rédige une synthèse exécutive</strong> en français, claire et professionnelle, résumant les points forts, les alertes et les recommandations prioritaires pour la période.</li>
				<li><strong>Le rapport PDF</strong> est téléchargeable depuis ce tableau de bord et/ou envoyé automatiquement par e-mail au destinataire configuré ci-dessus.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon pour générer la synthèse exécutive du rapport.<br/>
				Vérifie que ta clé API est configurée dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages → Clés API</a>.
			</p>
			<div class="notice notice-info inline" style="margin-top:0;">
				<p><strong>Note :</strong> La génération réelle du PDF (collecte de données + appel Claude) sera disponible dans une prochaine version. Les réglages ci-dessus sont d'ores et déjà sauvegardés et pris en compte au lancement du module.</p>
			</div>
		</div>
		<?php
	}

	private function default_settings(): array {
		return [
			'enabled'          => false,
			'claude_model'     => 'claude-sonnet-4-5',
			'frequency'        => 'monthly',
			'recipient_email'  => '',
			'prompt_template'  => '',
		];
	}

	private function sanitize_settings( array $input ): array {
		$allowed_models      = [ 'claude-sonnet-4-5', 'claude-opus-4-5', 'claude-haiku-4-5' ];
		$allowed_frequencies = [ 'monthly', 'weekly', 'manual' ];

		$model = isset( $input['claude_model'] ) ? sanitize_text_field( wp_unslash( $input['claude_model'] ) ) : 'claude-sonnet-4-5';
		if ( ! in_array( $model, $allowed_models, true ) ) {
			$model = 'claude-sonnet-4-5';
		}

		$frequency = isset( $input['frequency'] ) ? sanitize_text_field( wp_unslash( $input['frequency'] ) ) : 'monthly';
		if ( ! in_array( $frequency, $allowed_frequencies, true ) ) {
			$frequency = 'monthly';
		}

		return [
			'enabled'          => ! empty( $input['enabled'] ),
			'claude_model'     => $model,
			'frequency'        => $frequency,
			'recipient_email'  => isset( $input['recipient_email'] ) ? sanitize_email( wp_unslash( $input['recipient_email'] ) ) : '',
			'prompt_template'  => isset( $input['prompt_template'] ) ? sanitize_textarea_field( wp_unslash( $input['prompt_template'] ) ) : '',
		];
	}
}
