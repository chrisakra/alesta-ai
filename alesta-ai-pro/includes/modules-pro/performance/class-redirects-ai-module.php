<?php
/**
 * Alesta AI Pro — Redirections IA
 *
 * Suggère redirect 301 cible quand 404 détecté via Claude (Anthropic).
 * Le module Free performance/redirects-core gère les redirections 301/302 manuelles.
 * Ce module Pro ajoute la suggestion automatique par IA.
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Performance
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Performance;

use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class RedirectsAIModule {

	public function __construct() {
		add_filter( 'alesta_ai/pro/features', [ $this, 'register_feature' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'alesta_ai/admin/performance-redirects/actions', [ $this, 'render_inject_button' ] );
	}

	/**
	 * Enregistre la feature dans le registre Pro.
	 */
	public function register_feature( array $f ): array {
		$f['performance-redirects-ai'] = [
			'label'       => 'Redirections IA',
			'category'    => 'performance',
			'icon'        => 'redo',
			'description' => 'Suggère automatiquement la meilleure cible de redirect 301 quand un 404 est détecté',
		];
		return $f;
	}

	/**
	 * Ajoute la sous-page dans le menu WP Admin.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Redirections IA',
			'Redirections IA',
			'manage_alesta_ai',
			'alesta-ai-redirects-ai',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Bouton d'injection rapide affiché dans la page Free des redirections manuelles.
	 */
	public function render_inject_button( array $ctx ): void {
		if ( ! LicenseManager::instance()->is_valid() ) {
			return;
		}
		echo '<button type="button" class="button button-primary">&#10024; Suggérer redirect via IA</button>';
	}

	/**
	 * Rendu de la page admin du module.
	 */
	public function render_page(): void {
		$settings = get_option( 'alesta_ai_redirects_ai_settings', $this->default_settings() );

		if (
			isset( $_POST['alesta_ai_redirects_ai_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_redirects_ai_nonce'] ) ), 'save_redirects_ai_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_redirects_ai_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>R&#233;glages enregistr&#233;s.</p></div>';
		}

		$claude_models = [
			'claude-haiku-4-5'  => 'Claude Haiku 4.5 (rapide, &#233;conomique)',
			'claude-sonnet-4-5' => 'Claude Sonnet 4.5 (&#233;quilibr&#233;)',
			'claude-opus-4-5'   => 'Claude Opus 4.5 (pr&#233;cis, plus lent)',
		];

		$selected_model          = isset( $settings['claude_model'] ) ? $settings['claude_model'] : 'claude-haiku-4-5';
		$max_suggestions         = isset( $settings['max_suggestions'] ) ? (int) $settings['max_suggestions'] : 3;
		$prompt_extra            = isset( $settings['prompt_extra'] ) ? $settings['prompt_extra'] : '';
		$auto_create_redirect    = ! empty( $settings['auto_create_redirect'] );
		$enabled                 = ! empty( $settings['enabled'] );
		?>
		<div class="wrap">
			<h1>
				Redirections IA
				<span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span>
			</h1>
			<p class="description" style="max-width:720px;">
				D&#232;s qu&rsquo;une erreur 404 est d&#233;tect&#233;e, Claude analyse l&rsquo;URL cass&#233;e et le contenu de votre site
				pour proposer la meilleure cible de redirection 301. Vous validez en un clic.
			</p>
			<hr/>

			<form method="post">
				<?php wp_nonce_field( 'save_redirects_ai_settings', 'alesta_ai_redirects_ai_nonce' ); ?>
				<table class="form-table">
					<tbody>

						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( $enabled ); ?> />
									Active la d&#233;tection 404 et la suggestion IA de redirect 301
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Mod&#232;le Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php foreach ( $claude_models as $model_id => $model_label ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $selected_model, $model_id ); ?>>
											<?php echo $model_label; ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Haiku est recommand&#233; pour minimiser les co&#251;ts API sur chaque 404.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="max_suggestions">Nombre de suggestions</label></th>
							<td>
								<input
									type="number"
									name="max_suggestions"
									id="max_suggestions"
									value="<?php echo esc_attr( $max_suggestions ); ?>"
									min="1"
									max="10"
									style="width:60px;"
								/>
								<p class="description">Nombre maximal de cibles propos&#233;es par Claude pour chaque 404 (1&#8211;10).</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="auto_create_redirect">Cr&#233;ation automatique</label></th>
							<td>
								<label>
									<input type="checkbox" name="auto_create_redirect" id="auto_create_redirect" value="1" <?php checked( $auto_create_redirect ); ?> />
									Cr&#233;er automatiquement la redirection 301 si Claude est certain &#224; plus de 90 %
								</label>
								<p class="description">
									Laissez d&#233;coch&#233; pour garder un contr&#244;le manuel (recommand&#233;).
									Le module Free &laquo;&nbsp;Redirections Core&nbsp;&raquo; doit &#234;tre activ&#233; pour la cr&#233;ation effective.
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="prompt_extra">Instructions suppl&#233;mentaires (optionnel)</label></th>
							<td>
								<textarea
									name="prompt_extra"
									id="prompt_extra"
									rows="4"
									cols="50"
									class="large-text"
									placeholder="Ex : Pr&#233;f&#233;re toujours rediriger vers la cat&#233;gorie parente si la page exacte n&rsquo;existe plus."
								><?php echo esc_textarea( $prompt_extra ); ?></textarea>
								<p class="description">
									Contexte m&#233;tier ajout&#233; au prompt Claude pour affiner les suggestions
									(langue du site, politique de redirections, sections &#224; privil&#233;gier, etc.).
								</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button( 'Enregistrer les r&#233;glages' ); ?>
			</form>

			<hr/>

			<h2>Comment &#231;a marche</h2>
			<ol>
				<li><strong>D&#233;tection 404</strong> &mdash; Chaque erreur 404 sur votre site est intercept&#233;e par le module (hook <code>wp</code> + <code>is_404()</code>). L&rsquo;URL cass&#233;e est enregistr&#233;e en base avec horodatage et r&#233;f&#233;rent.</li>
				<li><strong>Analyse IA</strong> &mdash; Claude re&#231;oit l&rsquo;URL cass&#233;e, le titre/slug des pages existantes et vos instructions personnalis&#233;es, puis propose 1 &#224; <?php echo esc_html( $max_suggestions ); ?> cibles de redirect 301 class&#233;es par pertinence.</li>
				<li><strong>Validation manuelle</strong> &mdash; Les suggestions apparaissent dans <em>Alesta AI &#8594; Redirections IA</em>. Un clic sur &#171;&nbsp;Appliquer&nbsp;&#187; cr&#233;e la r&#232;gle 301 via le module Free Redirections Core.</li>
				<li><strong>Cr&#233;ation automatique (optionnel)</strong> &mdash; Si vous activez l&rsquo;option ci-dessus et que Claude est certain &#224; &gt;&#8239;90&#8239;%, la redirection est cr&#233;&#233;e sans intervention humaine.</li>
				<li><strong>Suivi</strong> &mdash; Un journal liste les 404 r&#233;cents, les suggestions g&#233;n&#233;r&#233;es et l&rsquo;&#233;tat (en attente / appliqu&#233; / ignor&#233;).</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
				V&#233;rifie que ta cl&#233; API est configur&#233;e dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">R&#233;glages &#8594; Cl&#233;s API</a>.
			</p>
			<p style="background:#fffbeb;border-left:4px solid #f59e0b;padding:10px 14px;max-width:720px;">
				<strong>Note :</strong> La logique d&rsquo;appel Claude (d&#233;tection 404 en temps r&#233;el, stockage des suggestions, table de journal)
				sera activ&#233;e en Phase S3. Cette page de r&#233;glages est d&#233;j&#224; fonctionnelle et vos pr&#233;f&#233;rences sont sauvegard&#233;es.
			</p>
		</div>
		<?php
	}

	/**
	 * Valeurs par d&#233;faut des settings.
	 */
	private function default_settings(): array {
		return [
			'enabled'             => false,
			'claude_model'        => 'claude-haiku-4-5',
			'max_suggestions'     => 3,
			'auto_create_redirect' => false,
			'prompt_extra'        => '',
		];
	}

	/**
	 * Sanitisation des donn&#233;es POST.
	 */
	private function sanitize_settings( array $input ): array {
		$allowed_models = [ 'claude-haiku-4-5', 'claude-sonnet-4-5', 'claude-opus-4-5' ];
		$model          = isset( $input['claude_model'] ) ? sanitize_text_field( $input['claude_model'] ) : 'claude-haiku-4-5';

		return [
			'enabled'              => ! empty( $input['enabled'] ),
			'claude_model'         => in_array( $model, $allowed_models, true ) ? $model : 'claude-haiku-4-5',
			'max_suggestions'      => min( 10, max( 1, (int) ( $input['max_suggestions'] ?? 3 ) ) ),
			'auto_create_redirect' => ! empty( $input['auto_create_redirect'] ),
			'prompt_extra'         => isset( $input['prompt_extra'] ) ? sanitize_textarea_field( $input['prompt_extra'] ) : '',
		];
	}
}
