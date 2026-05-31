<?php
/**
 * Alesta AI Free — Sitemap XML module
 *
 * Module PILOTE — sert d'exemple pour le pattern de migration des 27 modules
 * Free identifiés par l'audit. Les autres modules suivront exactement la même
 * structure (squelette + register dans includes/modules/index.php).
 *
 * Ce module est FREE pur : génération sitemap XML statique, pas d'IA.
 * Cependant il expose des HOOKS d'injection que le Pro peut consommer (ex.
 * pour ajouter un bouton "✨ Optimiser sitemap via IA" dans l'admin).
 *
 * @package AlestaAI\Modules\Seo
 * @since   2.0.0
 */

namespace AlestaAI\Modules\Seo;

use AlestaAI\Core\ExtensionsAPI;

defined( 'ABSPATH' ) || exit;

final class Sitemap {

	public function __construct() {
		// Désactive le sitemap natif WP (depuis WP 5.5) — on prend la main complète
		add_filter( 'wp_sitemaps_enabled', '__return_false' );

		// Rewrite rules pour servir /sitemap.xml dynamiquement
		add_action( 'init', [ $this, 'register_rewrites' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve_sitemap' ] );

		// Page admin (réglages basiques : exclude post types, fréquence...)
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	// =========================================================================
	// SITEMAP RENDERING
	// =========================================================================

	public function register_rewrites(): void {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?alesta_sitemap=1', 'top' );
		add_rewrite_tag( '%alesta_sitemap%', '([0-9]+)' );
	}

	public function maybe_serve_sitemap(): void {
		if ( get_query_var( 'alesta_sitemap' ) !== '1' ) {
			return;
		}

		// Récupère les posts/pages/taxonomies via filtre (le Pro peut override)
		$urls = apply_filters( 'alesta_ai/sitemap/urls', $this->collect_default_urls() );

		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $urls as $entry ) {
			echo "  <url>\n";
			echo '    <loc>' . esc_url( $entry['loc'] ) . "</loc>\n";
			if ( ! empty( $entry['lastmod'] ) ) {
				echo '    <lastmod>' . esc_html( $entry['lastmod'] ) . "</lastmod>\n";
			}
			if ( ! empty( $entry['priority'] ) ) {
				echo '    <priority>' . esc_html( $entry['priority'] ) . "</priority>\n";
			}
			echo "  </url>\n";
		}
		echo '</urlset>';

		do_action( 'alesta_ai/sitemap/served', count( $urls ) );
		exit;
	}

	private function collect_default_urls(): array {
		$urls = [
			[ 'loc' => home_url( '/' ), 'priority' => '1.0' ],
		];

		// Posts publiés
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 500,
		] );
		foreach ( $posts as $post ) {
			$urls[] = [
				'loc'      => get_permalink( $post ),
				'lastmod'  => mysql2date( 'c', $post->post_modified_gmt ),
				'priority' => '0.7',
			];
		}

		// Pages publiées
		$pages = get_pages( [ 'post_status' => 'publish', 'number' => 200 ] );
		foreach ( $pages as $page ) {
			$urls[] = [
				'loc'      => get_permalink( $page ),
				'lastmod'  => mysql2date( 'c', $page->post_modified_gmt ),
				'priority' => '0.5',
			];
		}

		return $urls;
	}

	// =========================================================================
	// ADMIN PAGE
	// =========================================================================

	public function register_admin_page(): void {
		add_submenu_page(
			'alesta-ai',
			'Sitemap XML',
			'Sitemap XML',
			'manage_alesta_ai',
			'alesta-ai-sitemap',
			[ $this, 'render_admin_page' ]
		);
	}

	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1>Sitemap XML</h1>
			<p>Votre sitemap est servi automatiquement à <code><?php echo esc_html( home_url( '/sitemap.xml' ) ); ?></code></p>

			<p>
				<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank" class="button">
					Voir le sitemap →
				</a>
			</p>

			<?php
			// HOOK D'INJECTION UI : le Pro peut accrocher ici pour proposer
			// "Optimiser le sitemap via IA" (ex. suggérer des priorités basées
			// sur le contenu, exclure les pages de mauvaise qualité, etc.)
			ExtensionsAPI::render_module_actions( 'seo-sitemap', [
				'page'  => 'alesta-ai-sitemap',
				'sitemap_url' => home_url( '/sitemap.xml' ),
			] );

			// Si le Pro n'est PAS actif, on affiche un upsell teaser :
			if ( ! ExtensionsAPI::is_pro_active() ) {
				?>
				<div class="notice notice-info inline" style="margin-top:24px;">
					<p>
						✨ <strong>Avec Alesta AI Pro</strong>, optimisez automatiquement la priorité de vos pages
						selon leur trafic et qualité de contenu.
						<a href="https://www.alesta-ai.com/pricing?utm_source=plugin&utm_campaign=sitemap-upsell">Découvrir Pro →</a>
					</p>
				</div>
				<?php
			}
			?>
		</div>
		<?php
	}
}
