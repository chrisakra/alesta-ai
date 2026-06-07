<?php
/**
 * Plugin Name:       Alesta AI
 * Plugin URI:        https://alesta-ai.com/
 * Description:       WordPress optimization toolkit — SEO, performance, security, GDPR, reviews and analytics. Foundation for the Alesta AI Pro extension.
 * Version:           2.0.8
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Christian EL DEBS (Alesta Computer)
 * Author URI:        https://www.alesta-computer.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       alesta-ai
 * Domain Path:       /languages
 *
 * @package AlestaAI
 *
 * ==========================================================================
 * ARCHITECTURE — "Pattern Elementor" (depuis v2.0)
 * ==========================================================================
 *
 * Ce plugin FREE est conçu comme un SOCLE auquel le plugin Alesta AI Pro
 * (distribué via GitHub Releases / Galiance Cockpit) vient s'accrocher via hooks :
 *
 *   alesta-ai/          ← VOUS ÊTES ICI (gratuit, wordpress.org)
 *   ├── Infrastructure : ExtensionsAPI, ModuleRegistry, EventLog
 *   ├── Modules FREE   : SEO basics, performance, security, GDPR…
 *   └── Hooks publics  : do_action('alesta_ai/loaded'), filters d'injection UI
 *
 *   alesta-ai-pro/      ← Plugin séparé, payant (Requires Plugins: alesta-ai)
 *   ├── Providers IA   : Claude (Anthropic), OpenAI (futur)
 *   ├── Modules PRO    : keywords-ai, faq-ai, schema-ai, content/improve…
 *   └── Bridges        : Galiance license bridge (alesta_license DB)
 *
 * Le Pro N'EST PAS un fork du Free : il consomme les hooks d'extension
 * publics exposés par ExtensionsAPI. Désactiver le Free désactive le Pro
 * (via header "Requires Plugins" en WP 6.5+, polyfill admin_notice avant).
 *
 * @see includes/core/class-extensions-api.php  Hooks publics
 * @see includes/core/class-module-registry.php Système d'enregistrement modules
 * @see https://github.com/elementor/elementor   Pattern de référence
 */

defined( 'ABSPATH' ) || exit;

// =============================================================================
// CONSTANTES
// =============================================================================

define( 'ALESTA_AI_VERSION', '2.0.8' );
define( 'ALESTA_AI_FILE',    __FILE__ );
define( 'ALESTA_AI_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ALESTA_AI_URL',     plugin_dir_url( __FILE__ ) );
define( 'ALESTA_AI_MIN_PHP', '7.4' );
define( 'ALESTA_AI_MIN_WP',  '6.0' );

// =============================================================================
// AUTOLOAD MINIMAL (sans Composer pour compatibilité wp.org)
// =============================================================================
//
// Mapping PSR-4 manuel :
//   AlestaAI\Core\ExtensionsAPI    -> includes/core/class-extensions-api.php
//   AlestaAI\Core\ModuleRegistry   -> includes/core/class-module-registry.php
//   AlestaAI\Modules\Seo\Sitemap   -> includes/modules/seo/class-sitemap.php
//
spl_autoload_register( function ( $class ) {
	if ( strpos( $class, 'AlestaAI\\' ) !== 0 ) {
		return;
	}
	$relative = substr( $class, strlen( 'AlestaAI\\' ) );           // ex: "Core\ExtensionsAPI"
	$parts    = explode( '\\', $relative );                          // ex: ["Core", "ExtensionsAPI"]
	$classname = array_pop( $parts );                                // "ExtensionsAPI"
	$dir       = strtolower( implode( DIRECTORY_SEPARATOR, $parts ) ); // "core"
	$filename  = 'class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $classname ) ) . '.php';
	$path      = ALESTA_AI_DIR . 'includes' . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $filename;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
} );

// =============================================================================
// BOOTSTRAP
// =============================================================================

