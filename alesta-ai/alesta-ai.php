<?php
/**
 * Plugin Name:       Alesta AI
 * Plugin URI:        https://alesta-ai.com/
 * Description:       WordPress optimization toolkit — SEO, performance, security, GDPR, reviews and analytics. Foundation for the Alesta AI Pro extension.
 * Version:           2.0.11
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

define( 'ALESTA_AI_VERSION', '2.0.11' );
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
 * Sub-menu FALLBACK pour chaque module registered qui n'a pas créé son propre
 * sub-menu (cas v2.0 actuelle : 18 modules Pro mais 16 sont des stubs sans
 * méthode `register_admin_page`, donc pas de hook admin_menu déclenché).
 *
 * Sans ce fallback, la sidebar n'affiche que les modules Free + les 2 Pro
 * implémentés → ~12 items, alors qu'on attend 27+ (1 par module).
 *
 * Priorité 50 : exécuté APRÈS les modules qui ajoutent leur propre menu
 * (priorité 10 par défaut) et AVANT le patch des labels Pro (priorité 9999).
 *
 * Le callback du fallback affiche une page "module en cours d'implémentation"
 * propre, qui matche l'esprit v1.2.x où chaque module avait son entrée même
 * en attendant son code complet (page de promo Pro).
 */
add_action( 'admin_menu', function () {
	global $submenu;
	$registry = \AlestaAI\Core\ModuleRegistry::instance();
	$all = $registry->all();

	// Index : page-slug -> true pour les modules qui ont DÉJÀ leur sub-menu.
	$existing_slugs = [];
	foreach ( $submenu['alesta-ai'] ?? [] as $item ) {
		$page = $item[2] ?? '';
		if ( $page ) {
			$existing_slugs[ $page ] = true;
		}
	}

	foreach ( $all as $slug => $entry ) {
		$short_slug = preg_replace( '#^.+/#', '', $slug );
		$page_slug  = 'alesta-ai-' . str_replace( '/', '-', $short_slug );
		if ( isset( $existing_slugs[ $page_slug ] ) ) {
			continue;
		}

		$name   = $entry['meta']['name'] ?? $slug;
		$desc   = $entry['meta']['description'] ?? '';
		$is_pro = ( $entry['source'] ?? 'free' ) === 'pro';

		// Label menu (HTML autorisé par WP). Le badge Pro est ajouté ici dans
		// le label original — le hook de patch priorité 9999 le repassera
		// quand même (idempotent : strpos pour éviter double pill).
		$label = esc_html( $name );

		add_submenu_page(
			'alesta-ai',
			$name,
			$label,
			'manage_alesta_ai',
			$page_slug,
			function () use ( $name, $desc, $is_pro ) {
				echo '<div class="wrap">';
				echo '<h1>' . esc_html( $name );
				if ( $is_pro ) {
					echo ' <span style="display:inline-block;background:#e8890c;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle;letter-spacing:.3px;text-transform:uppercase;margin-left:8px;">Pro</span>';
				}
				echo '</h1>';
				if ( $desc ) {
					echo '<p class="description" style="max-width:720px;font-size:13px;">' . esc_html( $desc ) . '</p>';
				}
				echo '<div class="notice notice-info inline" style="margin:20px 0;padding:14px 18px;">';
				echo '<p><strong>' . esc_html__( 'Module en cours d\'implémentation', 'alesta-ai' ) . '</strong></p>';
				echo '<p>' . esc_html__( "Ce module est enregistré dans Alesta AI mais son interface de configuration n'est pas encore disponible dans cette version. Les hooks publics restent fonctionnels en arrière-plan.", 'alesta-ai' ) . '</p>';
				echo '<p style="margin-top:10px;"><a href="' . esc_url( admin_url( 'admin.php?page=alesta-ai' ) ) . '" class="button">' . esc_html__( '← Retour au tableau de bord', 'alesta-ai' ) . '</a></p>';
				echo '</div>';
				echo '</div>';
			}
		);
	}
}, 50 );

