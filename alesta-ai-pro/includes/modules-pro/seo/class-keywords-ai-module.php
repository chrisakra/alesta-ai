<?php
/**
 * Alesta AI Pro — Keywords AI module (PILOTE)
 *
 * Module PILOTE qui démontre le pattern complet de migration des 17 modules Pro :
 *
 *  1. Auto-register dans le ModuleRegistry via register_pro()
 *  2. Consomme le hook public alesta_ai/ai/providers pour récupérer Claude
 *  3. Injecte un bouton "✨ Suggérer keywords IA" dans le module SEO Free
 *     via le hook alesta_ai/admin/{module}/actions
 *  4. Respecte BYOK : utilise la clé Anthropic stockée par l'user dans le
 *     Vault Free, pas une clé hébergée Alesta
 *  5. Vérifie la license valide avant d'exécuter l'appel IA payant
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.0
 */

namespace AlestaAIPro\Modules\Seo;

use AlestaAI\Core\ExtensionsAPI;
use AlestaAI\Core\APIKeyVault;
use AlestaAI\Core\RateLimiter;
use AlestaAIPro\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

final class KeywordsAIModule {

	/**
	 * Handle commun pour les assets (CSS + JS) du module.
	 * v2.0.4 : extraction des styles/scripts inline vers /assets/ pour
	 * conformité best practices WP (wp_enqueue_*).
	 */
	private const ASSET_HANDLE = 'alesta-ai-pro-keywords';

	public function __construct() {
		// Injection UI : ajoute le bouton "Suggérer via IA" dans la page admin
		// d'un module Free (ex. sitemap, posts editor metabox SEO, etc.)
		add_action( 'alesta_ai/admin/seo-sitemap/actions', [ $this, 'render_inject_button' ] );

		// REST endpoint pour l'AJAX (POST keywords:generate)
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Déclare cette feature dans la page "Découvrir Pro" du Free
		add_filter( 'alesta_ai/pro/features', [ $this, 'declare_feature' ] );

		// v2.0.4 : enregistre les assets une fois côté admin. L'enqueue effectif
		// est déclenché à la volée par render_inject_button() (seulement quand
		// le bouton est réellement rendu, pour 0 impact sur les autres pages).
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	// =========================================================================
	// FEATURE DECLARATION (visible dans le Free même sans Pro actif)
	// =========================================================================

	public function declare_feature( array $features ): array {
		$features['keywords-ai'] = [
			'label'       => 'Keywords IA',
			'description' => 'Génère des keywords sémantiquement liés via Claude pour booster votre SEO.',
			'icon'        => 'admin-network',
			'category'    => 'seo',
		];
		return $features;
	}

	// =========================================================================
	// ASSETS (v2.0.4 — extraction des inline <script>/<style> vers /assets/)
	// =========================================================================

	/**
	 * Enregistre (sans enqueue) le CSS et le JS du module.
	 * L'enqueue conditionnel est fait par render_inject_button().
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$version = defined( 'ALESTA_AI_PRO_VERSION' ) ? ALESTA_AI_PRO_VERSION : false;

		wp_register_style(
			self::ASSET_HANDLE,
			ALESTA_AI_PRO_URL . 'assets/css/keywords-ai.css',
			[],
			$version
		);

		wp_register_script(
			self::ASSET_HANDLE,
			ALESTA_AI_PRO_URL . 'assets/js/keywords-ai.js',
			[],
			$version,
			true
		);
	}

	// =========================================================================
	// INJECTION UI dans la page sitemap Free
	// =========================================================================

	/**
	 * Bouton "✨ Suggérer keywords IA" ajouté dans la page admin Free du sitemap.
	 *
	 * @param array $context Données passées par le module Free (page, sitemap_url, etc.)
	 */
	public function render_inject_button( array $context ): void {
		// Vérifie d'abord que tous les prérequis sont OK
		$license_ok = LicenseManager::instance()->is_valid();
		$key_ok     = APIKeyVault::has( 'anthropic' );

		if ( ! $license_ok ) {
			echo '<p class="alesta-ai-keywords-notice">';
			echo '<strong>Pro:</strong> Suggestion keywords IA nécessite une licence active. ';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=alesta-ai-license' ) ) . '">Vérifier ma licence</a>';
			echo '</p>';
			// On enqueue quand même le CSS pour la notice (mais pas le JS — inutile sans bouton).
			wp_enqueue_style( self::ASSET_HANDLE );
			return;
		}

		if ( ! $key_ok ) {
			echo '<p class="alesta-ai-keywords-notice">';
			echo '<strong>Pro:</strong> Configurez votre clé Anthropic dans ';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=alesta-ai-settings' ) ) . '">les réglages</a> ';
			echo 'pour utiliser les fonctionnalités IA. ';
			echo '<small>(BYOK — vous payez Anthropic directement selon votre usage)</small>';
			echo '</p>';
			wp_enqueue_style( self::ASSET_HANDLE );
			return;
		}

		// v2.0.4 : injection des variables PHP -> JS via wp_localize_script
		// (remplace l'ancienne interpolation inline rest_url()/wp_create_nonce()).
		// On utilise le nonce REST standard 'wp_rest' attendu par l'API REST WP.
		wp_localize_script(
			self::ASSET_HANDLE,
			'AlestaAIKeywords',
			[
				'restUrl' => esc_url_raw( rest_url( 'alesta-ai-pro/v1/keywords/generate' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => [
					'generating'    => '⟳ Génération en cours...',
					'suggested'     => 'Keywords suggérés :',
					'generic_error' => 'Erreur lors de la génération',
					'network_error' => 'Erreur réseau :',
					'button_label'  => '✨ Suggérer keywords IA',
				],
			]
		);
		wp_enqueue_style( self::ASSET_HANDLE );
		wp_enqueue_script( self::ASSET_HANDLE );

		// Tout OK : on rend le bouton (markup sans style inline).
		// Le data-context est sérialisé en JSON puis échappé via esc_attr().
		?>
		<div class="alesta-ai-keywords-panel">
			<p class="alesta-ai-keywords-panel__title">
				<strong>✨ Alesta AI Pro</strong> — Suggérer des keywords pour les URLs de votre sitemap
			</p>
			<button
				type="button"
				class="button button-primary"
				id="alesta-ai-keywords-generate"
				data-context="<?php echo esc_attr( wp_json_encode( $context ) ); ?>"
			>
				✨ Suggérer keywords IA
			</button>
			<div id="alesta-ai-keywords-result" class="alesta-ai-keywords-panel__result"></div>
		</div>
		<?php
	}

	// =========================================================================
	// REST ENDPOINT (génération IA)
	// =========================================================================

	public function register_rest_routes(): void {
		register_rest_route( 'alesta-ai-pro/v1', '/keywords/generate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_generate' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_alesta_ai' );
			},
		] );
	}

	public function handle_generate( \WP_REST_Request $request ): \WP_REST_Response {
		// 1. Re-check license + key (defense in depth)
		if ( ! LicenseManager::instance()->is_valid() ) {
			return new \WP_REST_Response( [ 'error' => 'license_invalid' ], 403 );
		}
		if ( ! APIKeyVault::has( 'anthropic' ) ) {
			return new \WP_REST_Response( [ 'error' => 'no_api_key', 'message' => 'Configurez votre clé Anthropic dans Alesta AI → Réglages.' ], 400 );
		}

		// 2. Rate limit (anti-abuse user qui clique 50x/min)
		$rl = new RateLimiter( 'keywords_ai', 30, HOUR_IN_SECONDS );
		if ( ! $rl->allow( (string) get_current_user_id() ) ) {
			return new \WP_REST_Response( [ 'error' => 'rate_limited', 'message' => '30 générations/heure max' ], 429 );
		}

		// 3. Récupère contexte (post_id, titre, contenu si disponible)
		$context = $request->get_param( 'context' ) ?? [];
		$post_id = isset( $context['post_id'] ) ? (int) $context['post_id'] : 0;
		$site_url = home_url();
		$site_name = get_bloginfo( 'name' );

		// Récupère contenu du post si fourni, sinon utilise homepage
		$content_excerpt = '';
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$content_excerpt = wp_strip_all_tags( wp_trim_words( $post->post_content, 200 ) );
				$site_name = $post->post_title . ' (' . $site_name . ')';
			}
		}
		if ( empty( $content_excerpt ) ) {
			$content_excerpt = wp_strip_all_tags( get_bloginfo( 'description' ) );
		}

