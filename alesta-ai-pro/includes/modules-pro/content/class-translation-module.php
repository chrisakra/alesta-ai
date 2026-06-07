<?php
/**
 * Alesta AI Pro — Traduction 20 langues
 *
 * Traduction automatique posts/pages via Claude (Solo: 5 langues, Pro/Agency: 20 langues, Unlimited: illimite).
 *
 * NOTE : Pro 1.3.22 appelait wp_remote_post Anthropic directement.
 *        A uniformiser via ExtensionsAPI::get_ai_provider('claude') lors du portage phase 2.
 *
 * Tier : pro
 *
 * @package AlestaAIPro\Modules\Content
 * @since   2.0.14
 */

namespace AlestaAIPro\Modules\Content;

use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class TranslationModule {

	/** Nombre max de langues par tier. */
	private const LANG_LIMITS = [
		'AAP_SOLO'      => 5,
		'AAP_PRO'       => 20,
		'AAP_AGENCY'    => 20,
		'AAP_FOUNDERS'  => 20,
		'AAP_UNLIMITED' => 9999,
	];

	/** 20 langues cibles disponibles (code ISO 639-1 => libelle). */
	private const AVAILABLE_LANGS = [
		'fr' => 'Francais',
		'en' => 'Anglais',
		'es' => 'Espagnol',
		'de' => 'Allemand',
		'it' => 'Italien',
		'pt' => 'Portugais',
		'nl' => 'Neerlandais',
		'pl' => 'Polonais',
		'ru' => 'Russe',
		'zh' => 'Chinois simplifie',
		'ja' => 'Japonais',
		'ko' => 'Coreen',
		'ar' => 'Arabe',
		'tr' => 'Turc',
		'sv' => 'Suedois',
		'da' => 'Danois',
		'fi' => 'Finnois',
		'nb' => 'Norvegien',
		'cs' => 'Tcheque',
		'ro' => 'Roumain',
	];

	public function __construct() {
		add_filter( 'alesta_ai/pro/features', [ $this, 'register_feature' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	/** Enregistre la feature dans le registre Pro. */
	public function register_feature( array $f ): array {
		$f['content-translation'] = [
			'label'       => 'Traduction 20 langues',
			'category'    => 'content',
			'icon'        => 'translation',
			'description' => 'Traduction automatique posts/pages via Claude, jusqu\'a 20 langues',
		];
		return $f;
	}

	/** Meta box sur post/page (bouton rapide). */
	public function add_metabox(): void {
		if ( ! LicenseManager::instance()->is_valid() ) {
			return;
		}
		add_meta_box(
			'alesta-ai-translation',
			'Traduire (Alesta AI)',
			static function () {
				echo '<button type="button" class="button">Traduire ce contenu</button>';
			},
			[ 'post', 'page' ],
			'side'
		);
	}

	/** Ajoute la sous-page dans le menu admin Alesta AI. */
	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Traduction 20 langues',
			'Traduction 20 langues',
			'manage_alesta_ai',
			'alesta-ai-translation',
			[ $this, 'render_page' ]
		);
	}

	/** Affiche la page de reglages du module. */
	public function render_page(): void {
		$settings = get_option( 'alesta_ai_translation_settings', $this->default_settings() );

		if (
			isset( $_POST['alesta_ai_translation_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alesta_ai_translation_nonce'] ) ), 'save_translation_settings' )
		) {
			$settings = $this->sanitize_settings( $_POST );
			update_option( 'alesta_ai_translation_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>Reglages enregistres.</p></div>';
		}

		// Determine le quota de langues selon la licence active.
		$tier       = LicenseManager::instance()->is_valid() ? ( LicenseManager::instance()->get_tier() ?? 'AAP_SOLO' ) : 'AAP_SOLO';
		$lang_limit = self::LANG_LIMITS[ $tier ] ?? 5;
		$all_langs  = self::AVAILABLE_LANGS;
		$available  = array_slice( $all_langs, 0, $lang_limit, true );

		?>
		<div class="wrap">
			<h1>
				Traduction 20 langues
				<span style="display:inline-block;background:#1e3a5f;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;border:1px solid #1e3a5f;">PRO</span>
			</h1>
			<p class="description" style="max-width:720px;">
				Traduction automatique de vos posts et pages WordPress via Claude (Anthropic).<br>
				<strong>Quota selon votre offre :</strong> Solo = 5 langues &mdash; Pro / Agency / Founders = 20 langues &mdash; Unlimited = illimite.
				Votre tier actuel (<code><?php echo esc_html( $tier ); ?></code>) vous donne acces a <strong><?php echo esc_html( $lang_limit >= 9999 ? 'toutes les langues' : $lang_limit . ' langue(s)' ); ?></strong>.
			</p>
			<hr/>

			<form method="post">
				<?php wp_nonce_field( 'save_translation_settings', 'alesta_ai_translation_nonce' ); ?>
				<table class="form-table">
					<tbody>

						<tr>
							<th scope="row"><label for="enabled">Activer le module</label></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
									Active la fonctionnalite Traduction 20 langues
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="target_langs">Langues cibles actives</label></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text">Selectionner les langues cibles</legend>
									<?php foreach ( $available as $code => $label ) : ?>
										<label style="display:inline-block;min-width:180px;margin-bottom:4px;">
											<input type="checkbox"
												   name="target_langs[]"
												   value="<?php echo esc_attr( $code ); ?>"
												   <?php checked( in_array( $code, (array) ( $settings['target_langs'] ?? [] ), true ) ); ?> />
											<?php echo esc_html( $label ); ?> (<code><?php echo esc_html( $code ); ?></code>)
										</label>
									<?php endforeach; ?>
								</fieldset>
								<?php if ( count( $all_langs ) > $lang_limit ) : ?>
									<p class="description" style="color:#888;">
										<?php echo esc_html( count( $all_langs ) - $lang_limit ); ?> langue(s) supplementaire(s) disponibles en passant a une offre superieure.
										<a href="https://alesta.ai/pro" target="_blank">Upgrader</a>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="claude_model">Modele Claude</label></th>
							<td>
								<select name="claude_model" id="claude_model">
									<option value="claude-opus-4-5" <?php selected( $settings['claude_model'] ?? '', 'claude-opus-4-5' ); ?>>Claude Opus 4.5 (recommande)</option>
									<option value="claude-sonnet-4-5" <?php selected( $settings['claude_model'] ?? '', 'claude-sonnet-4-5' ); ?>>Claude Sonnet 4.5 (plus rapide)</option>
									<option value="claude-haiku-4-5" <?php selected( $settings['claude_model'] ?? '', 'claude-haiku-4-5' ); ?>>Claude Haiku 4.5 (economique)</option>
								</select>
								<p class="description">Opus offre la meilleure qualite de traduction; Haiku est le plus rapide et economique.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="preserve_html">Conserver le HTML</label></th>
							<td>
								<label>
									<input type="checkbox" name="preserve_html" id="preserve_html" value="1" <?php checked( ! empty( $settings['preserve_html'] ) ); ?> />
									Preserves les balises HTML, shortcodes et blocs Gutenberg lors de la traduction
								</label>
								<p class="description">Recommande pour la majorite des cas. A desactiver uniquement si vous traduisez du texte brut.</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="custom_prompt_suffix">Instructions supplementaires (optionnel)</label></th>
							<td>
								<textarea name="custom_prompt_suffix" id="custom_prompt_suffix" rows="3" cols="60"
										  class="large-text"><?php echo esc_textarea( $settings['custom_prompt_suffix'] ?? '' ); ?></textarea>
								<p class="description">
									Ajoutez des consignes specifiques au prompt Claude (ex: "utilise un ton formel", "preserve les termes techniques en anglais").
									Laissez vide pour utiliser le prompt par defaut.
								</p>
							</td>
						</tr>

					</tbody>
				</table>
				<?php submit_button( 'Enregistrer les reglages' ); ?>
			</form>

			<hr/>

			<h2>Comment ca marche</h2>
			<ol>
				<li><strong>Activez le module</strong> via la case ci-dessus et selectionnez les langues cibles souhaitees.</li>
				<li><strong>Ouvrez un article ou une page</strong> dans l'editeur WordPress : un bouton "Traduire ce contenu" apparait dans la colonne de droite (meta box).</li>
				<li><strong>Cliquez sur le bouton</strong> : le contenu est envoye a Claude qui retourne la traduction dans la langue choisie, HTML preserve.</li>
				<li><strong>Relisez et ajustez</strong> si necessaire, puis publiez ou mettez a jour le post.</li>
				<li><strong>Repetez</strong> pour chaque langue cible active : chaque traduction peut etre sauvegardee comme un post separe (brouillon) ou remplacer le contenu existant.</li>
			</ol>

			<h2>Statut Claude API</h2>
			<p>
				Ce module utilise <code>Claude</code> (Anthropic) via le provider Pro Addon.
				Verifiez que votre cle API est configuree dans
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>">Reglages &rsaquo; Cles API</a>.
			</p>
			<p>
				<em>Note : la logique d'appel Claude sera couplee a <code>ExtensionsAPI::get_ai_provider('claude')</code> lors du portage Phase 2.
				Pour l'instant, configurez votre cle API et le module sera pret a l'activation complete.</em>
			</p>
		</div>
		<?php
	}

	/** Valeurs par defaut des reglages. */
	private function default_settings(): array {
		return [
			'enabled'              => false,
			'target_langs'         => [ 'en', 'es', 'de', 'it', 'pt' ],
			'claude_model'         => 'claude-opus-4-5',
			'preserve_html'        => true,
			'custom_prompt_suffix' => '',
		];
	}

	/** Sanitise les donnees POST avant sauvegarde. */
	private function sanitize_settings( array $input ): array {
		$allowed_models = [ 'claude-opus-4-5', 'claude-sonnet-4-5', 'claude-haiku-4-5' ];
		$allowed_langs  = array_keys( self::AVAILABLE_LANGS );

		$raw_langs = isset( $input['target_langs'] ) && is_array( $input['target_langs'] )
			? $input['target_langs']
			: [];

		$sanitized_langs = array_values(
			array_filter( $raw_langs, static fn( $c ) => in_array( $c, $allowed_langs, true ) )
		);

		$model = isset( $input['claude_model'] ) && in_array( $input['claude_model'], $allowed_models, true )
			? $input['claude_model']
			: 'claude-opus-4-5';

		return [
			'enabled'              => ! empty( $input['enabled'] ),
			'target_langs'         => $sanitized_langs,
			'claude_model'         => $model,
			'preserve_html'        => ! empty( $input['preserve_html'] ),
			'custom_prompt_suffix' => isset( $input['custom_prompt_suffix'] )
				? sanitize_textarea_field( wp_unslash( $input['custom_prompt_suffix'] ) )
				: '',
		];
	}
}
