<?php
/**
 * Plugin Name:       Alesta AI Pro
 * Plugin URI:        https://www.alesta-computer.com/alesta-ai-pro
 * Description:       Premium AI features for Alesta AI — Claude-powered content generation, SEO automation, image AI and more. Requires Alesta AI (free).
 * Version:           2.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  alesta-ai
 * Author:            Christian EL DEBS (Alesta Computer)
 * Author URI:        https://www.alesta-computer.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       alesta-ai-pro
 *
 * @package AlestaAIPro
 *
 * ==========================================================================
 * RELATION AU PLUGIN FREE
 * ==========================================================================
 *
 * Ce plugin est un ADDON du plugin "Alesta AI" (gratuit, wordpress.org).
 * Il s'accroche à `do_action('alesta_ai/loaded')` pour enregistrer :
 *   - Ses providers IA (Claude, à terme OpenAI/Mistral)
 *   - Ses modules payants (improve, summaries, editorial, chatbot…)
 *   - Ses overrides de modules Free (keywords statique → keywords IA, etc.)
 *
 * Aucun fichier Free n'est dupliqué ici. Le Pro vit dans son propre namespace
 * (\AlestaAIPro\) pour éviter toute collision.
 *
 * Header "Requires Plugins" (WP 6.5+) :
 *   - Bloque l'activation du Pro si le Free n'est pas installé
 *   - Désactive automatiquement le Pro si le Free est désactivé/supprimé
 *
 * Polyfill ci-dessous pour les sites en WP < 6.5.
 */

defined( 'ABSPATH' ) || exit;

// =============================================================================
// CONSTANTES
// =============================================================================

define( 'ALESTA_AI_PRO_VERSION', '2.0.0' );
define( 'ALESTA_AI_PRO_FILE',    __FILE__ );
define( 'ALESTA_AI_PRO_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ALESTA_AI_PRO_URL',     plugin_dir_url( __FILE__ ) );

// =============================================================================
// POLYFILL "Requires Plugins" pour WP < 6.5
// =============================================================================
//
// Sur WP 6.5+, le header "Requires Plugins" empêche déjà l'activation du Pro
// sans le Free. Mais sur WP 6.0-6.4, ce header est ignoré silencieusement —
// on simule donc le check à plugins_loaded en priorité 1.

add_action( 'plugins_loaded', function () {
	$free_loaded = defined( 'ALESTA_AI_VERSION' ) && class_exists( 'AlestaAI\\Core\\ExtensionsAPI' );
	if ( $free_loaded ) {
		return;
	}

	// Free absent : on désactive le Pro et on affiche une notice avec lien d'install.
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	deactivate_plugins( plugin_basename( __FILE__ ) );

	add_action( 'admin_notices', function () {
		$install_url = wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=alesta-ai' ),
			'install-plugin_alesta-ai'
		);
		echo '<div class="notice notice-error"><p><strong>Alesta AI Pro</strong> requires the free plugin <strong>Alesta AI</strong>. '
		   . '<a href="' . esc_url( $install_url ) . '" class="button button-primary" style="margin-left:8px">Install Alesta AI</a></p></div>';
	} );

	// Stoppe net le chargement du Pro pour cette requête.
	return;
}, 1 );

// =============================================================================
// AUTOLOAD (même pattern que le Free, namespace \AlestaAIPro\)
// =============================================================================

spl_autoload_register( function ( $class ) {
	if ( strpos( $class, 'AlestaAIPro\\' ) !== 0 ) {
		return;
	}
	$relative  = substr( $class, strlen( 'AlestaAIPro\\' ) );
	$parts     = explode( '\\', $relative );
	$classname = array_pop( $parts );
	$dir       = strtolower( implode( DIRECTORY_SEPARATOR, $parts ) );
	$filename  = 'class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $classname ) ) . '.php';
	$path      = ALESTA_AI_PRO_DIR . 'includes' . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $filename;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
} );

// =============================================================================
// BOOTSTRAP — s'accroche à `alesta_ai/loaded`
// =============================================================================
//
// Priorité 10 : laisse au Free le temps de finir son init. Le Pro ne doit JAMAIS
// faire d'init lourd avant ce point (sinon il casse en cas de Free downgrade
// ou de race condition au boot WP).

