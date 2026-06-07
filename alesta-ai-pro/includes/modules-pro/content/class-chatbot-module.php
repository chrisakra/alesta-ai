<?php
/**
 * Alesta AI Pro — Chatbot Claude
 *
 * Widget front-office connecté à Claude Haiku — répond aux visiteurs en
 * s'appuyant sur le contenu du site.
 *
 * ⚠ Pro 1.3.22 appelait wp_remote_post('https://api.anthropic.com/...') DIRECTEMENT
 *    (court-circuit du tracking budget). À UNIFORMISER lors du portage : passer
 *    par le provider Claude enregistré (ExtensionsAPI::get_ai_provider('claude')).
 *
 * Tier : pro
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Content;

use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class ChatbotModule {

	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-chatbot'] = [
				'label'       => 'Chatbot Claude',
				'category'    => 'content',
				'icon'        => 'format-chat',
				'description' => 'Widget chatbot frontend qui répond aux visiteurs en s\'appuyant sur ton contenu',
			];
			return $f;
		} );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'wp_footer', [ $this, 'render_widget' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Chatbot Claude',
			'Chatbot Claude',
			'manage_alesta_ai',
			'alesta-ai-chatbot',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		$settings = get_option( 'alesta_ai_chatbot_settings', $this->default_settings() );

		if (
			isset( $_POST['alesta_ai_chatbot_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_chatbot_nonce'] ) ), 'save_chatbot_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_chatbot_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}

		$model_options = [
			'claude-haiku-4-5'  => 'Claude Haiku 4.5 (rapide, économique)',
			'claude-sonnet-4-5' => 'Claude Sonnet 4.5 (équilibré)',
		];
		?>
		<div class="wrap">
			<h1>Chatbot Claude <span style="display:inline-block;background:#1e3a5f;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">PRO</span></h1>
			<p class="description" style="max-width:720px;">
				Widget front-office qui répond aux visiteurs de ton site en temps réel, alimenté par Claude.
				Le chatbot s'appuie sur le contenu de ton site pour formuler des réponses pertinentes.
			</p>
			<hr/>

			<div class="notice notice-info inline" style="max-width:720px;">
				<p><strong>Phase de portage en cours.</strong> La logique temps-réel (Phase S3) sera activée dans une prochaine mise à jour. La configuration ci-dessous est d'ores et déjà sauvegardée et sera prise en compte au lancement.</p>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'save_chatbot_settings', 'alesta_ai_chatbot_nonce' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="enabled">Activer le chatbot</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Affiche le widget chatbot dans le footer de ton site
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="model">Modèle Claude</label></th>
							<td>
								<select name="model" id="model">
									<?php foreach ( $model_options as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['model'], $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Claude Haiku est recommandé pour les chatbots (latence faible, coût réduit).</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="system_prompt">Prompt système</label></th>
							<td>
								<textarea name="system_prompt" id="system_prompt" rows="5" class="large-text" style="max-width:600px;"><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
								<p class="description">
									Décris le rôle du chatbot, le ton à adopter et les sujets sur lesquels il peut répondre.<br/>
									Exemple : <em>Tu es l'assistant du site [Nom du site]. Réponds en français, de façon concise et professionnelle.</em>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="widget_title">Titre du widget</label></th>
							<td>
								<input type="text" name="widget_title" id="widget_title" class="regular-text" value="<?php echo esc_attr( $settings['widget_title'] ); ?>" />
								<p class="description">Texte affiché dans l'en-tête du widget chatbot (ex : "Besoin d'aide ?").</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="max_tokens">Longueur max des réponses</label></th>
							<td>
								<input type="number" name="max_tokens" id="max_tokens" class="small-text" min="100" max="2000" step="50" value="<?php echo esc_attr( $settings['max_tokens'] ); ?>" />
								<span style="margin-left:6px;">tokens</span>
								<p class="description">Entre 100 et 2000 tokens. Valeur recommandée : 500.</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr/>
			<h2>Comment ça marche</h2>
			<ol>
				<li><strong>Active le widget</strong> via la case ci-dessus — il apparaîtra en bas à droite de toutes les pages publiques de ton site.</li>
				<li><strong>Personnalise le prompt système</strong> pour définir le rôle du chatbot et le périmètre des réponses (produits, FAQ, support, etc.).</li>
				<li><strong>Choisis le modèle Claude</strong> selon le compromis vitesse/qualité souhaité — Haiku est recommandé pour minimiser la latence perçue par l'utilisateur.</li>
				<li><strong>Les visiteurs posent leurs questions</strong> directement dans le widget ; le chatbot répond en puisant dans le contexte de ton site via l'API Claude.</li>
				<li><strong>Consulte l'utilisation API</strong> dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-usage' ) ); ?>">Réglages → Utilisation</a> pour suivre ta consommation de tokens.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
			Vérifie que ta clé API est configurée dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Réglages → Clés API</a>.</p>
		</div>
		<?php
	}

	public function render_widget(): void {
		if ( ! LicenseManager::instance()->is_valid() ) {
			return;
		}
		$settings = get_option( 'alesta_ai_chatbot_settings', $this->default_settings() );
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		// TODO Phase S3 : enqueue chatbot.js + render <div id="alesta-chatbot-root"></div>
	}

	public function register_rest(): void {
		register_rest_route( 'alesta-ai-pro/v1', '/chatbot/message', [
			'methods'             => 'POST',
			'callback'            => fn() => new \WP_REST_Response(
				[ 'todo' => 'Porter logique chatbot-module.php + uniformiser via $api->ask()' ],
				200
			),
			'permission_callback' => '__return_true',
		] );
	}

	private function default_settings(): array {
		return [
			'enabled'       => false,
			'model'         => 'claude-haiku-4-5',
			'system_prompt' => 'Tu es l\'assistant du site. Réponds en français, de façon concise et professionnelle, en t\'appuyant uniquement sur le contenu du site.',
			'widget_title'  => 'Besoin d\'aide ?',
			'max_tokens'    => 500,
		];
	}

	private function sanitize_settings( array $input ): array {
		$allowed_models = [ 'claude-haiku-4-5', 'claude-sonnet-4-5' ];
		$model          = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'claude-haiku-4-5';

		return [
			'enabled'       => ! empty( $input['enabled'] ),
			'model'         => in_array( $model, $allowed_models, true ) ? $model : 'claude-haiku-4-5',
			'system_prompt' => isset( $input['system_prompt'] ) ? sanitize_textarea_field( wp_unslash( $input['system_prompt'] ) ) : '',
			'widget_title'  => isset( $input['widget_title'] ) ? sanitize_text_field( wp_unslash( $input['widget_title'] ) ) : 'Besoin d\'aide ?',
			'max_tokens'    => isset( $input['max_tokens'] ) ? (int) min( 2000, max( 100, (int) $input['max_tokens'] ) ) : 500,
		];
	}
}
