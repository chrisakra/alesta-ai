<?php
/**
 * Plugin Name:       Alesta AI
 * Plugin URI:        https://alesta-ai.com/
 * Description:       WordPress optimization toolkit — SEO, performance, security, GDPR, reviews and analytics. Foundation for the Alesta AI Pro extension.
 * Version:           2.0.7
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

define( 'ALESTA_AI_VERSION', '2.0.7' );
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
}, 5 );

/**
 * Rendu de la page d'accueil "Alesta AI" dans wp-admin.
 *
 * Layout :
 *   - Header gradient avec titre + sous-titre + badge version
 *   - Stats banner : nb modules Free / Pro / catégories
 *   - Grille de cards par catégorie (SEO, Performance, Security, Content,
 *     Media, Settings, Reports, Communication) avec liste des modules
 *     correspondants. Chaque card a son icône dashicons et son accent color.
 *   - Footer avec liens vers réglages utiles (clés API, license, support)
 *
 * Pure PHP echo + CSS inline (pas de fichier .css séparé à enqueue) :
 *  - Garde le plugin lean (cf. politique 0-dep Free)
 *  - Cohérent visuellement même sans hit cache CSS au 1er rendu
 *  - Limite scope aux .alesta-dash-* pour ne pas polluer le reste de wp-admin
 */