		// 4. Appel Claude via le provider
		$providers = ExtensionsAPI::get_ai_providers();
		$claude = $providers['claude'] ?? null;
		if ( ! $claude ) {
			return new \WP_REST_Response( [ 'error' => 'no_provider', 'message' => 'Provider Claude indisponible' ], 500 );
		}

		$prompt = sprintf(
			"Je gère le site WordPress \"%s\" (%s). Voici un extrait de son contenu :\n\n%s\n\nGénère 10 keywords SEO pertinents en français pour ce contenu. Mix mots-clés génériques (recherche large) et long-tail (3-5 mots, intention claire). Privilégie ceux avec bon potentiel de positionnement (volume modéré, concurrence raisonnable).\n\nRetourne UNIQUEMENT un JSON de cette forme exacte :\n{\"keywords\": [\"keyword 1\", \"keyword 2\", ...]}",
			$site_name,
			$site_url,
			substr( $content_excerpt, 0, 1500 ),
		);

		$result = $claude->complete_json( $prompt, [
			'max_tokens'  => 600,
			'temperature' => 0.5,
			'cache_key'   => "keywords_${post_id}_" . md5( $content_excerpt ),
		] );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [
				'error' => $result->get_error_code(),
				'message' => $result->get_error_message(),
			], 500 );
		}

		$keywords = $result['keywords'] ?? [];
		if ( ! is_array( $keywords ) ) {
			return new \WP_REST_Response( [ 'error' => 'invalid_response', 'message' => 'Réponse Claude mal formée' ], 500 );
		}

		return new \WP_REST_Response( [
			'keywords' => array_slice( array_map( 'strval', $keywords ), 0, 10 ),
			'context'  => [ 'post_id' => $post_id, 'site' => $site_name ],
		], 200 );
	}
}
