<?php
/**
 * Alesta AI Pro — Meta AI
 *
 * Génère titles + meta descriptions optimisés via Claude
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Seo;

use AlestaAI\Core\ExtensionsAPI;
use AlestaAI\Core\APIKeyVault;
use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class MetaAIModule {

	public function __construct() {
		// Injecte bouton "✨ Générer Title+Meta IA" dans le metabox SEO du module Free seo-meta-box
		add_action( 'alesta_ai/admin/seo-meta-box/actions', [ $this, 'render_inject_button' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['meta-ai'] = [
				'label'       => 'Meta AI',
				'category'    => 'seo',
				'description' => 'Génère titles + meta descriptions optimisés via Claude',
			];
			return $f;
		} );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Meta AI',
			'Meta AI',
			'manage_alesta_ai',
			'alesta-ai-meta-ai',
			[ $this, 'render_page' ]
		);
	}

	public function render_inject_button( array $ctx ): void {
		if ( ! LicenseManager::instance()->is_valid() || ! APIKeyVault::has( 'anthropic' ) ) {
			return;
		}
		echo '<button class="button button-primary" data-post="' . esc_attr( $ctx['post_id'] ?? 0 ) . '">✨ Générer Title+Meta IA</button>';
	}

	public function register_rest(): void {
		register_rest_route( 'alesta-ai-pro/v1', '/meta/generate', [
			'methods'             => 'POST',
			'callback'            => fn() => new \WP_REST_Response( [ 'todo' => 'porter logique depuis class-meta-module.php' ], 200 ),
			'permission_callback' => fn() => current_user_can( 'manage_alesta_ai' ),
		] );
	}

	public function render_page(): void {
		// Récupère les settings sauvegardés
		$settings = get_option( 'alesta_ai_meta_ai_settings', $this->default_settings() );

		// Si POST → sauvegarde
		if ( isset( $_POST['alesta_ai_meta_ai_nonce'] ) &&
			 wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_meta_ai_nonce'] ) ), 'save_meta_ai_settings' ) ) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_meta_ai_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}

		$models = [
			'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (rapide, économique)',
			'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (recommandé)',
			'claude-opus-4-5'            => 'Claude Opus 4 (qualité maximale)',
		];

		$title_lengths = [
			'50' => '50 caractères (compact)',
			'60' => '60 caractères (standard Google)',
			'70' => '70 caractères (étendu)',
		];
		?>
		<div class="wrap">
			<h1>Meta AI <span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span></h1>
			<p class="description" style="max-width:720px;">Génère automatiquement des <strong>titles SEO</strong> et <strong>meta descriptions</strong> optimisés pour chaque article ou page, via Claude (Anthropic). Le bouton d'injection apparaît directement dans la metabox SEO de l'éditeur.</p>
			<hr/>
			<form method="post">
				<?php wp_nonce_field( 'save_meta_ai_settings', 'alesta_ai_meta_ai_nonce' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Affiche le bouton "Générer Title+Meta IA" dans l'éditeur de posts/pages
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php foreach ( $models as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['claude_model'] ?? 'claude-3-5-sonnet-20241022', $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Modèle utilisé pour la génération. Haiku = plus rapide et moins cher. Sonnet = meilleur équilibre.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="title_max_length">Longueur max du title</label></th>
							<td>
								<select name="title_max_length" id="title_max_length">
									<?php foreach ( $title_lengths as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $settings['title_max_length'] ?? '60' ), $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Google affiche généralement jusqu'à 60 caractères pour le title.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="prompt_extra">Instructions supplémentaires</label></th>
							<td>
								<textarea name="prompt_extra" id="prompt_extra" rows="4" cols="60" class="large-text"><?php echo esc_textarea( $settings['prompt_extra'] ?? '' ); ?></textarea>
								<p class="description">Optionnel. Ajoute des directives au prompt Claude : ton, mots-clés prioritaires, marque à inclure, etc. Exemple : <em>Toujours inclure le nom de marque "Galiance" en fin de title.</em></p>
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
				<li>Ouvre un article ou une page dans l'éditeur WordPress.</li>
				<li>Dans la <strong>metabox SEO Alesta AI</strong> (panneau latéral ou bas de page), clique sur <strong>"✨ Générer Title+Meta IA"</strong>.</li>
				<li>Claude analyse le contenu de ton article et génère un title (&lt;= longueur choisie) et une meta description (~150 caractères) optimisés pour le référencement.</li>
				<li>Les champs Title et Meta Description sont pré-remplis automatiquement — tu peux les relire et ajuster avant de publier.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<?php
			$has_key = class_exists( APIKeyVault::class ) && APIKeyVault::has( 'anthropic' );
			if ( $has_key ) {
				echo '<p style="color:#1a6a2a;"><span style="font-weight:700;">&#10003; Clé API Anthropic détectée.</span> Le module est prêt à générer.</p>';
			} else {
				echo '<p style="color:#8a2a2a;"><span style="font-weight:700;">&#9888; Clé API Anthropic manquante.</span> Configure-la dans <a href="' . esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ) . '">Réglages → Clés API</a> pour activer la génération.</p>';
			}
			?>
			<p>Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon (BYOK).
			Aucune donnée n'est transmise à des tiers hors Anthropic.</p>
		</div>
		<?php
	}

	private function default_settings(): array {
		return [
			'enabled'          => false,
			'claude_model'     => 'claude-3-5-sonnet-20241022',
			'title_max_length' => '60',
			'prompt_extra'     => '',
		];
	}

	private function sanitize_settings( array $input ): array {
		$allowed_models = [
			'claude-3-5-haiku-20241022',
			'claude-3-5-sonnet-20241022',
			'claude-opus-4-5',
		];
		$model = sanitize_text_field( $input['claude_model'] ?? 'claude-3-5-sonnet-20241022' );
		if ( ! in_array( $model, $allowed_models, true ) ) {
			$model = 'claude-3-5-sonnet-20241022';
		}

		$title_length = (int) ( $input['title_max_length'] ?? 60 );
		if ( ! in_array( $title_length, [ 50, 60, 70 ], true ) ) {
			$title_length = 60;
		}

		return [
			'enabled'          => ! empty( $input['enabled'] ),
			'claude_model'     => $model,
			'title_max_length' => (string) $title_length,
			'prompt_extra'     => sanitize_textarea_field( $input['prompt_extra'] ?? '' ),
		];
	}
}
