<?php
/**
 * Alesta AI Pro — Améliorer texte IA
 *
 * Sidebar Gutenberg avec actions Améliorer/Simplifier/Résumer/Étendre.
 * À porter en S3 Phase 2 depuis Pro 1.3.22 (~600 lignes).
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Content;

use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class ImproveModule {

	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-improve'] = [
				'label'       => 'Améliorer texte IA',
				'category'    => 'content',
				'description' => 'Bouton dans l\'éditeur Gutenberg pour réécrire/simplifier/améliorer un paragraphe',
			];
			return $f;
		} );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_plugin' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
	}

	// -------------------------------------------------------------------------
	// Admin page
	// -------------------------------------------------------------------------

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Améliorer texte IA',
			'Améliorer texte IA',
			'manage_alesta_ai',
			'alesta-ai-improve',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		$settings = get_option( 'alesta_ai_improve_settings', $this->default_settings() );

		if (
			isset( $_POST['alesta_ai_improve_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_improve_nonce'] ) ), 'save_improve_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_improve_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}

		$claude_model_options = [
			'claude-sonnet-4-5'   => 'Claude Sonnet 4.5 (recommandé)',
			'claude-haiku-4-5'    => 'Claude Haiku 4.5 (rapide)',
			'claude-opus-4-5'     => 'Claude Opus 4.5 (puissant)',
		];

		$action_options = [
			'improve'   => 'Améliorer — reformuler le texte de manière plus claire et percutante',
			'simplify'  => 'Simplifier — réécrire en langage simple et direct',
			'summarize' => 'Résumer — condenser en points essentiels',
			'expand'    => 'Étendre — développer avec plus de détails et d\'exemples',
		];
		?>
		<div class="wrap">
			<h1>
				Améliorer texte IA
				<span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span>
			</h1>
			<p class="description" style="max-width:720px;">
				Sidebar Gutenberg avec actions <strong>Améliorer / Simplifier / Résumer / Étendre</strong>.
				Sélectionne un bloc de texte dans l'éditeur WordPress et laisse Claude le transformer en un clic.
			</p>
			<hr/>

			<form method="post">
				<?php wp_nonce_field( 'save_improve_settings', 'alesta_ai_improve_nonce' ); ?>
				<table class="form-table">
					<tbody>

						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1"
										<?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Active la sidebar Gutenberg "Améliorer texte IA"
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php foreach ( $claude_model_options as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"
											<?php selected( $settings['claude_model'], $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Modèle utilisé pour toutes les actions de ce module.</p>
							</td>
						</tr>

						<tr>
							<th scope="row">Actions disponibles</th>
							<td>
								<?php foreach ( $action_options as $key => $label ) : ?>
									<label style="display:block;margin-bottom:6px;">
										<input type="checkbox"
											name="actions[]"
											value="<?php echo esc_attr( $key ); ?>"
											<?php checked( in_array( $key, $settings['actions'], true ) ); ?> />
										<strong><?php echo esc_html( ucfirst( $key ) ); ?></strong>
										— <?php echo esc_html( substr( $label, strpos( $label, '—' ) + 3 ) ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description">Coche les actions à afficher dans la sidebar Gutenberg.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="max_tokens">Tokens max (réponse)</label></th>
							<td>
								<input type="number" name="max_tokens" id="max_tokens"
									value="<?php echo esc_attr( $settings['max_tokens'] ); ?>"
									min="128" max="4096" step="64" style="width:120px;" />
								<p class="description">Limite de tokens pour la réponse Claude (128–4096). Défaut : 1024.</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr/>
			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Active le module</strong> via la case ci-dessus et enregistre.</li>
				<li>Ouvre un article ou une page dans l'<strong>éditeur Gutenberg</strong> — une nouvelle sidebar "Alesta AI" apparaît dans le panneau droit.</li>
				<li><strong>Sélectionne un bloc de texte</strong> (paragraphe, titre, liste…) dans l'éditeur.</li>
				<li>Clique sur l'une des actions disponibles : <strong>Améliorer, Simplifier, Résumer</strong> ou <strong>Étendre</strong> — Claude retourne sa suggestion en quelques secondes.</li>
				<li>Accepte la suggestion pour remplacer le bloc d'origine, ou rejette-la pour conserver ton texte initial.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
				Vérifie que ta clé API est configurée dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages &rarr; Clés API</a>.
			</p>
			<p style="color:#777;font-style:italic;">
				Note : l'intégration Gutenberg complète sera activée en Phase S3. La REST route <code>/alesta-ai-pro/v1/improve</code> est déjà enregistrée.
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Settings helpers
	// -------------------------------------------------------------------------

	private function default_settings(): array {
		return [
			'enabled'      => false,
			'claude_model' => 'claude-sonnet-4-5',
			'actions'      => [ 'improve', 'simplify', 'summarize', 'expand' ],
			'max_tokens'   => 1024,
		];
	}

	private function sanitize_settings( array $input ): array {
		$allowed_models  = [ 'claude-sonnet-4-5', 'claude-haiku-4-5', 'claude-opus-4-5' ];
		$allowed_actions = [ 'improve', 'simplify', 'summarize', 'expand' ];

		$raw_actions = isset( $input['actions'] ) && is_array( $input['actions'] )
			? $input['actions']
			: [];

		return [
			'enabled'      => ! empty( $input['enabled'] ),
			'claude_model' => in_array( $input['claude_model'] ?? '', $allowed_models, true )
				? $input['claude_model']
				: 'claude-sonnet-4-5',
			'actions'      => array_values( array_intersect( $raw_actions, $allowed_actions ) ),
			'max_tokens'   => min( 4096, max( 128, (int) ( $input['max_tokens'] ?? 1024 ) ) ),
		];
	}

	// -------------------------------------------------------------------------
	// Block editor / REST (Phase S3)
	// -------------------------------------------------------------------------

	public function enqueue_block_plugin(): void {
		if ( ! LicenseManager::instance()->is_valid() ) {
			return;
		}
		$settings = get_option( 'alesta_ai_improve_settings', $this->default_settings() );
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		// TODO Phase S3 : enqueue le JS qui ajoute le sidebar Alesta AI dans Gutenberg
		// avec actions "Améliorer", "Simplifier", "Résumer", "Étendre"
	}

	public function register_rest(): void {
		register_rest_route( 'alesta-ai-pro/v1', '/improve', [
			'methods'             => 'POST',
			'callback'            => fn() => new \WP_REST_Response(
				[ 'todo' => 'Claude API à configurer — Phase S3' ],
				200
			),
			'permission_callback' => fn() => current_user_can( 'manage_alesta_ai' ),
		] );
	}
}
