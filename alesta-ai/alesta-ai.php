<?php
/**
 * Plugin Name:       Alesta AI
 * Description:       All-in-one WordPress toolkit: XML sitemap, .htaccess cache/GZIP, robots.txt, broken-link scanner, GDPR banner, maintenance mode, and more.
 * Version:           1.2.6
 * Author:            Christian EL DEBS (Alesta Computer)
 * Author URI:        https://www.alesta-computer.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       alesta-ai
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

defined('ABSPATH') || exit;

// =============================================================================
// GARDE COHABITATION FREE / PREMIUM
// =============================================================================
// Si la version Premium d'Alesta AI a déjà été chargée (constante ALESTA_AI_DIR
// définie), on STOPPE NET avant toute déclaration de classe pour éviter le fatal
// "Cannot declare class … because the name is already in use".
//
// On désactive également le plugin courant + admin_notice en français.
if ( defined('ALESTA_AI_DIR') ) {
    add_action('admin_init', function() {
        if ( ! function_exists('deactivate_plugins') ) {
            // Loading a WP core admin include — ABSPATH is the canonical way
            // to reach wp-admin/includes/. WordPress core itself uses this idiom.
            require_once ABSPATH . 'wp-admin/includes/plugin.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
        }
        deactivate_plugins( plugin_basename(__FILE__), true );
    });
    add_action('admin_notices', function() {
        // Static, hardcoded admin notice — no user input. Output is fixed HTML.
        echo wp_kses_post(
            '<div class="notice notice-error"><p>'
          . '<strong>Alesta AI :</strong> la version <em>Premium</em> du plugin est déjà active. '
          . 'La version gratuite a été désactivée automatiquement pour éviter une erreur fatale. '
          . 'Vous pouvez la supprimer en toute sécurité depuis la page <em>Extensions</em>.'
          . '</p></div>'
        );
    });
    return; // Stoppe le chargement de cette instance.
}

define('ALESTA_AI_VERSION', '1.2.6');
define('ALESTA_AI_DIR',     plugin_dir_path(__FILE__));
define('ALESTA_AI_URL',     plugin_dir_url(__FILE__));

// Chargement sécurisé avec vérification d'existence
function alesta_ai_load_files() {
    $alesta_ai_files = [
        // Coeur
        'includes/class-pro-promo.php',
        'includes/class-api.php',
        'includes/class-admin.php',
        // SEO (Free)
        'includes/modules/seo/class-sitemap-module.php',
        'includes/modules/seo/class-admin-sitemap.php',
        // Performance (Free)
        'includes/modules/performance/class-htaccess-module.php',
        'includes/modules/performance/class-admin-htaccess.php',
        'includes/modules/performance/class-minify-module.php',
        'includes/modules/performance/class-fonts-module.php',
        'includes/modules/performance/class-admin-fonts.php',
        'includes/modules/performance/class-errors-module.php',
        'includes/modules/performance/class-admin-errors.php',
        'includes/modules/performance/class-robots-module.php',
        'includes/modules/performance/class-admin-robots.php',
        'includes/modules/performance/class-db-cleaner-module.php',
        'includes/modules/performance/class-admin-db-cleaner.php',
        'includes/modules/performance/class-maintenance-module.php',
        'includes/modules/performance/class-admin-maintenance.php',
        'includes/modules/performance/class-admin-debug.php',
        'includes/modules/performance/class-admin-health.php',
        // Sécurité (Free)
        'includes/modules/security/class-rgpd-module.php',
        'includes/modules/security/class-admin-rgpd.php',
        // Communication (Free) — Talk to Me 100% libre, sans clé API
        'includes/modules/communication/class-talk-to-me-module.php',
        'includes/modules/communication/class-admin-talk-to-me.php',
        // Réglages (Free)
        'includes/modules/settings/class-admin-budget.php',
    ];

    foreach ($alesta_ai_files as $alesta_ai_file) {
        $alesta_ai_path = ALESTA_AI_DIR . $alesta_ai_file;
        if (file_exists($alesta_ai_path)) {
            require_once $alesta_ai_path;
        } else {
            add_action('admin_notices', function() use ($alesta_ai_file) {
                // wp_kses_post on the full static envelope; the dynamic part
                // ($alesta_ai_file) is still independently escaped via esc_html.
                echo wp_kses_post(
                    '<div class="notice notice-error"><p><strong>Alesta AI :</strong> Fichier manquant : '
                    . esc_html($alesta_ai_file)
                    . '</p></div>'
                );
            });
        }
    }
}
alesta_ai_load_files();

add_action('plugins_loaded', function () {
    if (class_exists('Alesta_AI_Admin'))                new Alesta_AI_Admin();
    if (class_exists('Alesta_AI_Sitemap_Module'))       new Alesta_AI_Sitemap_Module();
    if (class_exists('Alesta_AI_Admin_Sitemap'))        new Alesta_AI_Admin_Sitemap();
    if (class_exists('Alesta_AI_Htaccess_Module'))      new Alesta_AI_Htaccess_Module();
    if (class_exists('Alesta_AI_Minify_Module'))        Alesta_AI_Minify_Module::init();
    if (class_exists('Alesta_AI_Fonts_Module'))         Alesta_AI_Fonts_Module::init();
    if (class_exists('Alesta_AI_Admin_Fonts'))          new Alesta_AI_Admin_Fonts();
    if (class_exists('Alesta_AI_Admin_Htaccess'))       new Alesta_AI_Admin_Htaccess();
    if (class_exists('Alesta_AI_Errors_Module'))        new Alesta_AI_Errors_Module();
    if (class_exists('Alesta_AI_Admin_Errors'))         new Alesta_AI_Admin_Errors();
    if (class_exists('Alesta_AI_Robots_Module'))        new Alesta_AI_Robots_Module();
    if (class_exists('Alesta_AI_Admin_Robots'))         new Alesta_AI_Admin_Robots();
    if (class_exists('Alesta_AI_Admin_DB_Cleaner'))     new Alesta_AI_Admin_DB_Cleaner();
    if (class_exists('Alesta_AI_Admin_Debug'))          new Alesta_AI_Admin_Debug();
    if (class_exists('Alesta_AI_Admin_Health'))         new Alesta_AI_Admin_Health();
    if (class_exists('Alesta_AI_Maintenance_Module'))   new Alesta_AI_Maintenance_Module();
    if (class_exists('Alesta_AI_RGPD_Module'))          Alesta_AI_RGPD_Module::init();
    if (class_exists('Alesta_AI_Admin_RGPD'))           new Alesta_AI_Admin_RGPD();
    if (class_exists('Alesta_AI_Admin_Budget'))         new Alesta_AI_Admin_Budget();
    // Communication — Talk to Me (100% gratuit dans la Free)
    if (class_exists('Alesta_AI_TalkToMe_Module'))      Alesta_AI_TalkToMe_Module::init();
    if (class_exists('Alesta_AI_Admin_TalkToMe'))       new Alesta_AI_Admin_TalkToMe();
});

function alesta_ai_menu_icon(): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
         . '<text x="10" y="17" text-anchor="middle" font-family="Georgia,serif" font-size="19" fill="#a0aec0">&#x03C6;</text>'
         . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
