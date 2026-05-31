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

	public function __construct() {
		// Injection UI : ajoute le bouton "Suggérer via IA" dans la page admin
		// d'un module Free (ex. sitemap, posts editor metabox SEO, etc.)
		add_action( 'alesta_ai/admin/seo-sitemap/actions', [ $this, 'render_inject_button' ] );

		// REST endpoint pour l'AJAX (POST keywords:generate)
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Déclare cette feature dans la page "Découvrir Pro" du Free
		add_filter( 'alesta_ai/pro/features', [ $this, 'declare_feature' ] );
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
			echo '<p style="margin-top:16px;color:#a00;">';
			echo '<strong>Pro:</strong> Suggestion keywords IA nécessite une licence active. ';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=alesta-ai-license' ) ) . '">Vérifier ma licence</a>';
			echo '</p>';
			return;
		}

		if ( ! $key_ok ) {
			echo '<p style="margin-top:16px;color:#a00;">';
			echo '<strong>Pro:</strong> Configurez votre clé Anthropic dans ';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=alesta-ai-settings' ) ) . '">les réglages</a> ';
			echo 'pour utiliser les fonctionnalités IA. ';
			echo '<small>(BYOK — vous payez Anthropic directement selon votre usage)</small>';
			echo '</p>';
			return;
		}

		// Tout OK : on rend le bouton
		?>
		<div style="margin-top:24px;padding:20px;background:#f3f0ff;border-left:4px solid #8b5cf6;border-radius:4px;">
			<p style="margin:0 0 12px 0;">
				<strong>✨ Alesta AI Pro</strong> — Suggérer des keywords pour les URLs de votre sitemap
			</p>
			<button
				type="button"
				class="button button-primary"
				id="alesta-ai-keywords-generate"
				data-context="<?php echo esc_attr( wp_json_encode( $context ) ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'alesta_ai_keywords' ) ); ?>"
			>
				✨ Suggérer keywords IA
			</button>
			<div id="alesta-ai-keywords-result" style="margin-top:16px;"></div>
		</div>
		<script>
		(function() {
			const btn = document.getElementById('alesta-ai-keywords-generate');
			if (!btn) return;
			btn.addEventListener('click', async () => {
				btn.disabled = true;
				btn.textContent = '⟳ Génération en cours...';
				const result = document.getElementById('alesta-ai-keywords-result');
				try {
					const res = await fetch('<?php echo esc_url_raw( rest_url( 'alesta-ai-pro/v1/keywords/generate' ) ); ?>', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': btn.dataset.nonce,
						},
						body: JSON.stringify({ context: JSON.parse(btn.dataset.context) }),
					});
					const data = await res.json();
					if (data.keywords && data.keywords.length) {
						result.innerHTML = '<p><strong>Keywords suggérés :</strong></p><ul style="margin:0;padding-left:20px;">' +
							data.keywords.map(k => '<li>' + k + '</li>').join('') + '</ul>';
					} else {
						result.innerHTML = '<p style="color:#a00;">' + (data.message || 'Erreur lors de la génération') + '</p>';
					}
				} catch (err) {
					result.innerHTML = '<p style="color:#a00;">Erreur réseau : ' + err.message + '</p>';
				} finally {
					btn.disabled = false;
					btn.textContent = '✨ Suggérer keywords IA';
				}
			});
		})();
		</script>
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
