<?php
/**
 * Plugin Name:       Alesta AI
 * Plugin URI:        https://alesta-ai.com/
 * Description:       WordPress optimization toolkit — SEO, performance, security, GDPR, reviews and analytics. Foundation for the Alesta AI Pro extension.
 * Version:           2.0.6
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

define( 'ALESTA_AI_VERSION', '2.0.6' );
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
		function () {                           // callback page dashboard simple
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Alesta AI', 'alesta-ai' ) . '</h1>';
			echo '<p>' . esc_html__( 'Bienvenue sur Alesta AI — toolkit WordPress (SEO, performance, sécurité, GDPR, analytics) avec extension Pro optionnelle (IA Claude).', 'alesta-ai' ) . '</p>';
			$registry  = \AlestaAI\Core\ModuleRegistry::instance();
			$modules   = $registry->all();
			$free_count = count( $registry->all( [ 'source' => 'free' ] ) );
			$pro_count  = count( $registry->all( [ 'source' => 'pro' ] ) );
			echo '<p><strong>' . esc_html(
				sprintf(
					/* translators: 1: free modules count, 2: pro modules count */
					_n( '%1$d module Free chargé', '%1$d modules Free chargés', $free_count, 'alesta-ai' ),
					$free_count
				)
			) . '</strong>';
			if ( $pro_count > 0 ) {
				echo ' · ' . esc_html(
					sprintf(
						_n( '%1$d module Pro chargé', '%1$d modules Pro chargés', $pro_count, 'alesta-ai' ),
						$pro_count
					)
				);
			}
			echo '</p>';
			if ( ! empty( $modules ) ) {
				echo '<h2>' . esc_html__( 'Modules actifs', 'alesta-ai' ) . '</h2>';
				echo '<ul style="columns:2;-webkit-columns:2;-moz-columns:2;max-width:900px;">';
				foreach ( $modules as $slug => $entry ) {
					$badge = 'pro' === $entry['source'] ? ' <em style="color:#9333ea">[Pro]</em>' : '';
					printf(
						'<li><code>%s</code> — %s%s</li>',
						esc_html( $slug ),
						esc_html( $entry['meta']['name'] ?? $slug ),
						$badge // déjà escaped au-dessus
					);
				}
				echo '</ul>';
			}
			echo '</div>';
		},
		'dashicons-superhero',                  // icon
		25                                       // position (entre Pages et Commentaires)
	);
}, 5 );

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
