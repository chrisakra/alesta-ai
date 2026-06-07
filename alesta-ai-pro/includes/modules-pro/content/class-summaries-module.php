<?php
/**
 * Alesta AI Pro — Résumés auto
 *
 * TL;DR auto en début de post long, cacheé en post_meta.
 * Génère via Claude un résumé (~2-3 phrases) pour tout post > seuil de mots.
 *
 * Tier : solo
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Content;

use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class SummariesModule {

	public function __construct() {
		add_filter( 'alesta_ai/pro/features', function ( $f ) {
			$f['content-summaries'] = [
				'label'       => 'Résumés auto',
				'category'    => 'content',
				'description' => 'TL;DR généré par Claude au début des posts longs (>1500 mots), cacheé post_meta',
			];
			return $f;
		} );

		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_filter( 'the_content', [ $this, 'maybe_prepend_summary' ], 5 );
	}

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Résumés auto',
			'Résumés auto',
			'manage_alesta_ai',
			'alesta-ai-summaries',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		$settings = get_option( 'alesta_ai_summaries_settings', $this->default_settings() );

		if (
			isset( $_POST['alesta_ai_summaries_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_summaries_nonce'] ) ), 'save_summaries_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_summaries_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>
				Résumés auto
				<span style="display:inline-block;background:#dbeafe;color:#1e3a5f;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">SOLO</span>
			</h1>
			<p class="description" style="max-width:720px;">
				Génère automatiquement un résumé TL;DR (2-3 phrases) via Claude pour chaque article dépassant le seuil de mots configuré.
				Le résumé est cacheé en <code>post_meta</code> (<code>_alesta_ai_summary</code>) et affiché en tête de l'article.
			</p>
			<hr/>

			<form method="post">
				<?php wp_nonce_field( 'save_summaries_settings', 'alesta_ai_summaries_nonce' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1"
										<?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Active la génération et l'affichage des résumés automatiques
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="word_threshold">Seuil de mots</label></th>
							<td>
								<input type="number" name="word_threshold" id="word_threshold" min="300" max="10000" step="50"
									value="<?php echo esc_attr( $settings['word_threshold'] ); ?>"
									class="small-text" />
								<p class="description">
									Nombre minimum de mots d'un article pour déclencher la génération du résumé (défaut : 1 500).
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="claude_model">Modèle Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<?php
									$models = [
										'claude-haiku-4-5'   => 'Claude Haiku 4.5 (rapide, économique)',
										'claude-sonnet-4-5'  => 'Claude Sonnet 4.5 (équilibré)',
										'claude-sonnet-4-6'  => 'Claude Sonnet 4.6 (recommandé)',
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
								<p class="description">Modèle utilisé pour la génération du résumé. Haiku est le plus économique.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="prompt_template">Prompt de résumé</label></th>
							<td>
								<textarea name="prompt_template" id="prompt_template" rows="4"
									class="large-text"><?php echo esc_textarea( $settings['prompt_template'] ); ?></textarea>
								<p class="description">
									Template du prompt envoyé à Claude. Utilisez <code>{content}</code> pour insérer le texte de l'article.
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="post_types">Types de contenu</label></th>
							<td>
								<?php
								$all_types  = get_post_types( [ 'public' => true ], 'objects' );
								$sel_types  = (array) ( $settings['post_types'] ?? [ 'post' ] );
								foreach ( $all_types as $pt ) {
									printf(
										'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="post_types[]" value="%s" %s /> %s <code style="font-size:11px;color:#666;">(%s)</code></label>',
										esc_attr( $pt->name ),
										checked( in_array( $pt->name, $sel_types, true ), true, false ),
										esc_html( $pt->label ),
										esc_html( $pt->name )
									);
								}
								?>
								<p class="description">Types de contenu pour lesquels activer les résumés automatiques.</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>

			<hr/>
			<h2>Comment ça marche</h2>
			<ol>
				<li>
					<strong>Active le module</strong> via la case ci-dessus et enregistre.
				</li>
				<li>
					<strong>Configure le seuil de mots</strong> : seuls les articles dépassant ce seuil déclenchent une génération.
					Pour un blog standard, 1 500 mots est un bon point de départ.
				</li>
				<li>
					<strong>À la première visite</strong> d'un article éligible (front-end), Claude génère le résumé en arrière-plan
					et le stocke dans le <code>post_meta</code> <code>_alesta_ai_summary</code>.
				</li>
				<li>
					<strong>Affichage immédiat</strong> dès la visite suivante : le TL;DR est injecté en tête de l'article,
					dans un bloc stylisé, sans re-génération (cache post_meta).
				</li>
				<li>
					<strong>Régénération manuelle</strong> possible depuis la page d'édition de l'article (méta-box) —
					utile après une mise à jour majeure du contenu.
				</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
				Vérifie que ta clé API est configurée dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">
					Réglages &rarr; Clés API
				</a>.
			</p>
			<p>
				<em>Note : la logique d'appel Claude (génération réelle) sera activée en Phase S3.
				La configuration ci-dessus est déjà persistée et sera utilisée dès activation.</em>
			</p>
		</div>
		<?php
	}

	public function maybe_prepend_summary( string $content ): string {
		$settings = get_option( 'alesta_ai_summaries_settings', $this->default_settings() );

		if ( empty( $settings['enabled'] ) ) {
			return $content;
		}

		if ( ! LicenseManager::instance()->is_valid() ) {
			return $content;
		}

		$post_types = (array) ( $settings['post_types'] ?? [ 'post' ] );
		if ( ! is_singular( $post_types ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		$word_count = str_word_count( wp_strip_all_tags( $content ) );
		if ( $word_count < (int) $settings['word_threshold'] ) {
			return $content;
		}

		$cached = get_post_meta( $post_id, '_alesta_ai_summary', true );
		if ( ! $cached ) {
			// TODO Phase S3 : génération lazy via Claude + stockage post_meta
			return $content;
		}

		$block  = '<div class="alesta-ai-summary" style="background:#f0f7ff;border-left:4px solid #1e3a5f;padding:12px 16px;margin-bottom:24px;border-radius:0 4px 4px 0;">';
		$block .= '<strong style="display:block;margin-bottom:6px;color:#1e3a5f;font-size:.85em;text-transform:uppercase;letter-spacing:.5px;">TL;DR</strong>';
		$block .= '<p style="margin:0;">' . esc_html( $cached ) . '</p>';
		$block .= '</div>';

		return $block . $content;
	}

	private function default_settings(): array {
		return [
			'enabled'         => false,
			'word_threshold'  => 1500,
			'claude_model'    => 'claude-sonnet-4-6',
			'prompt_template' => "Résume l'article suivant en 2 à 3 phrases courtes et percutantes, en français, pour un lecteur pressé (TL;DR). Ne commence pas par « Cet article » ni par « Dans cet article ».\n\n{content}",
			'post_types'      => [ 'post' ],
		];
	}

	private function sanitize_settings( array $input ): array {
		return [
			'enabled'         => ! empty( $input['enabled'] ),
			'word_threshold'  => (int) ( $input['word_threshold'] ?? 1500 ),
			'claude_model'    => sanitize_text_field( $input['claude_model'] ?? 'claude-sonnet-4-6' ),
			'prompt_template' => sanitize_textarea_field( $input['prompt_template'] ?? '' ),
			'post_types'      => array_map( 'sanitize_key', (array) ( $input['post_types'] ?? [ 'post' ] ) ),
		];
	}
}
