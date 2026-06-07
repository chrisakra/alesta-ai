<?php
/**
 * Plugin Name:       Alesta AI Pro
 * Plugin URI:        https://alesta-ai.com/tarifs.html
 * Description:       Premium AI features for Alesta AI — Claude-powered content generation, SEO automation, image AI and more. Requires Alesta AI (free).
 * Version:           2.0.6
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

define( 'ALESTA_AI_PRO_VERSION', '2.0.6' );
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
	// 0. GlitchTip Reporter (opt-in via constante ALESTA_GLITCHTIP_DSN dans wp-config).
	//    Désactivé par défaut. Envoie les fatals/exceptions PHP du plugin au
	//    serveur GlitchTip de Galiance pour monitoring centralisé.
	//    Injecté automatiquement par Galiance worker sur les sites hébergés.
	if ( class_exists( '\\AlestaAIPro\\ErrorReporter\\GlitchTipReporter' ) ) {
		$reporter = \AlestaAIPro\ErrorReporter\GlitchTipReporter::instance();
		if ( $reporter ) {
			$reporter->init();
		}
	}

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
		require_once ALESTA_AI_PRO_DIR . 'includes/providers/class-claude-provider.php';
		$providers['claude'] = new \AlestaAIPro\Providers\ClaudeProvider();
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

	// SEO additionnels (3)
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/seo/class-ai-metadata-module.php';
	$registry->register_pro( 'seo/ai-metadata', \AlestaAIPro\Modules\Seo\AiMetadataModule::class, [
		'name' => 'AI Metadata', 'category' => 'seo', 'icon' => 'admin-customizer',
		'description' => 'OG, Twitter Cards, schema.org, alt text via Claude',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/seo/class-duplicates-ai-module.php';
	$registry->register_pro( 'seo/duplicates', \AlestaAIPro\Modules\Seo\DuplicatesAIModule::class, [
		'name' => 'Détection duplicates SEO', 'category' => 'seo', 'icon' => 'admin-page',
		'description' => 'Détection IA contenus similaires + suggestions canonical/merge',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/seo/class-llms-txt-ai-module.php';
	$registry->register_pro( 'seo/llms-txt', \AlestaAIPro\Modules\Seo\LlmsTxtAIModule::class, [
		'name' => 'LLMs.txt AI', 'category' => 'seo', 'icon' => 'text',
		'description' => 'llms.txt enrichi par Claude (descriptions + hiérarchie pages)',
	] );

	// CONTENT additionnels (5)
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/content/class-editorial-module.php';
	$registry->register_pro( 'content/editorial', \AlestaAIPro\Modules\Content\EditorialModule::class, [
		'name' => 'Calendrier éditorial', 'category' => 'content', 'icon' => 'calendar-alt',
		'description' => 'Idées d\'articles IA basées sur tendances + gaps SEO',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/content/class-chatbot-module.php';
	$registry->register_pro( 'content/chatbot', \AlestaAIPro\Modules\Content\ChatbotModule::class, [
		'name' => 'Chatbot Claude', 'category' => 'content', 'icon' => 'format-chat',
		'description' => 'Widget chatbot frontend qui répond aux visiteurs',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/content/class-comments-module.php';
	$registry->register_pro( 'content/comments', \AlestaAIPro\Modules\Content\CommentsModule::class, [
		'name' => 'Modération commentaires', 'category' => 'content', 'icon' => 'admin-comments',
		'description' => 'Filtre spam/toxic + suggestions de réponse via Claude',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/content/class-tags-module.php';
	$registry->register_pro( 'content/tags', \AlestaAIPro\Modules\Content\TagsModule::class, [
		'name' => 'Tags AI suggestions', 'category' => 'content', 'icon' => 'tag',
		'description' => 'Suggère les meilleurs tags WordPress par post via Claude',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/content/class-translation-module.php';
	$registry->register_pro( 'content/translation', \AlestaAIPro\Modules\Content\TranslationModule::class, [
		'name' => 'Traduction 20 langues', 'category' => 'content', 'icon' => 'translation',
		'description' => 'Traduction automatique posts/pages via Claude',
	] );

	// MEDIA (1 module SPLIT — couche IA)
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/media/class-filenames-ai-module.php';
	$registry->register_pro( 'media/filenames-ai', \AlestaAIPro\Modules\Media\FilenamesAIModule::class, [
		'name' => 'Filenames AI', 'category' => 'media', 'icon' => 'format-image',
		'description' => 'Suggère 3 noms SEO par image via Claude vision',
	] );

	// PERFORMANCE (2 modules SPLIT — couches IA)
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/performance/class-redirects-ai-module.php';
	$registry->register_pro( 'performance/redirects-ai', \AlestaAIPro\Modules\Performance\RedirectsAIModule::class, [
		'name' => 'Redirections IA', 'category' => 'performance', 'icon' => 'redo',
		'description' => 'Suggère redirect 301 cible quand 404 détecté',
	] );
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/performance/class-perf-audit-ai-module.php';
	$registry->register_pro( 'performance/perf-audit-ai', \AlestaAIPro\Modules\Performance\PerfAuditAIModule::class, [
		'name' => 'Audit perf priorisé IA', 'category' => 'performance', 'icon' => 'performance',
		'description' => 'Top 3 actions à plus fort impact via Claude',
	] );

	// REPORTS (1)
	require_once ALESTA_AI_PRO_DIR . 'includes/modules-pro/reports/class-pdf-report-module.php';
	$registry->register_pro( 'reports/pdf', \AlestaAIPro\Modules\Reports\PdfReportModule::class, [
		'name' => 'Rapports PDF mensuels', 'category' => 'reports', 'icon' => 'pdf',
		'description' => 'PDF mensuel SEO/perf/sécu + synthèse exécutive IA',
	] );

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