function alesta_ai_render_dashboard_page(): void {
	$registry   = \AlestaAI\Core\ModuleRegistry::instance();
	$all        = $registry->all();
	$free_count = count( $registry->all( [ 'source' => 'free' ] ) );
	$pro_count  = count( $registry->all( [ 'source' => 'pro' ] ) );

	// Catégories affichées en cards. Si meta['category'] match → on regroupe ici.
	// L'ordre détermine l'ordre d'affichage. Les modules sans catégorie known
	// tombent dans "other" (qui n'est pas affichée si vide).
	$categories = [
		'seo'           => [ 'label' => __( 'SEO & Référencement', 'alesta-ai' ),    'icon' => 'chart-line',         'color' => '#2563eb' ],
		'content'       => [ 'label' => __( 'Contenu & Rédaction', 'alesta-ai' ),    'icon' => 'edit',               'color' => '#9333ea' ],
		'performance'   => [ 'label' => __( 'Performance', 'alesta-ai' ),            'icon' => 'performance',        'color' => '#16a34a' ],
		'security'      => [ 'label' => __( 'Sécurité & GDPR', 'alesta-ai' ),        'icon' => 'shield-alt',         'color' => '#dc2626' ],
		'media'         => [ 'label' => __( 'Médias & Images', 'alesta-ai' ),        'icon' => 'format-image',       'color' => '#ea580c' ],
		'reports'       => [ 'label' => __( 'Rapports & Audits', 'alesta-ai' ),      'icon' => 'analytics',          'color' => '#0891b2' ],
		'communication' => [ 'label' => __( 'Communication & IA', 'alesta-ai' ),     'icon' => 'format-chat',        'color' => '#ec4899' ],
		'settings'      => [ 'label' => __( 'Réglages avancés', 'alesta-ai' ),       'icon' => 'admin-generic',      'color' => '#64748b' ],
	];

	// Regroupe les modules par catégorie pour itération facile.
	$grouped = array_fill_keys( array_keys( $categories ), [] );
	foreach ( $all as $slug => $entry ) {
		$cat = $entry['meta']['category'] ?? 'other';
		if ( isset( $grouped[ $cat ] ) ) {
			$grouped[ $cat ][ $slug ] = $entry;
		}
	}
	$pro_active = $pro_count > 0;
	$total      = count( $all );
	$cat_used   = count( array_filter( $grouped, fn( $g ) => ! empty( $g ) ) );

	?>
	<div class="wrap alesta-dash">
		<style>
			.alesta-dash { max-width: 1280px; }
			.alesta-dash-header {
				background: linear-gradient(135deg, #1e293b 0%, #312e81 100%);
				color: #fff;
				padding: 36px 40px;
				border-radius: 12px;
				margin: 16px 0 24px;
				position: relative;
				overflow: hidden;
			}
			.alesta-dash-header::after {
				content: ""; position: absolute; right: -40px; top: -40px;
				width: 220px; height: 220px; border-radius: 50%;
				background: rgba(167, 139, 250, 0.15); pointer-events: none;
			}
			.alesta-dash-header h1 { color: #fff; font-size: 28px; margin: 0 0 8px; font-weight: 600; }
			.alesta-dash-header p { color: rgba(255,255,255,.78); margin: 0 0 16px; font-size: 14px; line-height: 1.5; max-width: 720px; }
			.alesta-dash-header .alesta-dash-badge {
				display: inline-block; padding: 4px 10px; font-size: 11px;
				background: rgba(167, 139, 250, 0.22); color: #c4b5fd;
				border: 1px solid rgba(167, 139, 250, 0.35);
				border-radius: 999px; letter-spacing: .04em; font-weight: 500;
			}
			.alesta-dash-stats {
				display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
				gap: 12px; margin: 0 0 24px;
			}
			.alesta-dash-stat {
				background: #fff; padding: 18px 20px; border-radius: 10px;
				border: 1px solid #e2e8f0;
			}
			.alesta-dash-stat-label {
				font-size: 11px; letter-spacing: .08em; text-transform: uppercase;
				color: #64748b; margin: 0 0 6px;
			}
			.alesta-dash-stat-value {
				font-size: 26px; font-weight: 600; color: #0f172a; line-height: 1.1;
			}
			.alesta-dash-stat-sub { font-size: 12px; color: #94a3b8; margin: 4px 0 0; }
			.alesta-dash-grid {
				display: grid; grid-template-columns: repeat(auto-fit, minmax(340px,1fr));
				gap: 16px;
			}
			.alesta-dash-card {
				background: #fff; border-radius: 10px; padding: 22px 22px 18px;
				border: 1px solid #e2e8f0; display: flex; flex-direction: column;
			}
			.alesta-dash-card-head {
				display: flex; align-items: center; gap: 12px; margin: 0 0 14px;
				padding-bottom: 14px; border-bottom: 1px solid #f1f5f9;
			}
			.alesta-dash-card-icon {
				width: 40px; height: 40px; border-radius: 8px;
				display: flex; align-items: center; justify-content: center;
				color: #fff;
			}
			.alesta-dash-card-icon .dashicons { font-size: 22px; width: 22px; height: 22px; }
			.alesta-dash-card-title { font-size: 14px; font-weight: 600; color: #0f172a; margin: 0; }
			.alesta-dash-card-count { font-size: 11px; color: #64748b; margin: 2px 0 0; }
			.alesta-dash-mod { display: flex; align-items: center; gap: 10px; padding: 8px 0; font-size: 13px; }
			.alesta-dash-mod + .alesta-dash-mod { border-top: 1px dashed #f1f5f9; }
			.alesta-dash-mod a { color: #1e293b; text-decoration: none; flex: 1; }
			.alesta-dash-mod a:hover { color: #2563eb; }
			.alesta-dash-mod-pro {
				background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%);
				color: #fff; font-size: 10px; padding: 2px 8px; border-radius: 999px;
				font-weight: 600; letter-spacing: .04em;
			}
			.alesta-dash-mod-free {
				background: #f1f5f9; color: #64748b; font-size: 10px; padding: 2px 8px;
				border-radius: 999px; font-weight: 600;
			}
			.alesta-dash-footer {
				margin: 28px 0 8px; padding: 18px 22px; background: #f8fafc;
				border: 1px solid #e2e8f0; border-radius: 10px;
				display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between;
				align-items: center;
			}
			.alesta-dash-footer-title { font-weight: 600; color: #0f172a; margin: 0; font-size: 13px; }
			.alesta-dash-footer-sub { color: #64748b; font-size: 12px; margin: 4px 0 0; }
			.alesta-dash-footer a.button { text-decoration: none; }
			@media (max-width: 783px) {
				.alesta-dash-header { padding: 24px 22px; }
				.alesta-dash-header h1 { font-size: 22px; }
			}
		</style>

		<!-- Header -->
		<div class="alesta-dash-header">
			<span class="alesta-dash-badge">v<?php echo esc_html( ALESTA_AI_VERSION ); ?><?php if ( $pro_active ) : ?> · Pro actif<?php endif; ?></span>
			<h1>Alesta AI</h1>
			<p><?php esc_html_e( 'Toolkit WordPress — SEO, performance, sécurité, GDPR, analytics. Architecture modulaire Free + extension Pro optionnelle propulsée par Claude (Anthropic).', 'alesta-ai' ); ?></p>
		</div>

		<!-- Stats -->
		<div class="alesta-dash-stats">
			<div class="alesta-dash-stat">
				<p class="alesta-dash-stat-label"><?php esc_html_e( 'Modules chargés', 'alesta-ai' ); ?></p>
				<p class="alesta-dash-stat-value"><?php echo (int) $total; ?></p>
				<p class="alesta-dash-stat-sub"><?php echo esc_html( sprintf( __( '%1$d Free · %2$d Pro', 'alesta-ai' ), $free_count, $pro_count ) ); ?></p>
			</div>
			<div class="alesta-dash-stat">
				<p class="alesta-dash-stat-label"><?php esc_html_e( 'Catégories actives', 'alesta-ai' ); ?></p>
				<p class="alesta-dash-stat-value"><?php echo (int) $cat_used; ?></p>
				<p class="alesta-dash-stat-sub"><?php echo esc_html( sprintf( __( 'sur %d disponibles', 'alesta-ai' ), count( $categories ) ) ); ?></p>
			</div>
			<div class="alesta-dash-stat">
				<p class="alesta-dash-stat-label"><?php esc_html_e( 'Extension Pro', 'alesta-ai' ); ?></p>
				<p class="alesta-dash-stat-value" style="color: <?php echo $pro_active ? '#16a34a' : '#94a3b8'; ?>;">
					<?php echo $pro_active ? esc_html__( '✓ Active', 'alesta-ai' ) : esc_html__( 'Non installée', 'alesta-ai' ); ?>
				</p>
				<p class="alesta-dash-stat-sub">
					<?php
					if ( $pro_active ) {
						echo esc_html__( 'License Galiance Hosting valide', 'alesta-ai' );
					} else {
						printf(
							/* translators: %s: link to alesta-ai.com */
							wp_kses_post( __( '<a href="%s" target="_blank" rel="noopener">Découvrir Alesta AI Pro →</a>', 'alesta-ai' ) ),
							esc_url( 'https://alesta-ai.com/tarifs.html' )
						);
					}
					?>
				</p>
			</div>
			<div class="alesta-dash-stat">
				<p class="alesta-dash-stat-label"><?php esc_html_e( 'Versions', 'alesta-ai' ); ?></p>
				<p class="alesta-dash-stat-value" style="font-size: 18px;"><?php echo esc_html( ALESTA_AI_VERSION ); ?><?php if ( defined( 'ALESTA_AI_PRO_VERSION' ) ) : ?> · <?php echo esc_html( ALESTA_AI_PRO_VERSION ); ?><?php endif; ?></p>
				<p class="alesta-dash-stat-sub"><?php esc_html_e( 'Free · Pro Addon', 'alesta-ai' ); ?></p>
			</div>
		</div>

		<!-- Cards par catégorie -->
		<div class="alesta-dash-grid">
			<?php foreach ( $categories as $cat_key => $cat_meta ) : ?>
				<?php
				$mods = $grouped[ $cat_key ] ?? [];
				if ( empty( $mods ) ) {
					continue;
				}
				$count_in_cat = count( $mods );
				?>
				<div class="alesta-dash-card">
					<div class="alesta-dash-card-head">
						<div class="alesta-dash-card-icon" style="background: <?php echo esc_attr( $cat_meta['color'] ); ?>;">
							<span class="dashicons dashicons-<?php echo esc_attr( $cat_meta['icon'] ); ?>" aria-hidden="true"></span>
						</div>
						<div>
							<p class="alesta-dash-card-title"><?php echo esc_html( $cat_meta['label'] ); ?></p>
							<p class="alesta-dash-card-count"><?php echo esc_html( sprintf( _n( '%d module', '%d modules', $count_in_cat, 'alesta-ai' ), $count_in_cat ) ); ?></p>
						</div>
					</div>
					<?php foreach ( $mods as $slug => $entry ) : ?>
						<?php
						$name = $entry['meta']['name'] ?? $slug;
						// On tente le lien sub-menu correspondant à ce slug si déclaré côté module.
						// Convention v2.0 : page = transformation slug "seo/sitemap" -> "alesta-ai-sitemap"
						// (les modules font add_submenu_page avec ce naming). Si non, lien dashboard.
						$short_slug = preg_replace( '#^.+/#', '', $slug );
						$page_slug  = 'alesta-ai-' . str_replace( '/', '-', $short_slug );
						$href       = admin_url( 'admin.php?page=' . $page_slug );
						$is_pro     = ( $entry['source'] ?? 'free' ) === 'pro';
						?>
						<div class="alesta-dash-mod">
							<a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $name ); ?></a>
							<span class="alesta-dash-mod-<?php echo $is_pro ? 'pro' : 'free'; ?>">
								<?php echo $is_pro ? 'PRO' : 'FREE'; ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Footer : liens utiles -->
		<div class="alesta-dash-footer">
			<div>
				<p class="alesta-dash-footer-title"><?php esc_html_e( 'Configuration & support', 'alesta-ai' ); ?></p>
				<p class="alesta-dash-footer-sub">
					<?php
					if ( $pro_active ) {
						esc_html_e( 'Plugin Alesta AI installé et actif sur ce site Galiance.', 'alesta-ai' );
					} else {
						esc_html_e( 'Activez Pro depuis votre cockpit Galiance pour débloquer toutes les fonctionnalités IA.', 'alesta-ai' );
					}
					?>
				</p>
			</div>
			<div style="display: flex; gap: 8px; flex-wrap: wrap;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-api-keys' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Clés API', 'alesta-ai' ); ?>
				</a>
				<a href="https://alesta-ai.com/docs" class="button button-secondary" target="_blank" rel="noopener">
					<?php esc_html_e( 'Documentation', 'alesta-ai' ); ?>
				</a>
				<?php if ( $pro_active ) : ?>
					<a href="https://app.galiance.fr/dashboard" class="button button-primary" target="_blank" rel="noopener">
						<?php esc_html_e( 'Cockpit Galiance', 'alesta-ai' ); ?>
					</a>
				<?php else : ?>
					<a href="https://alesta-ai.com/tarifs.html" class="button button-primary" target="_blank" rel="noopener">
						<?php esc_html_e( 'Passer à Pro', 'alesta-ai' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
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
