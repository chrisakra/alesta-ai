<?php
/**
 * Alesta AI Free — Module Loader (v2.0)
 *
 * Charge tous les modules FREE et les enregistre dans le ModuleRegistry.
 *
 * Pattern : chaque module a sa classe dans includes/modules/{category}/class-{name}.php
 * et expose une méthode statique register() qui crée l'instance + l'enregistre.
 *
 * Lazy loading : les modules sont déclarés ici mais leur logique métier
 * (hooks WP, REST endpoints...) ne tourne que quand WP en a besoin.
 *
 * @package AlestaAI
 */

defined( 'ABSPATH' ) || exit;

use AlestaAI\Core\ModuleRegistry;

$registry = ModuleRegistry::instance();

// =============================================================================
// SEO (5 modules Free)
// =============================================================================
require_once ALESTA_AI_DIR . 'includes/modules/seo/class-sitemap.php';
$registry->register( 'seo/sitemap', \AlestaAI\Modules\Seo\Sitemap::class, [
	'name'        => 'Sitemap XML',
	'description' => 'Génération automatique du sitemap.xml (posts, pages, taxonomies).',
	'category'    => 'seo',
	'icon'        => 'sitemap',
] );

// =============================================================================
// PERFORMANCE (12 modules Free)
// =============================================================================
require_once ALESTA_AI_DIR . 'includes/modules/performance/class-htaccess.php';
$registry->register( 'performance/htaccess', \AlestaAI\Modules\Performance\Htaccess::class, [
	'name' => '.htaccess editor', 'category' => 'performance', 'icon' => 'admin-tools',
	'description' => 'Cache navigateur, GZIP, sécurité HTTPS, WebP rewrite.',
] );
require_once ALESTA_AI_DIR . 'includes/modules/performance/class-minify.php';
$registry->register( 'performance/minify', \AlestaAI\Modules\Performance\Minify::class, [
	'name' => 'Minify HTML/CSS/JS', 'category' => 'performance', 'icon' => 'editor-code',
	'description' => 'Minification HTML, combine CSS/JS, défer scripts.',
] );
require_once ALESTA_AI_DIR . 'includes/modules/performance/class-db-cleaner.php';
$registry->register( 'performance/db-cleaner', \AlestaAI\Modules\Performance\DbCleaner::class, [
	'name' => 'DB Cleaner', 'category' => 'performance', 'icon' => 'database',
	'description' => 'Nettoyage hebdo : révisions, transients, spam/trash, drafts orphelins.',
] );
// TODO Phase S3 : fonts, errors, maintenance, debug, health, cwv, search-replace, redirects-core, scripts-core, perf-audit-core

// =============================================================================
// SECURITY (6 modules Free)
// =============================================================================
require_once ALESTA_AI_DIR . 'includes/modules/security/class-rgpd.php';
$registry->register( 'security/rgpd', \AlestaAI\Modules\Security\Rgpd::class, [
	'name' => 'RGPD — Bannière cookies', 'category' => 'security', 'icon' => 'shield',
	'description' => 'Bannière de consentement personnalisable, conforme RGPD/CNIL.',
] );
require_once ALESTA_AI_DIR . 'includes/modules/security/class-brute-force.php';
$registry->register( 'security/brute-force', \AlestaAI\Modules\Security\BruteForce::class, [
	'name' => 'Brute Force Protection', 'category' => 'security', 'icon' => 'lock',
	'description' => 'Rate limit login : max 5 tentatives / 15 min / IP, ban automatique.',
] );
require_once ALESTA_AI_DIR . 'includes/modules/security/class-security-audit.php';
$registry->register( 'security/audit', \AlestaAI\Modules\Security\SecurityAudit::class, [
	'name' => 'Security Audit', 'category' => 'security', 'icon' => 'shield-alt',
	'description' => 'Checklist audit sécurité WordPress (file perms, WP_DEBUG, user admin, DB prefix).',
] );
// =============================================================================
// MEDIA (1 module Free)
// =============================================================================
require_once ALESTA_AI_DIR . 'includes/modules/media/class-webp.php';
$registry->register( 'media/webp', \AlestaAI\Modules\Media\Webp::class, [
	'name' => 'WebP — Conversion images', 'category' => 'media', 'icon' => 'format-image',
	'description' => 'Conversion automatique JPEG/PNG → WebP via GD/Imagick + rewrite rules.',
] );
// TODO Phase S3 : activity, login-bot, updates (security)

// =============================================================================
// CONTENT (2 modules Free) — TODO Phase S3
// =============================================================================
// $registry->register( 'content/duplicate', ... );
// $registry->register( 'content/templates', ... );

// =============================================================================
// MEDIA (1 module Free + 1 SPLIT) — TODO Phase S3
// =============================================================================
// $registry->register( 'media/webp', ... );
// $registry->register( 'media/filenames-core', ... );

// =============================================================================
// COMMUNICATION (1 module Free) — TODO Phase S3
// =============================================================================
// $registry->register( 'communication/talk-to-me', ... );

// =============================================================================
// REPUTATION (2 modules Free) — TODO Phase S3
// =============================================================================
// $registry->register( 'reputation/reviews-google', ... );
// $registry->register( 'reputation/trustpilot', ... );

// =============================================================================
// SETTINGS (2 modules Free)
// =============================================================================
require_once ALESTA_AI_DIR . 'includes/modules/settings/class-alerts.php';
$registry->register( 'settings/alerts', \AlestaAI\Modules\Settings\Alerts::class, [
	'name'        => 'Alerts cron',
	'description' => 'Notifications email automatiques : site down, SSL expire, disk full, brute-force.',
	'category'    => 'settings',
	'icon'        => 'megaphone',
] );
// TODO Phase S3 : roles

// =============================================================================
// DASHBOARD (1 widget — pas dans modules/, dans dashboard/)
// =============================================================================
require_once ALESTA_AI_DIR . 'includes/dashboard/class-dashboard-widget.php';
new \AlestaAI\Dashboard\DashboardWidget();
