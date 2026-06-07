<?php
/**
 * Alesta AI Pro — Audit perf priorisé IA
 *
 * Claude analyse le rapport Lighthouse du module Free perf-audit-core
 * et propose les 3 actions à plus fort impact pour ce site spécifique.
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Performance
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Performance;

defined( 'ABSPATH' ) || exit;

final class PerfAuditAIModule {

	public function __construct() {
		// Enregistre la feature dans le registre Pro (utilisé par le Free pour les badges)
		add_filter( 'alesta_ai/pro/features', [ $this, 'register_feature' ] );

		// Injecte le bouton IA dans la page d'audit du module Free
		add_action( 'alesta_ai/admin/performance-perf-audit/actions', [ $this, 'render_inject_button' ] );

		// Page admin dédiée
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	/**
	 * Enregistre la feature dans le registre Pro.
	 */
	public function register_feature( array $features ): array {
		$features['performance-audit-ai'] = [
			'label'       => 'Audit perf priorisé IA',
			'category'    => 'performance',
			'icon'        => 'performance',
			'description' => 'Claude analyse ton rapport Lighthouse et propose les 3 actions à plus fort impact pour ce site spécifique.',
		];
		return $features;
	}

	/**
	 * Injecte le bouton IA dans la page d'audit du module Free.
	 */
	public function render_inject_button( array $ctx ): void {
		echo '<button type="button" class="button button-primary">&#x2728; Recommandations priorisées via IA</button>';
		echo '<p class="description" style="margin-top:6px;">Claude API à configurer — <a href="' . esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ) . '">Configurer ma clé API</a></p>';
	}

	/**
	 * Ajoute la sous-page admin dans le menu Alesta AI.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Audit perf priorisé IA',
			'Audit perf priorisé IA',
			'manage_alesta_ai',
			'alesta-ai-perf-audit-ai',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Rendu de la page admin.
	 */
	public function render_page(): void {
		$option_key = 'alesta_ai_perf_audit_ai_settings';
		$nonce_action = 'save_perf_audit_ai_settings';
		$nonce_field  = 'alesta_ai_perf_audit_ai_nonce';

		$settings = get_option( $option_key, $this->default_settings() );

		// Sauvegarde si POST valide
		if (
			isset( $_POST[ $nonce_field ] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( $option_key, $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}

		// Modèles Claude disponibles
		$claude_models = [
			'claude-sonnet-4-5' => 'Claude Sonnet 4.5 (recommandé — équilibre vitesse / qualité)',
			'claude-haiku-4-5'  => 'Claude Haiku 4.5 (rapide, économique)',
			'claude-opus-4-5'   => 'Claude Opus 4.5 (analyse la plus poussée)',
		];

		?>
		<div class="wrap">
			<h1>
				Audit perf priorisé IA
				<span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span>
			</h1>
			<p class="description" style="max-width:720px;">
				Claude analyse le rapport de performance de ton site (Lighthouse / Core Web Vitals)
				et identifie les <strong>3 actions à plus fort impact</strong> pour ce site spécifique,
				classées par gain attendu.
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
									Active l'analyse IA des rapports de performance
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php foreach ( $claude_models as $model_id => $model_label ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>"
											<?php selected( $settings['claude_model'], $model_id ); ?>>
											<?php echo esc_html( $model_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Modèle utilisé pour générer les recommandations.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="max_recommendations">Nombre de recommandations</label></th>
							<td>
								<select name="max_recommendations" id="max_recommendations">
									<option value="3" <?php selected( $settings['max_recommendations'], '3' ); ?>>3 recommandations (défaut)</option>
									<option value="5" <?php selected( $settings['max_recommendations'], '5' ); ?>>5 recommandations</option>
								</select>
								<p class="description">Nombre maximum d'actions proposées par Claude.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="prompt_context">Contexte site (optionnel)</label></th>
							<td>
								<textarea name="prompt_context" id="prompt_context" rows="4"
									class="large-text"
									placeholder="Ex : Site e-commerce WooCommerce, 10 000 visites/mois, hébergé sur VPS OVH 2 vCPU..."
									><?php echo esc_textarea( $settings['prompt_context'] ); ?></textarea>
								<p class="description">
									Informations contextuelles transmises à Claude pour affiner ses recommandations.
									Décris ton type de site, ton hébergeur, ton audience cible.
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
				<li><strong>Active le module</strong> via la case ci-dessus et configure ton modèle Claude préféré.</li>
				<li><strong>Lance un audit</strong> depuis la page <em>Performance &rarr; Audit Core</em> du plugin Free : le score Lighthouse et les métriques Core Web Vitals sont collectés.</li>
				<li><strong>Clique sur "Recommandations priorisées via IA"</strong> dans les résultats d'audit : Claude reçoit ton rapport complet et analyse les goulots d'étranglement spécifiques à ton site.</li>
				<li><strong>Consulte le top 3</strong> des actions classées par gain attendu (ex : activer le cache objet Redis, optimiser tes images WebP, différer le JS non critique).</li>
				<li><strong>Applique les recommandations</strong> dans l'ordre proposé et relance un audit pour mesurer l'amélioration.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
				Vérifie que ta clé API est configurée dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages &rarr; Clés API</a>.
			</p>
			<p>
				<em>Note :</em> L'analyse Claude API n'est pas encore active — la fonctionnalité sera disponible
				dès la Phase S3. En attendant, la page de configuration est prête et tes réglages seront conservés.
			</p>
		</div>
		<?php
	}

	/**
	 * Valeurs par défaut des settings.
	 */
	private function default_settings(): array {
		return [
			'enabled'             => false,
			'claude_model'        => 'claude-sonnet-4-5',
			'max_recommendations' => '3',
			'prompt_context'      => '',
		];
	}

	/**
	 * Sanitization des settings POST.
	 */
	private function sanitize_settings( array $input ): array {
		$allowed_models = [ 'claude-sonnet-4-5', 'claude-haiku-4-5', 'claude-opus-4-5' ];

		$model = isset( $input['claude_model'] ) ? sanitize_text_field( $input['claude_model'] ) : 'claude-sonnet-4-5';
		if ( ! in_array( $model, $allowed_models, true ) ) {
			$model = 'claude-sonnet-4-5';
		}

		$max_rec = isset( $input['max_recommendations'] ) ? sanitize_text_field( $input['max_recommendations'] ) : '3';
		if ( ! in_array( $max_rec, [ '3', '5' ], true ) ) {
			$max_rec = '3';
		}

		return [
			'enabled'             => ! empty( $input['enabled'] ),
			'claude_model'        => $model,
			'max_recommendations' => $max_rec,
			'prompt_context'      => isset( $input['prompt_context'] )
				? sanitize_textarea_field( wp_unslash( $input['prompt_context'] ) )
				: '',
		];
	}
}