add_action( 'plugins_loaded', function () {
	// 1. Init du registre central des modules.
	// La classe expose `register()`, `get()`, `all()` — utilisée par les modules
	// Free pour s'enregistrer ET par le Pro pour découvrir / overrider.
	\AlestaAI\Core\ModuleRegistry::instance();

	// 2. Init de l'API publique d'extension (hooks + helpers pour le Pro).
	\AlestaAI\Core\ExtensionsAPI::init();

	// 3. Chargement des modules FREE (chaque module s'auto-enregistre
	//    via ModuleRegistry::register() dans son fichier d'init).
	//
	//    Phase 1 (v2.0.0) — On migre depuis l'ancien Free 1.2.x :
	//      - SEO       : sitemap, robots, llms.txt
	//      - Perf      : htaccess, minify, fonts, errors, db-cleaner, maintenance,
	//                    debug, health, cwv, search-replace, redirects(core),
	//                    scripts(core), perf-audit(core)
	//      - Security  : rgpd, security-audit, activity, brute-force, login-bot, updates
	//      - Content   : duplicate, templates
	//      - Media     : webp, filenames(core)
	//      - Reputation: reviews, trustpilot
	//      - Comm      : talk-to-me (avec quota IA Alesta hébergé — 100 msg/mois)
	//      - Settings  : alerts, roles
	//
	//    Les modules sont chargés en lazy : ModuleRegistry les instancie
	//    uniquement quand WP en a besoin (admin page open, REST endpoint hit…).
	if ( file_exists( ALESTA_AI_DIR . 'includes/modules/index.php' ) ) {
		require_once ALESTA_AI_DIR . 'includes/modules/index.php';
	}

	// 4. SIGNAL DE FIN DE BOOT — déclenche le chargement du Pro s'il est actif.
	//    Le Pro écoute ce hook pour enregistrer ses providers IA et ses modules
	//    payants. C'est l'équivalent de `elementor/loaded` dans Elementor.
	do_action( 'alesta_ai/loaded' );

	// 5. INSTANCIATION DES MODULES (Free + Pro) — déclenche leurs hooks WP.
	//    Chaque module Free / Pro hooke `admin_menu`, `init`, etc. dans son
	//    constructeur. Sans cette boucle, les modules sont registered mais
	//    leurs hooks ne sont jamais branchés → aucun menu n'apparaît dans
	//    wp-admin. On force l'instanciation de tous les modules ici.
	//
	//    À exécuter APRÈS `alesta_ai/loaded` : le Pro a eu le temps de
	//    `register_pro(...)` ses propres modules, on les instancie tous d'un
	//    coup. Si on instanciait avant, les modules Pro seraient skip.
	$registry = \AlestaAI\Core\ModuleRegistry::instance();
	foreach ( array_keys( $registry->all() ) as $slug ) {
		// get() instancie le module (lazy) et déclenche son constructeur,
		// qui hooke admin_menu / init / etc.
		$registry->get( $slug );
	}
}, 10 );

// =============================================================================
// MENU ADMIN PARENT
// =============================================================================
//
// Crée le menu "Alesta AI" dans la sidebar wp-admin. Les modules Free et Pro
// s'y accrochent via `add_submenu_page('alesta-ai', ...)` dans leur propre
// `admin_menu` hook (déclenché par l'instanciation ci-dessus).
//
// Le slug du menu parent est délibérément `alesta-ai` pour matcher ce que
// les modules attendent (cf. alesta-ai-pro/includes/modules-pro/*/class-*.php
// qui font `add_submenu_page( 'alesta-ai', ... )`).
//
// Priorité 5 (avant la priorité par défaut 10 des sous-menus) pour que le
// parent existe quand WP traite les `add_submenu_page`.

add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'Alesta AI', 'alesta-ai' ),        // page title
		__( 'Alesta AI', 'alesta-ai' ),        // menu label
		'manage_alesta_ai',                     // capability custom (mappée à administrator)
		'alesta-ai',                            // slug parent — référencé par les sous-menus
		'alesta_ai_render_dashboard_page',      // callback (fonction globale ci-dessous)
		'dashicons-superhero',                  // icon
		25                                       // position (entre Pages et Commentaires)
	);
	// Renomme le 1er sous-menu auto-créé par WP (qui hérite du title parent) en
	// "Tableau de bord" pour cohérence avec l'usage WordPress standard.
	global $submenu;
	if ( isset( $submenu['alesta-ai'][0] ) ) {
		$submenu['alesta-ai'][0][0] = __( 'Tableau de bord', 'alesta-ai' );
	}
}, 5 );

/**
 * Rendu de la page d'accueil "Alesta AI" dans wp-admin.
 *
 * Style sobre dans l'esprit WordPress natif (postbox-like) :
 *   - Titre WP standard h1 + sous-titre court
 *   - Stats inline simples (compteurs modules / catégories / Pro)
 *   - Liste des modules groupés par catégorie avec libellés clairs + badge
 *     [Free] / [Pro] et lien vers la page du module
 *   - Footer avec liens utiles vers la config et la doc
 *
 * Volontairement minimaliste : reste cohérent avec le reste de wp-admin
 * (pas de gradient, pas de surcharge visuelle). Si l'utilisateur veut une
 * vraie "vue d'ensemble" il a accès au widget Dashboard via le Tableau de
 * bord principal de WP (cf. includes/dashboard/class-dashboard-widget.php).
 */