/**
 * Patch des labels du sous-menu Alesta AI pour injecter les pills "Pro" orange
 * (style v1.2.x originel #e8890c) à côté du nom des modules Pro.
 *
 * Priorité 9999 = exécuté APRÈS tous les modules qui ont fait add_submenu_page
 * (priorité 10 par défaut). On parcourt $submenu['alesta-ai'] et on identifie
 * chaque entrée dont le page-slug correspond à un module registered comme
 * source='pro' dans ModuleRegistry. Le label se voit append un <span> pill.
 *
 * Le CSS associé est injecté via admin_head ci-dessous (toujours visible côté
 * wp-admin, pas seulement sur la page Alesta AI).
 */
add_action( 'admin_menu', function () {
	global $submenu;
	if ( empty( $submenu['alesta-ai'] ) ) {
		return;
	}
	$registry = \AlestaAI\Core\ModuleRegistry::instance();
	$pro_modules = $registry->all( [ 'source' => 'pro' ] );

	// Index : page-slug -> est-Pro. Construit selon la convention
	// "page = alesta-ai-{short_slug}" appliquée par les modules.
	$pro_slugs = [];
	foreach ( $pro_modules as $slug => $entry ) {
		$short_slug = preg_replace( '#^.+/#', '', $slug );
		$pro_slugs[ 'alesta-ai-' . str_replace( '/', '-', $short_slug ) ] = true;
	}

	foreach ( $submenu['alesta-ai'] as $i => $item ) {
		$page = $item[2] ?? '';
		if ( isset( $pro_slugs[ $page ] ) ) {
			$submenu['alesta-ai'][ $i ][0] .= ' <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>';
		}
	}
}, 9999 );

/**
 * Injecte le CSS des pills sidebar dans toutes les pages wp-admin (le menu
 * Alesta AI est rendu partout, donc le CSS doit être présent partout).
 *
 * Couleur reprise de v1.2.x : orange Alesta #e8890c. Cohérent avec la
 * charte historique du plugin (avant migration v2.0). Volontairement
 * minimal pour ne pas alourdir wp-admin — scopé strictement à #adminmenu.
 */