add_action( 'alesta_ai/loaded', function () {
	// 1. Init License Manager + Update Checker (consommateurs des endpoints Galiance)
	//    LicenseManager check toutes les 12h /api/license/verify
	//    UpdateChecker check toutes les 12h /api/alesta-ai-pro/latest
	if ( class_exists( '\\AlestaAIPro\\License\\LicenseManager' ) ) {
		\AlestaAIPro\License\LicenseManager::instance()->init();
	}
	if ( class_exists( '\\AlestaAIPro\\License\\UpdateChecker' ) ) {
		\AlestaAIPro\License\UpdateChecker::instance()->init();
	}

	// 2. Bootstrap des providers IA — branchés sur le hook public du Free.
	//    BYOK : le ClaudeProvider lit la clé Anthropic du user depuis le Vault
	//    Free (APIKeyVault::get('anthropic')), pas une clé hébergée Alesta.
	add_filter( 'alesta_ai/ai/providers', function ( $providers ) {
		// require_once ALESTA_AI_PRO_DIR . 'includes/providers/class-claude-provider.php';
		// $providers['claude'] = new \AlestaAIPro\Providers\ClaudeProvider();
		// TODO: réactiver quand la classe ClaudeProvider sera migrée depuis class-api.php
		return $providers;
	} );

	// 3. Déclare les features Pro pour la page "Découvrir Pro" du Free.
	add_filter( 'alesta_ai/pro/features', function ( $features ) {
		// Format : ['feature-slug' => ['label' => ..., 'description' => ..., 'icon' => ...]]
		// À compléter lors de la migration des modules.
		return $features;
	} );

	// 4. Enregistre les modules Pro dans le registry.
	$registry = \AlestaAI\Core\ModuleRegistry::instance();

	// SEO (4 modules pilotes)
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/seo/class-keywords-ai-module.php';
	$registry->register_pro( 'seo/keywords-ai', \AlestaAIPro\Modules\Seo\KeywordsAIModule::class, [
		'name' => 'Keywords AI', 'category' => 'seo', 'icon' => 'admin-network',
		'description' => 'Génération de keywords sémantiques via Claude',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/seo/class-meta-ai-module.php';
	$registry->register_pro( 'seo/meta-ai', \AlestaAIPro\Modules\Seo\MetaAIModule::class, [
		'name' => 'Meta AI', 'category' => 'seo', 'icon' => 'edit',
		'description' => 'Génère titles + meta descriptions optimisés via Claude',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/seo/class-schema-ai-module.php';
	$registry->register_pro( 'seo/schema-ai', \AlestaAIPro\Modules\Seo\SchemaAIModule::class, [
		'name' => 'Schema.org IA', 'category' => 'seo', 'icon' => 'editor-code',
		'description' => 'JSON-LD Schema.org enrichi par Claude (Article, Product, FAQ, HowTo)',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/seo/class-faq-ai-module.php';
	$registry->register_pro( 'seo/faq-ai', \AlestaAIPro\Modules\Seo\FaqAIModule::class, [
		'name' => 'FAQ IA', 'category' => 'seo', 'icon' => 'editor-help',
		'description' => '5-10 Q/R pertinentes via Claude + JSON-LD FAQPage',
	] );

	// CONTENT (2 modules pilotes)
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/content/class-improve-module.php';
	$registry->register_pro( 'content/improve', \AlestaAIPro\Modules\Content\ImproveModule::class, [
		'name' => 'Améliorer texte IA', 'category' => 'content', 'icon' => 'edit-large',
		'description' => 'Sidebar Gutenberg avec actions Améliorer/Simplifier/Résumer/Étendre',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/content/class-summaries-module.php';
	$registry->register_pro( 'content/summaries', \AlestaAIPro\Modules\Content\SummariesModule::class, [
		'name' => 'Résumés auto', 'category' => 'content', 'icon' => 'media-text',
		'description' => 'TL;DR auto en début de post long, cacheé en post_meta',
	] );

	// TODO Phase S3 : 11 autres modules Pro (ai-metadata, llms-txt, duplicates, chatbot,
	// comments, editorial, templates, tags, translation, filenames-ai, pdf-report)

	// 5. Injection UI dans les modules SPLIT du Free.
	//    Exemple — bouton "✨ Suggérer keywords IA" injecté sous le tableau Free de keywords :
	//
	// add_action( 'alesta_ai/admin/seo-keywords/actions', function ( $context ) {
	// 	$post_id = $context['post_id'] ?? 0;
	// 	echo '<button class="button button-primary alesta-ai-suggest-keywords" data-post="' . esc_attr( $post_id ) . '">✨ Suggérer via IA</button>';
	// } );

	// 6. Migration script — auto-exécuté la 1ère fois après upgrade depuis 1.3.x.
	if ( ! get_option( 'alesta_ai_pro_v2_migrated' ) ) {
		// require_once ALESTA_AI_PRO_DIR . 'includes/migrations/class-migration-2-0-0.php';
		// \AlestaAIPro\Migrations\Migration_2_0_0::run();
	}
}, 10 );

// =============================================================================
// HOOKS DE CYCLE DE VIE
// =============================================================================

register_activation_hook( __FILE__, function () {
	// Marqueur pour le script de migration : "le Pro vient juste d'être activé".
	if ( ! get_option( 'alesta_ai_pro_first_activated_at' ) ) {
		update_option( 'alesta_ai_pro_first_activated_at', current_time( 'mysql' ), false );
	}
} );