function alesta_ai_render_dashboard_page(): void {
	$registry   = \AlestaAI\Core\ModuleRegistry::instance();
	$all        = $registry->all();
	$free_count = count( $registry->all( [ 'source' => 'free' ] ) );
	$pro_count  = count( $registry->all( [ 'source' => 'pro' ] ) );
	$pro_active = $pro_count > 0;

	// Libellés et ordre des catégories. Modules sans catégorie connue tombent
	// dans "Autres" en fin de page (rare en pratique).
	$cat_labels = [
		'seo'           => __( 'SEO & Référencement', 'alesta-ai' ),
		'content'       => __( 'Contenu & Rédaction', 'alesta-ai' ),
		'performance'   => __( 'Performance', 'alesta-ai' ),
		'security'      => __( 'Sécurité & GDPR', 'alesta-ai' ),
		'media'         => __( 'Médias & Images', 'alesta-ai' ),
		'reports'       => __( 'Rapports & Audits', 'alesta-ai' ),
		'communication' => __( 'Communication & IA', 'alesta-ai' ),
		'settings'      => __( 'Réglages avancés', 'alesta-ai' ),
		'other'         => __( 'Autres', 'alesta-ai' ),
	];

	// Regroupe les modules par catégorie, en conservant l'ordre des libellés.
	$grouped = array_fill_keys( array_keys( $cat_labels ), [] );
	foreach ( $all as $slug => $entry ) {
		$cat = $entry['meta']['category'] ?? 'other';
		if ( ! isset( $grouped[ $cat ] ) ) {
			$cat = 'other';
		}
		$grouped[ $cat ][ $slug ] = $entry;
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Alesta AI', 'alesta-ai' ); ?></h1>
		<p class="description" style="max-width: 720px;">
			<?php esc_html_e( 'Toolkit WordPress modulaire — SEO, performance, sécurité, GDPR, analytics. Extension Pro optionnelle propulsée par Claude (Anthropic).', 'alesta-ai' ); ?>
		</p>

		<p>
			<strong><?php echo (int) count( $all ); ?></strong>
			<?php esc_html_e( 'modules chargés', 'alesta-ai' ); ?>
			(<?php echo (int) $free_count; ?> <?php esc_html_e( 'Free', 'alesta-ai' ); ?><?php if ( $pro_count > 0 ) : ?> · <?php echo (int) $pro_count; ?> <?php esc_html_e( 'Pro', 'alesta-ai' ); ?><?php endif; ?>)
			<?php if ( $pro_active ) : ?>
				· <span style="color: #008a20;">✓ <?php esc_html_e( 'Extension Pro active', 'alesta-ai' ); ?></span>
			<?php else : ?>
				· <a href="https://alesta-ai.com/tarifs.html" target="_blank" rel="noopener"><?php esc_html_e( 'Découvrir Alesta AI Pro →', 'alesta-ai' ); ?></a>
			<?php endif; ?>
			· <code>v<?php echo esc_html( ALESTA_AI_VERSION ); ?><?php if ( defined( 'ALESTA_AI_PRO_VERSION' ) ) : ?> + <?php echo esc_html( ALESTA_AI_PRO_VERSION ); ?> Pro<?php endif; ?></code>
		</p>

		<hr />

		<?php foreach ( $cat_labels as $cat_key => $cat_label ) : ?>
			<?php
			$mods = $grouped[ $cat_key ] ?? [];
			if ( empty( $mods ) ) {
				continue;
			}
			?>
			<h2 style="margin-top: 24px;">
				<?php echo esc_html( $cat_label ); ?>
				<span style="font-weight: 400; color: #646970; font-size: 13px;">(<?php echo (int) count( $mods ); ?>)</span>
			</h2>
			<table class="widefat striped" style="max-width: 900px;">
				<tbody>
					<?php foreach ( $mods as $slug => $entry ) : ?>
						<?php
						$name       = $entry['meta']['name'] ?? $slug;
						$desc       = $entry['meta']['description'] ?? '';
						$short_slug = preg_replace( '#^.+/#', '', $slug );
						$page_slug  = 'alesta-ai-' . str_replace( '/', '-', $short_slug );
						$href       = admin_url( 'admin.php?page=' . $page_slug );
						$is_pro     = ( $entry['source'] ?? 'free' ) === 'pro';
						?>
						<tr>
							<td style="width: 30%;">
								<strong><a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $name ); ?></a></strong>
								<?php if ( $is_pro ) : ?>
									<span style="display: inline-block; margin-left: 6px; padding: 1px 8px; background: #f0e7ff; color: #6c2bd9; border: 1px solid #d4b8ff; border-radius: 3px; font-size: 11px; font-weight: 600;">PRO</span>
								<?php endif; ?>
							</td>
							<td style="color: #646970;"><?php echo esc_html( $desc ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

		<hr style="margin-top: 32px;" />
		<p>
			<a href="https://alesta-ai.com/docs" target="_blank" rel="noopener" class="button button-secondary"><?php esc_html_e( 'Documentation', 'alesta-ai' ); ?></a>
			<?php if ( $pro_active ) : ?>
				<a href="https://app.galiance.fr/dashboard" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e( 'Cockpit Galiance', 'alesta-ai' ); ?></a>
			<?php else : ?>
				<a href="https://alesta-ai.com/tarifs.html" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e( 'Passer à Pro', 'alesta-ai' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

// =============================================================================
// HOOKS DE CYCLE DE VIE
// =============================================================================

register_activation_hook( __FILE__, function () {
	// Capacité custom pour les pages admin (mappable à des rôles via le module roles).
	$role = get_role( 'administrator' );
	if ( $role && ! $role->has_cap( 'manage_alesta_ai' ) ) {
		$role->add_cap( 'manage_alesta_ai' );
	}
	// Flush rewrite (utile pour le sitemap XML notamment).
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