add_action( 'admin_head', function () {
	?>
	<style>
		/* Pills "Pro" injectées dans les labels des modules Pro (sidebar) */
		#adminmenu .alesta-pro-pill {
			display: inline-block;
			font-size: 9px;
			font-weight: 700;
			padding: 1px 6px;
			margin-left: 6px;
			border-radius: 4px;
			vertical-align: middle;
			line-height: 1.5;
			letter-spacing: .3px;
			background: #fff3e0;
			color: #7c3d00;
			border: 1px solid #e8890c;
			text-transform: uppercase;
		}
		#adminmenu .alesta-pro-pill--pro {
			background: #e8890c;
			color: #fff;
			border-color: #e8890c;
		}
	</style>
	<?php
} );

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

	// --- Catégories : libellé + numéro de section + emoji d'icône fallback ---
	$cat_meta = [
		'seo'           => [ 'label' => __( 'SEO & Référencement', 'alesta-ai' ),  'desc' => __( 'Optimisation on-page, balises, mots-clés, données structurées, visibilité IA', 'alesta-ai' ),  'fallback_icon' => '📝' ],
		'content'       => [ 'label' => __( 'Contenu & Rédaction', 'alesta-ai' ),  'desc' => __( 'Génération, amélioration et traduction de contenu via IA', 'alesta-ai' ),                  'fallback_icon' => '✍️' ],
		'performance'   => [ 'label' => __( 'Performance', 'alesta-ai' ),          'desc' => __( 'Cache, minification, base de données, Core Web Vitals', 'alesta-ai' ),                  'fallback_icon' => '⚡' ],
		'security'      => [ 'label' => __( 'Sécurité & GDPR', 'alesta-ai' ),      'desc' => __( 'Brute-force, RGPD, audit sécurité, bannière cookies', 'alesta-ai' ),                    'fallback_icon' => '🛡️' ],
		'media'         => [ 'label' => __( 'Médias & Images', 'alesta-ai' ),      'desc' => __( 'Conversion WebP, optimisation, alt text IA, nommage', 'alesta-ai' ),                    'fallback_icon' => '🖼️' ],
		'reports'       => [ 'label' => __( 'Rapports & Audits', 'alesta-ai' ),    'desc' => __( 'Synthèses PDF mensuelles, audits priorisés par IA', 'alesta-ai' ),                      'fallback_icon' => '📊' ],
		'communication' => [ 'label' => __( 'Communication & IA', 'alesta-ai' ),   'desc' => __( 'Chatbot front-end, modération commentaires IA', 'alesta-ai' ),                          'fallback_icon' => '💬' ],
		'settings'      => [ 'label' => __( 'Réglages avancés', 'alesta-ai' ),     'desc' => __( 'Alertes, rôles, paramètres globaux', 'alesta-ai' ),                                     'fallback_icon' => '⚙️' ],
		'other'         => [ 'label' => __( 'Autres', 'alesta-ai' ),               'desc' => __( 'Modules sans catégorie principale', 'alesta-ai' ),                                      'fallback_icon' => '🔧' ],
	];

	// Regroupe les modules par catégorie, dans l'ordre $cat_meta
	$grouped = array_fill_keys( array_keys( $cat_meta ), [] );
	foreach ( $all as $slug => $entry ) {
		$cat = $entry['meta']['category'] ?? 'other';
		if ( ! isset( $grouped[ $cat ] ) ) {
			$cat = 'other';
		}
		$grouped[ $cat ][ $slug ] = $entry;
	}

	// --- Données cockpit (lecture WP standard, dispo côté Free) ---
	$wp_version       = get_bloginfo( 'version' );
	$php_version      = PHP_VERSION;
	$wp_ok            = version_compare( $wp_version, '6.4', '>=' );
	$php_ok           = version_compare( $php_version, '8.0', '>=' );
	$is_https         = strpos( (string) get_option( 'siteurl', '' ), 'https://' ) === 0;
	$update_plugins   = get_site_transient( 'update_plugins' );
	$plugins_pending  = is_object( $update_plugins ) && ! empty( $update_plugins->response ) ? count( $update_plugins->response ) : 0;
	$update_themes    = get_site_transient( 'update_themes' );
	$themes_pending   = is_object( $update_themes ) && ! empty( $update_themes->response ) ? count( $update_themes->response ) : 0;
	$pending_updates  = $plugins_pending + $themes_pending;
	$total_posts      = (int) wp_count_posts( 'post' )->publish;
	$total_pages      = (int) wp_count_posts( 'page' )->publish;
	$total_content    = $total_posts + $total_pages;
	$total_images     = (int) wp_count_posts( 'attachment' )->inherit;
	$llms_exists      = file_exists( trailingslashit( ABSPATH ) . 'llms.txt' );
	$robots_exists    = file_exists( trailingslashit( ABSPATH ) . 'robots.txt' );

	?>
	<div class="wrap alesta-wrap">
		<style>
			.alesta-wrap { max-width: 1280px; padding-right: 20px; }
			.alesta-wrap .alesta-cockpit-header {
				display: flex; align-items: center; justify-content: space-between;
				padding: 20px 26px;
				background: linear-gradient(135deg, #1e3a5f 0%, #0f2440 100%);
				border-radius: 10px; margin: 16px 0 20px;
				flex-wrap: wrap; gap: 12px;
			}
			.alesta-wrap .alesta-cockpit-logo {
				display: flex; align-items: center; gap: 14px;
			}
			.alesta-wrap .alesta-cockpit-icon {
				display: inline-flex; align-items: center; justify-content: center;
				width: 50px; height: 50px;
				background: rgba(255,255,255,.1); border-radius: 12px;
				font-family: Georgia, serif; font-size: 36px; line-height: 1; color: #fff;
			}
			.alesta-wrap .alesta-cockpit-title { color: #fff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -.3px; }
			.alesta-wrap .alesta-cockpit-sub   { color: #94a3b8; margin: 0; font-size: 13px; }
			.alesta-wrap .alesta-badge {
				font-size: 11px; padding: 3px 10px; border-radius: 20px;
				background: rgba(255,255,255,.12); color: #cbd5e1;
				border: 1px solid rgba(255,255,255,.18);
			}
			.alesta-wrap .alesta-badge.is-active { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
			.alesta-wrap .alesta-badge.is-warn   { background: #fef9c3; color: #713f12; border-color: #fcd34d; }
			.alesta-wrap .alesta-badge.is-info   { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }

			/* Cockpit panneaux */
			.alesta-wrap .alesta-cockpit-grid {
				display: grid; grid-template-columns: repeat(4, 1fr);
				gap: 14px; margin-bottom: 24px;
			}
			.alesta-wrap .alesta-panel {
				background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;
			}
			.alesta-wrap .alesta-panel-head {
				padding: 11px 16px; background: #f0f9ff;
				border-bottom: 1px solid #bae6fd;
				display: flex; align-items: center; gap: 8px;
			}
			.alesta-wrap .alesta-panel-head .dashicons {
				color: #0ea5e9; font-size: 16px; width: 16px; height: 16px;
			}
			.alesta-wrap .alesta-panel-label {
				font-weight: 700; font-size: 12px; color: #0369a1; letter-spacing: .3px;
			}
			.alesta-wrap .alesta-panel-body {
				padding: 14px 16px;
				display: flex; flex-direction: column; gap: 8px;
				font-size: 12px;
			}
			.alesta-wrap .alesta-row {
				display: flex; align-items: center; justify-content: space-between;
			}
			.alesta-wrap .alesta-row-label { color: #6b7280; }
			.alesta-wrap .alesta-row-value { font-weight: 600; color: #111827; display: flex; align-items: center; gap: 5px; }
			.alesta-wrap .alesta-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; }
			.alesta-wrap .alesta-dot.ok { background: #22c55e; }
			.alesta-wrap .alesta-dot.warn { background: #f59e0b; }
			.alesta-wrap .alesta-dot.err { background: #ef4444; }

			/* Section block (catalogue modules) */
			.alesta-wrap .alesta-section-block {
				margin-bottom: 1.5rem;
				border: 1px solid #e5e7eb; border-radius: 12px;
				overflow: hidden; background: #fff;
			}
			.alesta-wrap .alesta-section-heading {
				display: flex; align-items: center; gap: 10px;
				padding: 12px 20px;
				background: #f8fafc; border-bottom: 1px solid #e5e7eb;
				flex-wrap: wrap;
			}
			.alesta-wrap .alesta-section-num {
				font-size: 10px; font-weight: 700; color: #6b7280;
				font-family: monospace;
				background: #e5e7eb; padding: 2px 6px; border-radius: 4px;
				letter-spacing: .5px;
			}
			.alesta-wrap .alesta-section-title { font-size: 13px; font-weight: 600; color: #1e3a5f; }
			.alesta-wrap .alesta-section-desc  { font-size: 12px; color: #9ca3af; }

			/* Module grid */
			.alesta-wrap .alesta-cards {
				display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
				background: #fff;
			}
			.alesta-wrap .alesta-module-card {
				display: flex; flex-direction: column; gap: 6px;
				padding: 28px 18px 16px 18px;
				border-right: 1px solid #f3f4f6;
				border-bottom: 1px solid #f3f4f6;
				background: #fff;
				position: relative;
				transition: background-color .12s ease-out;
			}
			.alesta-wrap .alesta-module-card:hover { background: #fafafa; }
			.alesta-wrap .alesta-module-active { border-top: 3px solid #1e3a5f; }
			.alesta-wrap .amc-icon { font-size: 22px; line-height: 1; margin-bottom: 2px; }
			.alesta-wrap .amc-name { font-size: 13px; font-weight: 600; color: #111827; }
			.alesta-wrap .amc-desc { font-size: 11px; color: #6b7280; line-height: 1.5; flex: 1; }
			.alesta-wrap .amc-footer { margin-top: 6px; display: flex; gap: 6px; }
			.alesta-wrap .amc-status {
				position: absolute; top: 10px; right: 12px;
				font-size: 10px; font-weight: 600;
				padding: 2px 8px; border-radius: 999px;
				background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7;
			}
			.alesta-wrap .amc-badge-pro {
				display: inline-block;
				background: #e8890c;
				color: #fff; font-size: 9.5px;
				padding: 1px 7px; border-radius: 4px;
				font-weight: 700; letter-spacing: .3px;
				margin-left: 4px; vertical-align: middle;
				border: 1px solid #e8890c;
				text-transform: uppercase;
			}
		</style>

		<!-- ═══════════════════════════════════════════════════════════
		     COCKPIT HEADER
		══════════════════════════════════════════════════════════════ -->
		<div class="alesta-cockpit-header">
			<div class="alesta-cockpit-logo">
				<span class="alesta-cockpit-icon">&#x03C6;</span>
				<div>
					<h1 class="alesta-cockpit-title">Master AI Dashboard</h1>
					<p class="alesta-cockpit-sub"><?php esc_html_e( 'Cockpit central — santé, modules, sécurité et visibilité IA en un seul écran', 'alesta-ai' ); ?></p>
				</div>
			</div>
			<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
				<span class="alesta-badge"><?php echo esc_html( 'v' . ALESTA_AI_VERSION ); ?><?php if ( defined( 'ALESTA_AI_PRO_VERSION' ) ) : ?> + <?php echo esc_html( ALESTA_AI_PRO_VERSION ); ?> Pro<?php endif; ?></span>
				<?php if ( $pro_active ) : ?>
					<span class="alesta-badge is-active"><?php esc_html_e( '✓ Extension Pro active', 'alesta-ai' ); ?></span>
				<?php else : ?>
					<span class="alesta-badge is-info"><?php esc_html_e( 'Free seul', 'alesta-ai' ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════════════════════
		     COCKPIT 4 PANNEAUX
		══════════════════════════════════════════════════════════════ -->
		<div class="alesta-cockpit-grid">
			<!-- Santé du site -->
			<div class="alesta-panel">
				<div class="alesta-panel-head">
					<span class="dashicons dashicons-heart" aria-hidden="true"></span>
					<span class="alesta-panel-label"><?php esc_html_e( 'SANTÉ DU SITE', 'alesta-ai' ); ?></span>
				</div>
				<div class="alesta-panel-body">
					<div class="alesta-row">
						<span class="alesta-row-label">WordPress</span>
						<span class="alesta-row-value"><span class="alesta-dot <?php echo $wp_ok ? 'ok' : 'warn'; ?>"></span><?php echo esc_html( $wp_version ); ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label">PHP</span>
						<span class="alesta-row-value"><span class="alesta-dot <?php echo $php_ok ? 'ok' : 'warn'; ?>"></span><?php echo esc_html( $php_version ); ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label">HTTPS</span>
						<span class="alesta-row-value"><span class="alesta-dot <?php echo $is_https ? 'ok' : 'err'; ?>"></span><?php echo $is_https ? esc_html__( 'Actif', 'alesta-ai' ) : esc_html__( 'Inactif', 'alesta-ai' ); ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'Mises à jour', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><span class="alesta-dot <?php echo $pending_updates === 0 ? 'ok' : 'warn'; ?>"></span><?php echo (int) $pending_updates; ?></span>
					</div>
				</div>
			</div>

			<!-- Contenu -->
			<div class="alesta-panel">
				<div class="alesta-panel-head">
					<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					<span class="alesta-panel-label"><?php esc_html_e( 'CONTENU', 'alesta-ai' ); ?></span>
				</div>
				<div class="alesta-panel-body">
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'Articles', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><?php echo (int) $total_posts; ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'Pages', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><?php echo (int) $total_pages; ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'Médias', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><?php echo (int) $total_images; ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'Total publié', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><?php echo (int) $total_content; ?></span>
					</div>
				</div>
			</div>

			<!-- Modules -->
			<div class="alesta-panel">
				<div class="alesta-panel-head">
					<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
					<span class="alesta-panel-label"><?php esc_html_e( 'MODULES', 'alesta-ai' ); ?></span>
				</div>
				<div class="alesta-panel-body">
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'Total chargés', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><?php echo (int) count( $all ); ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'Free', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><?php echo (int) $free_count; ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'Pro', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><?php echo (int) $pro_count; ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'Catégories', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><?php echo (int) count( array_filter( $grouped, fn( $g ) => ! empty( $g ) ) ); ?></span>
					</div>
				</div>
			</div>

			<!-- Visibilité IA -->
			<div class="alesta-panel">
				<div class="alesta-panel-head">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					<span class="alesta-panel-label"><?php esc_html_e( 'VISIBILITÉ IA', 'alesta-ai' ); ?></span>
				</div>
				<div class="alesta-panel-body">
					<div class="alesta-row">
						<span class="alesta-row-label">robots.txt</span>
						<span class="alesta-row-value"><span class="alesta-dot <?php echo $robots_exists ? 'ok' : 'warn'; ?>"></span><?php echo $robots_exists ? esc_html__( 'Présent', 'alesta-ai' ) : esc_html__( 'Absent', 'alesta-ai' ); ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label">llms.txt</span>
						<span class="alesta-row-value"><span class="alesta-dot <?php echo $llms_exists ? 'ok' : 'warn'; ?>"></span><?php echo $llms_exists ? esc_html__( 'Présent', 'alesta-ai' ) : esc_html__( 'Absent', 'alesta-ai' ); ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label">sitemap.xml</span>
						<span class="alesta-row-value"><span class="alesta-dot ok"></span><?php esc_html_e( 'Auto', 'alesta-ai' ); ?></span>
					</div>
					<div class="alesta-row">
						<span class="alesta-row-label"><?php esc_html_e( 'IA Pro', 'alesta-ai' ); ?></span>
						<span class="alesta-row-value"><span class="alesta-dot <?php echo $pro_active ? 'ok' : 'err'; ?>"></span><?php echo $pro_active ? esc_html__( 'Active', 'alesta-ai' ) : esc_html__( 'Non installée', 'alesta-ai' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════════════════════
		     CATALOGUE DES MODULES (par catégorie, sections numérotées)
		══════════════════════════════════════════════════════════════ -->
		<?php
		$section_num = 0;
		foreach ( $cat_meta as $cat_key => $meta ) :
			$mods = $grouped[ $cat_key ] ?? [];
			if ( empty( $mods ) ) {
				continue;
			}
			$section_num++;
			?>
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num"><?php printf( '%02d', $section_num ); ?></span>
					<span class="alesta-section-title"><?php echo esc_html( $meta['label'] ); ?></span>
					<span class="alesta-section-desc"><?php echo esc_html( $meta['desc'] ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php foreach ( $mods as $slug => $entry ) :
						$name       = $entry['meta']['name'] ?? $slug;
						$desc       = $entry['meta']['description'] ?? '';
						$short_slug = preg_replace( '#^.+/#', '', $slug );
						$page_slug  = 'alesta-ai-' . str_replace( '/', '-', $short_slug );
						$href       = admin_url( 'admin.php?page=' . $page_slug );
						$is_pro     = ( $entry['source'] ?? 'free' ) === 'pro';
						$icon       = $entry['meta']['icon_emoji'] ?? $meta['fallback_icon'];
						?>
						<div class="alesta-module-card alesta-module-active">
							<span class="amc-status"><?php esc_html_e( '✓ Disponible', 'alesta-ai' ); ?></span>
							<div class="amc-icon"><?php echo esc_html( $icon ); ?></div>
							<div class="amc-info">
								<div class="amc-name">
									<?php echo esc_html( $name ); ?>
									<?php if ( $is_pro ) : ?>
										<span class="amc-badge-pro">PRO</span>
									<?php endif; ?>
								</div>
								<?php if ( $desc ) : ?>
									<div class="amc-desc"><?php echo esc_html( $desc ); ?></div>
								<?php endif; ?>
							</div>
							<div class="amc-footer">
								<a href="<?php echo esc_url( $href ); ?>" class="button button-primary"><?php esc_html_e( 'Ouvrir', 'alesta-ai' ); ?></a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<!-- Footer -->
		<p style="margin-top: 24px;">
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
