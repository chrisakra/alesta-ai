<?php
defined('ABSPATH') || exit;

class Alesta_AI_Admin {

    public function __construct() {
        add_action('admin_menu',                   [$this, 'register_menu']);
        add_action('admin_enqueue_scripts',        [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts',        [$this, 'enqueue_global_menu_assets']);
        add_action('wp_ajax_alesta_test_api',      [$this, 'ajax_test_api']);
        add_action('wp_ajax_alesta_save_settings', [$this, 'ajax_save_settings']);
    }

    /**
     * Loads the small CSS used by the sidebar admin menu badges and the
     * "Passer à Pro" CTA. Loaded on every admin page because the sidebar
     * is always visible.
     */
    public function enqueue_global_menu_assets(): void {
        wp_enqueue_style(
            'alesta-ai-admin-menu',
            ALESTA_AI_URL . 'assets/admin-menu.css',
            [],
            ALESTA_AI_VERSION
        );
        wp_enqueue_script(
            'alesta-ai-admin-menu',
            ALESTA_AI_URL . 'assets/admin-menu.js',
            [],
            ALESTA_AI_VERSION,
            true
        );
    }

    // -------------------------------------------------------------------------
    // MENU
    // -------------------------------------------------------------------------

    public function register_menu(): void {
        add_menu_page(
            'Alesta AI', 'Alesta AI', 'manage_options', 'alesta-ai',
            [$this, 'page_dashboard'], alesta_ai_menu_icon(), 30
        );

        add_submenu_page('alesta-ai', 'Tableau de bord', 'Tableau de bord', 'manage_options', 'alesta-ai', [$this, 'page_dashboard']);

        // 01 SEO — Free
        add_submenu_page('alesta-ai', 'SEO', 'SEO & Referencement', 'manage_options', 'alesta-ai-seo', [$this, 'page_coming_soon']);
        add_submenu_page('alesta-ai', 'Sitemap XML', '- Sitemap XML', 'manage_options', 'alesta-ai-sitemap', function(){ (new Alesta_AI_Admin_Sitemap())->render_page(); });
        // 01 SEO — Pro features (info only)
        add_submenu_page('alesta-ai', 'Title Meta', '- Title &amp; Meta + Audit SEO <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-meta', function(){ Alesta_AI_Pro_Promo::render('Title & Meta + Audit SEO', 'Génération en lot des titres et méta-descriptions par Claude, et audit SEO complet avec score.', '📝'); });
        add_submenu_page('alesta-ai', 'FAQ Schema', '- FAQ Schema <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-faq', function(){ Alesta_AI_Pro_Promo::render('FAQ Schema JSON-LD', 'Génération automatique des données structurées FAQ pour les rich snippets Google.', '❓'); });
        add_submenu_page('alesta-ai', 'Mots cles', '- Mots-cles <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-keywords', function(){ Alesta_AI_Pro_Promo::render('Analyse de mots-clés', 'Densité, synonymes LSI et recherche de mots-clés assistée par Claude.', '🔑'); });
        add_submenu_page('alesta-ai', 'LLMs.txt', '- LLMs.txt pour IA <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-llms', function(){ Alesta_AI_Pro_Promo::render('Générateur LLMs.txt', 'Améliorez la visibilité sur les moteurs IA : ChatGPT, Claude, Gemini et autres.', '🤖'); });
        add_submenu_page('alesta-ai', 'AI Metadata', '- AI Metadata Generator <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-ai-metadata', function(){ Alesta_AI_Pro_Promo::render('Générateur de métadonnées IA', 'Balises meta optimisées pour les crawlers IA.', '🧠'); });
        add_submenu_page('alesta-ai', 'Contenu duplique', '- Detecteur contenu duplique <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-duplicates', function(){ Alesta_AI_Pro_Promo::render('Détecteur de contenu dupliqué', 'Détection et alertes sur les contenus similaires de votre site.', '📋'); });
        add_submenu_page('alesta-ai', 'Reglages SEO', '- Reglages SEO natif <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-seo-settings', function(){ Alesta_AI_Pro_Promo::render('Réglages SEO natifs', 'Configuration avancée du moteur SEO intégré.', '⚙️'); });
        add_submenu_page('alesta-ai', 'Schema', '- Donnees structurees <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', 'manage_options', 'alesta-ai-schema', function(){ Alesta_AI_Pro_Promo::render('Données structurées', 'Article, Product, Organization, LocalBusiness… Claude détecte automatiquement le bon type par page.', '🏷️'); });

        // 02 Content — Free header
        add_submenu_page('alesta-ai', 'Contenu', 'Contenu & Redaction', 'manage_options', 'alesta-ai-content', [$this, 'page_coming_soon']);
        // 02 Content — Pro features (info only)
        add_submenu_page('alesta-ai', 'Amelioration', '- Amelioration texte <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-improve', function(){ Alesta_AI_Pro_Promo::render('Amélioration de texte', 'Reformuler, simplifier ou enrichir vos contenus existants avec Claude.', '✨'); });
        add_submenu_page('alesta-ai', 'Editorial', '- Plan editorial <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-editorial', function(){ Alesta_AI_Pro_Promo::render('Plan éditorial', 'Calendrier d\'articles généré sur 1 à 3 mois.', '📅'); });
        add_submenu_page('alesta-ai', 'Resumes', '- Resumes auto <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-summaries', function(){ Alesta_AI_Pro_Promo::render('Résumés automatiques', 'Extraits de 2-3 phrases pour tous vos articles et pages.', '📄'); });
        add_submenu_page('alesta-ai', 'Commentaires', '- Commentaires <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', 'manage_options', 'alesta-ai-comments', function(){ Alesta_AI_Pro_Promo::render('Modération IA des commentaires', 'Classification automatique par Claude : spam, toxique, légitime.', '💬'); });
        add_submenu_page('alesta-ai', 'Tags', '- Tags auto <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-tags', function(){ Alesta_AI_Pro_Promo::render('Tags automatiques', 'Catégories et étiquettes appliquées automatiquement par Claude.', '🏷️'); });

        // 03 Media — Pro features (info only)
        add_submenu_page('alesta-ai', 'Medias', 'Medias & Images', 'manage_options', 'alesta-ai-media', [$this, 'page_coming_soon']);
        add_submenu_page('alesta-ai', 'Images', '- Traitement images <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-images', function(){ Alesta_AI_Pro_Promo::render('Traitement IA des images', 'Texte alternatif, titre, légende et description générés par Claude.', '🖼️'); });
        add_submenu_page('alesta-ai', 'Fichiers', '- Nommage fichiers <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-filenames', function(){ Alesta_AI_Pro_Promo::render('Nommage SEO des fichiers', 'Audit SEO et renommage des fichiers images.', '💾'); });
        add_submenu_page('alesta-ai', 'WebP', '- Conversion WebP <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-webp', function(){ Alesta_AI_Pro_Promo::render('Conversion WebP', 'Optimisation automatique des images au format WebP.', '⚡'); });

        // 04 Performance — Free
        add_submenu_page('alesta-ai', 'Performance', 'Performance & Optimisation', 'manage_options', 'alesta-ai-perf', [$this, 'page_coming_soon']);
        add_submenu_page('alesta-ai', 'Htaccess', '- Optimisation Gzip, Cache, HTTPS', 'manage_options', 'alesta-ai-cache', function(){ (new Alesta_AI_Admin_Htaccess())->render_page('cache'); });
        add_submenu_page('alesta-ai', 'Robots.txt', '- Robots.txt', 'manage_options', 'alesta-ai-robots', function(){ (new Alesta_AI_Admin_Robots())->render_page(); });
        add_submenu_page('alesta-ai', 'Erreurs', '- Erreurs 4xx / 5xx', 'manage_options', 'alesta-ai-links', function(){ (new Alesta_AI_Admin_Errors())->render_page(); });
        add_submenu_page('alesta-ai', 'Nettoyeur BDD', '- Nettoyeur BDD planifie', 'manage_options', 'alesta-ai-db-cleaner', function(){ (new Alesta_AI_Admin_DB_Cleaner())->render_page(); });
        add_submenu_page('alesta-ai', 'Google Fonts RGPD', '- Optimiseur Google Fonts RGPD', 'manage_options', 'alesta-ai-fonts', function(){ (new Alesta_AI_Admin_Fonts())->render_page(); });
        add_submenu_page('alesta-ai', 'Maintenance', '- Mode Maintenance', 'manage_options', 'alesta-ai-maintenance', function(){ (new Alesta_AI_Admin_Maintenance())->render_page(); });
        // 04 Performance — Pro features (info only)
        add_submenu_page('alesta-ai', 'Web Vitals', '- Core Web Vitals <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-cwv', function(){ Alesta_AI_Pro_Promo::render('Moniteur Core Web Vitals', 'LCP, CLS et INP en temps réel via l\'API PageSpeed Insights.', '📊'); });
        add_submenu_page('alesta-ai', 'Audit Perf', '- Audit et recommandations <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-perf-audit', function(){ Alesta_AI_Pro_Promo::render('Audit de performance', 'Analyse approfondie avec recommandations Claude.', '🔍'); });
        add_submenu_page('alesta-ai', 'Scripts bloquants', '- Detecteur scripts bloquants <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-scripts', function(){ Alesta_AI_Pro_Promo::render('Détecteur de scripts bloquants', 'Identification et conseils defer/async.', '🔁'); });
        add_submenu_page('alesta-ai', 'Redirections 404', '- Redirections 404 auto <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-redirects', function(){ Alesta_AI_Pro_Promo::render('Redirections 404 intelligentes', 'Détection et suggestions IA pour les pages introuvables.', '🔄'); });

        // 05 AI — Pro features (info only) — Chatbot déplacé vers "Communication"
        add_submenu_page('alesta-ai', 'IA', 'IA & Automatisation', 'manage_options', 'alesta-ai-automation', [$this, 'page_coming_soon']);
        add_submenu_page('alesta-ai', 'Traduction', '- Traduction IA <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', 'manage_options', 'alesta-ai-translate', function(){ Alesta_AI_Pro_Promo::render('Traduction IA', '20 langues prises en charge via Claude Opus.', '🌐'); });

        // 06 Security — Free
        add_submenu_page('alesta-ai', 'Sécurité', 'Sécurité & Conformité', 'manage_options', 'alesta-ai-security-section', [$this, 'page_coming_soon']);
        add_submenu_page('alesta-ai', 'Health Check', '- Health Check Dashboard', 'manage_options', 'alesta-ai-health', function(){ (new Alesta_AI_Admin_Health())->render_page(); });
        add_submenu_page('alesta-ai', 'Bannière RGPD', '- Bannière RGPD souveraine', 'manage_options', 'alesta-ai-rgpd', function(){ (new Alesta_AI_Admin_RGPD())->render_page(); });
        // 06 Security — Pro features (info only)
        add_submenu_page('alesta-ai', 'Audit Sécurité', '- Audit sécurité passif <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-security-audit', function(){ Alesta_AI_Pro_Promo::render('Audit de sécurité passif', 'Fichiers exposés, tentatives de connexion, permissions.', '🛡️'); });
        add_submenu_page('alesta-ai', 'Activité admin', '- Journal activité admin <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-activity', function(){ Alesta_AI_Pro_Promo::render('Journal d\'activité admin', 'Historique complet des actions administrateur.', '📖'); });
        add_submenu_page('alesta-ai', 'Mises à jour', '- Mises à jour planifiées <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', 'manage_options', 'alesta-ai-updates', function(){ Alesta_AI_Pro_Promo::render('Mises à jour planifiées', 'Plugins, thèmes et cœur WordPress via WP Cron.', '📦'); });

        // 07 Reports — Pro features (info only)
        add_submenu_page('alesta-ai', 'Rapports', 'Rapports', 'manage_options', 'alesta-ai-reports', [$this, 'page_coming_soon']);
        add_submenu_page('alesta-ai', 'Dashboard SEO', '- Dashboard SEO <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', 'manage_options', 'alesta-ai-dashboard-seo', function(){ Alesta_AI_Pro_Promo::render('Tableau de bord SEO global', 'Score SEO de toutes vos pages en un coup d\'œil.', '📈'); });
        add_submenu_page('alesta-ai', 'Rapport PDF', '- Rapport PDF <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', 'manage_options', 'alesta-ai-pdf', function(){ Alesta_AI_Pro_Promo::render('Rapport PDF mensuel', 'Synthèse automatique générée par Claude.', '📑'); });
        add_submenu_page('alesta-ai', 'Alertes', '- Alertes automatiques <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', 'manage_options', 'alesta-ai-alerts', function(){ Alesta_AI_Pro_Promo::render('Alertes automatiques', '7 types de surveillance envoyés par e-mail.', '🔔'); });

        // 08 Avis & Réputation — Pro features (info only)
        add_submenu_page('alesta-ai', 'Avis', 'Avis', 'manage_options', 'alesta-ai-reviews-section', [$this, 'page_coming_soon']);
        add_submenu_page('alesta-ai', 'Avis Google', '- Google <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-reviews', function(){ Alesta_AI_Pro_Promo::render('Avis Google', 'Récupération automatique de vos avis Google et affichage via shortcode (carousel, grille, liste, masonry).', '⭐'); });
        add_submenu_page('alesta-ai', 'Avis Trustpilot', '- Trustpilot <span class="alesta-pro-pill">Solo</span>', 'manage_options', 'alesta-ai-reviews-trustpilot', function(){ Alesta_AI_Pro_Promo::render('Avis Trustpilot', 'Récupération automatique de vos avis Trustpilot — bientôt disponible.', '📝'); });

        // 09 Communication — Talk to Me (Free, fonctionnel) + Chatbot (Pro promo)
        add_submenu_page('alesta-ai', 'Communication', 'Communication', 'manage_options', 'alesta-ai-communication-section', [$this, 'page_coming_soon']);
        add_submenu_page('alesta-ai', 'Talk to Me', '- Talk to Me', 'manage_options', 'alesta-ai-talk-to-me', function(){ (new Alesta_AI_Admin_TalkToMe())->render_page(); });
        add_submenu_page('alesta-ai', 'Chatbot', '- Chatbot IA <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', 'manage_options', 'alesta-ai-chatbot', function(){ Alesta_AI_Pro_Promo::render('Chatbot IA visiteurs', 'Widget front-end propulsé par Claude Haiku.', '💬'); });

        // 10 Settings — Free
        add_submenu_page('alesta-ai', 'Reglages', 'Reglages', 'manage_options', 'alesta-ai-settings-section', [$this, 'page_coming_soon']);
        add_submenu_page('alesta-ai', 'Debug Manager', '- Debug Manager', 'manage_options', 'alesta-ai-debug', function(){ (new Alesta_AI_Admin_Debug())->render_page(); });
        add_submenu_page('alesta-ai', 'Configuration', '- Configuration', 'manage_options', 'alesta-ai-settings', [$this, 'page_settings']);
        add_submenu_page('alesta-ai', 'Budget', '- Budget API', 'manage_options', 'alesta-ai-budget', function(){ (new Alesta_AI_Admin_Budget())->render_page(); });
        // 10 Settings — Pro features (info only)
        add_submenu_page('alesta-ai', 'Roles', '- Roles et acces <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', 'manage_options', 'alesta-ai-roles', function(){ Alesta_AI_Pro_Promo::render('Rôles & Accès', 'Matrice de droits par rôle WordPress sur chaque module.', '👥'); });

        // ── Bottom CTA — "Passer à Pro" ──────────────────────────────────────
        // The slug is set to the external pricing URL so WordPress renders it
        // as a direct link in the sidebar (no callback is invoked).
        add_submenu_page(
            'alesta-ai',
            'Passer à Pro',
            '<span class="alesta-menu-upgrade">&#9889; Passer à Pro</span>',
            'manage_options',
            'https://www.alesta-ai.com/tarifs.html',
            null
        );
    }

    // -------------------------------------------------------------------------
    // ASSETS
    // -------------------------------------------------------------------------

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'alesta-ai' ) === false ) return;

        wp_enqueue_style( 'alesta-ai',           ALESTA_AI_URL . 'assets/admin.css',     [], ALESTA_AI_VERSION );
        wp_enqueue_style( 'alesta-ai-pro-promo', ALESTA_AI_URL . 'assets/pro-promo.css', [], ALESTA_AI_VERSION );
        wp_enqueue_script( 'alesta-ai', ALESTA_AI_URL . 'assets/admin.js',  ['jquery'], ALESTA_AI_VERSION, true );
        wp_localize_script( 'alesta-ai', 'AlestaAI', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'alesta_ai_nonce' ),
        ]);
    }

    // -------------------------------------------------------------------------
    // PAGES
    // -------------------------------------------------------------------------

    public function page_dashboard(): void {

        // --- Données site ---
        $total_images   = (int) wp_count_posts('attachment')->inherit;
        $total_posts    = (int) wp_count_posts('post')->publish;
        $total_pages    = (int) wp_count_posts('page')->publish;
        $total_content  = $total_posts + $total_pages;
        $total_products = 0;
        if (post_type_exists('product')) {
            $counts = wp_count_posts('product');
            $total_products = (int) ($counts->publish ?? 0);
        }

        // --- Dernier audit SEO ---
        $last_audit     = get_option('alesta_ai_last_audit');
        $has_audit      = is_array($last_audit) && !empty($last_audit['results']);
        $global_score   = $has_audit ? (int) ($last_audit['results']['summary']['global_score'] ?? 0) : null;
        $audit_date     = $has_audit ? $last_audit['timestamp'] : '';
        $audit_broken   = $has_audit ? (int) ($last_audit['results']['summary']['broken_links'] ?? 0) : 0;
        $audit_alt      = $has_audit ? (int) ($last_audit['results']['summary']['missing_alt']  ?? 0) : 0;

        // --- Données cockpit (disponibles sans module supplémentaire) ---
        $wp_version    = get_bloginfo('version');
        $php_version   = PHP_VERSION;
        $debug_on      = defined('WP_DEBUG') && WP_DEBUG;
        // Dashboard cards look up well-known files at the WordPress root
        // (llms.txt, llms-full.txt, robots.txt). Read-only file_exists checks —
        // ABSPATH is the only correct anchor for these root-level files.
        $llms_exists   = file_exists(trailingslashit(ABSPATH) . 'llms.txt');
        $llms_full     = file_exists(trailingslashit(ABSPATH) . 'llms-full.txt');
        $llms_count    = (int) get_option('alesta_llms_url_count', 0);
        $llms_last     = get_option('alesta_llms_last_generated', '');
        $robots_exists = file_exists(trailingslashit(ABSPATH) . 'robots.txt');
        $php_ok        = version_compare($php_version, '8.0', '>=');
        $wp_ok         = version_compare($wp_version,  '6.4', '>=');

        // --- Plugins en attente de mise à jour ---
        $update_plugins  = get_site_transient('update_plugins');
        $plugins_pending = is_object($update_plugins) && !empty($update_plugins->response) ? count($update_plugins->response) : 0;
        $update_themes   = get_site_transient('update_themes');
        $themes_pending  = is_object($update_themes) && !empty($update_themes->response) ? count($update_themes->response) : 0;
        $pending_updates = $plugins_pending + $themes_pending;

        // --- SSL ---
        $is_https = strpos(get_option('siteurl', ''), 'https://') === 0;

        // --- Espace disque ---
        // disk_free_space()/disk_total_space() report on the filesystem hosting
        // the given path — ABSPATH is correct here (= the WP install root).
        $disk_free    = function_exists('disk_free_space')  ? @disk_free_space(ABSPATH)  : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
        $disk_total   = function_exists('disk_total_space') ? @disk_total_space(ABSPATH) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
        $disk_pct     = ($disk_free !== false && $disk_total > 0) ? (int) round((1 - $disk_free / $disk_total) * 100) : null;
        $disk_free_gb = ($disk_free !== false) ? round($disk_free / 1073741824, 1) : null;

        // --- Tentatives login échouées (Journal d'activité) ---
        $activity_log       = get_option('alesta_activity_log', []);
        $login_failed_count = 0;
        if (is_array($activity_log)) {
            foreach ($activity_log as $entry) {
                if (isset($entry['type']) && $entry['type'] === 'login_failed') {
                    $login_failed_count++;
                }
            }
        }

        // --- Alertes automatiques ---
        $alerts_config   = get_option('alesta_alerts_config', []);
        $alerts_enabled  = !empty($alerts_config['enabled']);
        $alerts_history  = get_option('alesta_alerts_history', []);
        $last_alert      = !empty($alerts_history) ? reset($alerts_history) : null;
        $last_alert_date = $last_alert ? ($last_alert['date'] ?? '') : '';

        // --- Taille base de données ---
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $db_size_raw = $wpdb->get_var("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) FROM information_schema.tables WHERE table_schema = DATABASE()");
        $db_size     = $db_size_raw ? (float) $db_size_raw : null;

        // --- Mises à jour planifiées : dernier rapport ---
        $updates_history  = get_option('alesta_updates_history', []);
        $last_update_run  = !empty($updates_history) ? $updates_history[0] : null;
        $last_update_date = ($last_update_run && !empty($last_update_run['date'])) ? $last_update_run['date'] : '';

        ?>
        <div class="wrap alesta-wrap">

        <!-- ═══════════════════════════════════════════════════════════
             MASTER AI DASHBOARD — COCKPIT
        ══════════════════════════════════════════════════════════════ -->

        <!-- Header cockpit -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 26px;background:linear-gradient(135deg,#1e3a5f 0%,#0f2440 100%);border-radius:10px;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
            <div style="display:flex;align-items:center;gap:14px;">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:50px;height:50px;background:rgba(255,255,255,.1);border-radius:12px;font-family:Georgia,serif;font-size:36px;line-height:1;color:#fff;">&#x03C6;</span>
                <div>
                    <h1 style="color:#fff;margin:0;font-size:20px;font-weight:700;letter-spacing:-.3px;">Master AI Dashboard</h1>
                    <p style="color:#94a3b8;margin:0;font-size:13px;">Cockpit central — santé, performance, sécurité et visibilité IA en un seul écran</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="alesta-badge" id="api-status" style="font-size:12px;">Vérification...</span>
                <?php if ( defined('WPSEO_VERSION') ): ?>
                    <span style="font-size:11px;padding:3px 10px;background:#d1fae5;color:#065f46;border-radius:20px;border:1px solid #6ee7b7;">Yoast SEO</span>
                <?php elseif ( defined('RANK_MATH_VERSION') ): ?>
                    <span style="font-size:11px;padding:3px 10px;background:#d1fae5;color:#065f46;border-radius:20px;border:1px solid #6ee7b7;">RankMath</span>
                <?php else: ?>
                    <span style="font-size:11px;padding:3px 10px;background:#fef9c3;color:#713f12;border-radius:20px;border:1px solid #fcd34d;" title="Installez Yoast SEO ou RankMath pour l'intégration complète">⚠ Pas de plugin SEO</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cockpit — 6 panneaux (5 statuts + 1 actions rapides) -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px;">

            <!-- ■ Panneau 1 : Santé du site -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <div style="padding:11px 16px;background:#f0f9ff;border-bottom:1px solid #bae6fd;display:flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-heart" style="color:#0ea5e9;font-size:16px;width:16px;height:16px;"></span>
                    <span style="font-weight:700;font-size:12px;color:#0369a1;letter-spacing:.3px;">SANTÉ DU SITE</span>
                </div>
                <div style="padding:14px 16px;">
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">WordPress</span>
                            <span style="display:flex;align-items:center;gap:5px;font-weight:600;color:#111827;">
                                <span style="display:inline-block;width:7px;height:7px;background:<?php echo $wp_ok ? '#22c55e' : '#f59e0b'; ?>;border-radius:50%;"></span>
                                <?php echo esc_html($wp_version); ?>
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">PHP</span>
                            <span style="display:flex;align-items:center;gap:5px;font-weight:600;color:#111827;">
                                <span style="display:inline-block;width:7px;height:7px;background:<?php echo $php_ok ? '#22c55e' : '#f59e0b'; ?>;border-radius:50%;"></span>
                                <?php echo esc_html($php_version); ?>
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Plugins / thèmes MAJ</span>
                            <?php if ($pending_updates > 0): ?>
                                <span style="display:flex;align-items:center;gap:5px;">
                                    <span style="background:#fee2e2;color:#991b1b;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;"><?php echo esc_html((string) $pending_updates); ?> en attente</span>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-updates')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">planifier</a>
                                </span>
                            <?php else: ?>
                                <span style="background:#f0fdf4;color:#166534;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">✓ À jour</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Certificat SSL</span>
                            <?php if ($is_https): ?>
                                <span style="background:#f0fdf4;color:#166534;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">✓ HTTPS actif</span>
                            <?php else: ?>
                                <span style="display:flex;align-items:center;gap:5px;">
                                    <span style="background:#fee2e2;color:#991b1b;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">⚠ HTTP</span>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-cache')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">activer</a>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Espace disque libre</span>
                            <?php if ($disk_free_gb !== null): ?>
                                <span style="font-weight:600;font-size:11px;color:<?php echo ($disk_pct !== null && $disk_pct >= 90) ? '#dc2626' : (($disk_pct !== null && $disk_pct >= 75) ? '#d97706' : '#166534'); ?>;">
                                    <?php echo esc_html((string) $disk_free_gb); ?> Go libres
                                </span>
                            <?php else: ?>
                                <span style="font-size:11px;color:#9ca3af;">N/A</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-top:11px;padding-top:10px;border-top:1px solid #f3f4f6;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-health')); ?>" style="font-size:11px;color:#0369a1;text-decoration:none;">Health Check complet →</a>
                    </div>
                </div>
            </div>

            <!-- ■ Panneau 2 : Performance -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <div style="padding:11px 16px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-performance" style="color:#16a34a;font-size:16px;width:16px;height:16px;"></span>
                    <span style="font-weight:700;font-size:12px;color:#166534;letter-spacing:.3px;">PERFORMANCE</span>
                </div>
                <div style="padding:14px 16px;">
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;color:#9ca3af;">
                            <span>Core Web Vitals (LCP/INP)</span>
                            <span style="font-size:10px;background:#f3f4f6;padding:1px 8px;border-radius:10px;">Via PageSpeed</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Taille base de données</span>
                            <?php if ($db_size !== null): ?>
                                <span style="font-weight:600;font-size:11px;color:<?php echo $db_size >= 500 ? '#d97706' : '#111827'; ?>;">
                                    <?php echo esc_html((string) $db_size); ?> Mo
                                </span>
                            <?php else: ?>
                                <span style="font-size:11px;color:#9ca3af;">N/A</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Dernières MAJ auto</span>
                            <?php if ($last_update_date): ?>
                                <span style="font-size:11px;color:#6b7280;"><?php echo esc_html(mysql2date('d/m/Y', $last_update_date)); ?></span>
                            <?php else: ?>
                                <span style="display:flex;align-items:center;gap:5px;">
                                    <span style="font-size:11px;color:#9ca3af;font-style:italic;">Aucune</span>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-updates')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">planifier</a>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_audit): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Score SEO</span>
                            <span style="font-weight:700;color:<?php echo $global_score >= 80 ? '#16a34a' : ($global_score >= 50 ? '#d97706' : '#dc2626'); ?>;">
                                <?php echo esc_html((string) $global_score); ?>/100
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top:11px;padding-top:10px;border-top:1px solid #f3f4f6;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-cwv')); ?>" style="font-size:11px;color:#16a34a;text-decoration:none;">Moniteur Core Web Vitals →</a>
                    </div>
                </div>
            </div>

            <!-- ■ Panneau 3 : Sécurité -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <div style="padding:11px 16px;background:#fef2f2;border-bottom:1px solid #fecaca;display:flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-shield" style="color:#dc2626;font-size:16px;width:16px;height:16px;"></span>
                    <span style="font-weight:700;font-size:12px;color:#991b1b;letter-spacing:.3px;">SÉCURITÉ</span>
                </div>
                <div style="padding:14px 16px;">
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Tentatives login échouées</span>
                            <span style="display:flex;align-items:center;gap:5px;">
                                <?php if ($login_failed_count > 0): ?>
                                    <span style="background:#fee2e2;color:#991b1b;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;"><?php echo esc_html((string) $login_failed_count); ?></span>
                                <?php else: ?>
                                    <span style="background:#f0fdf4;color:#166534;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">0</span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-activity')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">journal →</a>
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;color:#9ca3af;">
                            <span>Fichiers sensibles exposés</span>
                            <span style="font-size:10px;background:#f3f4f6;padding:1px 8px;border-radius:10px;">Audit sécurité</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">WP_DEBUG</span>
                            <span style="display:flex;align-items:center;gap:6px;">
                                <?php if ($debug_on): ?>
                                    <span style="background:#fee2e2;color:#991b1b;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">ACTIF</span>
                                <?php else: ?>
                                    <span style="background:#f0fdf4;color:#166534;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">Inactif</span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-debug')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">gérer</a>
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">MAJ plugins planifiées</span>
                            <span style="display:flex;align-items:center;gap:5px;">
                                <?php if ($plugins_pending > 0): ?>
                                    <span style="background:#fef9c3;color:#713f12;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;"><?php echo esc_html((string) $plugins_pending); ?> plugin<?php echo $plugins_pending > 1 ? 's' : ''; ?></span>
                                <?php else: ?>
                                    <span style="background:#f0fdf4;color:#166534;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">✓ À jour</span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-updates')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">gérer</a>
                            </span>
                        </div>
                    </div>
                    <div style="margin-top:11px;padding-top:10px;border-top:1px solid #f3f4f6;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-security-audit')); ?>" style="font-size:11px;color:#991b1b;text-decoration:none;">Audit sécurité complet →</a>
                    </div>
                </div>
            </div>

            <!-- ■ Panneau 4 : Visibilité IA -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <div style="padding:11px 16px;background:#faf5ff;border-bottom:1px solid #e9d5ff;display:flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-visibility" style="color:#7c3aed;font-size:16px;width:16px;height:16px;"></span>
                    <span style="font-weight:700;font-size:12px;color:#5b21b6;letter-spacing:.3px;">VISIBILITÉ IA</span>
                </div>
                <div style="padding:14px 16px;">
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">/llms.txt</span>
                            <span style="display:flex;align-items:center;gap:5px;">
                                <?php if ($llms_exists): ?>
                                    <span style="background:#f0fdf4;color:#166534;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">Présent<?php echo $llms_count > 0 ? ' · ' . esc_html((string) $llms_count) . ' URLs' : ''; ?></span>
                                <?php else: ?>
                                    <span style="background:#f3f4f6;color:#6b7280;padding:1px 8px;border-radius:10px;font-size:10px;">Absent</span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-llms')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">générer</a>
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">/llms-full.txt</span>
                            <?php if ($llms_full): ?>
                                <span style="background:#f0fdf4;color:#166534;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">Présent</span>
                            <?php else: ?>
                                <span style="background:#f3f4f6;color:#6b7280;padding:1px 8px;border-radius:10px;font-size:10px;">Absent</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;color:#9ca3af;">
                            <span>AI Metadata</span>
                            <span style="font-style:italic;font-size:11px;">bientôt</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">/robots.txt</span>
                            <span style="display:flex;align-items:center;gap:5px;">
                                <?php if ($robots_exists): ?>
                                    <span style="background:#f0fdf4;color:#166534;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">Présent</span>
                                <?php else: ?>
                                    <span style="background:#fef9c3;color:#713f12;padding:1px 8px;border-radius:10px;font-size:10px;">Virtuel WP</span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-robots')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">éditer</a>
                            </span>
                        </div>
                    </div>
                    <div style="margin-top:11px;padding-top:10px;border-top:1px solid #f3f4f6;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-llms')); ?>" style="font-size:11px;color:#5b21b6;text-decoration:none;">LLMs.txt Generator →</a>
                    </div>
                </div>
            </div>

            <!-- ■ Panneau 5 : Alertes -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <div style="padding:11px 16px;background:#fffbeb;border-bottom:1px solid #fde68a;display:flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-bell" style="color:#d97706;font-size:16px;width:16px;height:16px;"></span>
                    <span style="font-weight:700;font-size:12px;color:#713f12;letter-spacing:.3px;">ALERTES & E-MAIL</span>
                </div>
                <div style="padding:14px 16px;">
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Alertes automatiques</span>
                            <?php if ($alerts_enabled): ?>
                                <span style="background:#f0fdf4;color:#166534;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">✓ Activées</span>
                            <?php else: ?>
                                <span style="display:flex;align-items:center;gap:5px;">
                                    <span style="background:#f3f4f6;color:#6b7280;padding:1px 8px;border-radius:10px;font-size:10px;">Désactivées</span>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-alerts')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">activer</a>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Dernière alerte envoyée</span>
                            <?php if ($last_alert_date): ?>
                                <span style="font-size:11px;color:#6b7280;"><?php echo esc_html(mysql2date('d/m/Y H:i', $last_alert_date)); ?></span>
                            <?php else: ?>
                                <span style="font-size:11px;color:#9ca3af;font-style:italic;">Aucune</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Journal d'activité</span>
                            <span style="display:flex;align-items:center;gap:5px;">
                                <?php $log_count = is_array($activity_log) ? count($activity_log) : 0; ?>
                                <span style="font-size:11px;color:#6b7280;"><?php echo esc_html((string) $log_count); ?> entrée<?php echo $log_count > 1 ? 's' : ''; ?></span>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-activity')); ?>" style="font-size:10px;color:#9ca3af;text-decoration:none;">voir →</a>
                            </span>
                        </div>
                        <?php if ($has_audit && $audit_broken > 0): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#6b7280;">Liens cassés (dernier audit)</span>
                            <span style="background:#fee2e2;color:#991b1b;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;"><?php echo esc_html((string) $audit_broken); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top:11px;padding-top:10px;border-top:1px solid #f3f4f6;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-alerts')); ?>" style="font-size:11px;color:#713f12;text-decoration:none;">Configurer les alertes →</a>
                    </div>
                </div>
            </div>

            <!-- ■ Panneau 6 : Actions rapides -->
            <div style="background:#1e3a5f;border-radius:8px;overflow:hidden;">
                <div style="padding:11px 16px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-controls-play" style="color:#94a3b8;font-size:16px;width:16px;height:16px;"></span>
                    <span style="font-weight:700;font-size:12px;color:#e2e8f0;letter-spacing:.3px;">ACTIONS RAPIDES</span>
                </div>
                <div style="padding:14px 16px;display:flex;flex-direction:column;gap:7px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-meta&tab=audit')); ?>" class="button" style="text-align:center;font-size:12px;background:rgba(255,255,255,.1);color:#e2e8f0;border-color:rgba(255,255,255,.2);width:100%;box-sizing:border-box;">
                        &#128269; Lancer l'audit SEO
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-translate')); ?>" class="button" style="text-align:center;font-size:12px;background:rgba(255,255,255,.1);color:#e2e8f0;border-color:rgba(255,255,255,.2);width:100%;box-sizing:border-box;">
                        &#127760; Traduire un contenu
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-chatbot')); ?>" class="button" style="text-align:center;font-size:12px;background:rgba(255,255,255,.1);color:#e2e8f0;border-color:rgba(255,255,255,.2);width:100%;box-sizing:border-box;">
                        &#128483; Configurer le chatbot
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-activity')); ?>" class="button" style="text-align:center;font-size:12px;background:rgba(255,255,255,.1);color:#e2e8f0;border-color:rgba(255,255,255,.2);width:100%;box-sizing:border-box;">
                        &#128214; Journal d'activité
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-alerts')); ?>" class="button" style="text-align:center;font-size:12px;background:rgba(255,255,255,.1);color:#e2e8f0;border-color:rgba(255,255,255,.2);width:100%;box-sizing:border-box;">
                        &#128276; Alertes automatiques
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-debug')); ?>" class="button" style="text-align:center;font-size:12px;background:rgba(255,255,255,.1);color:#e2e8f0;border-color:rgba(255,255,255,.2);width:100%;box-sizing:border-box;">
                        &#128030; Debug Manager
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-settings')); ?>" class="button" style="text-align:center;font-size:12px;background:rgba(255,255,255,.1);color:#e2e8f0;border-color:rgba(255,255,255,.2);width:100%;box-sizing:border-box;">
                        &#9881; Réglages API
                    </a>
                </div>
            </div>

        </div><!-- /cockpit -->


        <!-- Chiffres clés -->
        <div class="alesta-stats-row" style="margin-bottom:2rem;">
            <div class="alesta-stat" style="background:#f0f4ff;border:1px solid #e0e7ff;">
                <span style="color:#1e3a5f;"><?php echo esc_html((string) $total_images); ?></span>
                <small>Images</small>
            </div>
            <div class="alesta-stat" style="background:#f0fdf4;border:1px solid #d1fae5;">
                <span style="color:#065f46;"><?php echo esc_html((string) $total_content); ?></span>
                <small>Pages &amp; articles</small>
            </div>
            <?php if ($total_products > 0): ?>
            <div class="alesta-stat" style="background:#fef3c7;border:1px solid #fde68a;">
                <span style="color:#78350f;"><?php echo esc_html((string) $total_products); ?></span>
                <small>Produits</small>
            </div>
            <?php endif; ?>
            <?php if ($has_audit):
                $sc = $global_score; $score_color = $sc >= 80 ? '#22c55e' : ($sc >= 50 ? '#f59e0b' : '#ef4444');
                $score_bg = $sc >= 80 ? '#ecfdf5' : ($sc >= 50 ? '#fffbeb' : '#fef2f2');
                $score_border = $sc >= 80 ? '#a7f3d0' : ($sc >= 50 ? '#fcd34d' : '#fecaca');
            ?>
            <div class="alesta-stat" style="background:<?php echo esc_attr($score_bg); ?>;border:1px solid <?php echo esc_attr($score_border); ?>;">
                <span style="color:<?php echo esc_attr($score_color); ?>;"><?php echo esc_html((string) $sc); ?>/100</span>
                <small>Score SEO<br><span style="font-size:10px;color:#9ca3af;"><?php echo esc_html(mysql2date('d/m/Y', $audit_date)); ?></span></small>
            </div>
            <?php if ($audit_alt > 0): ?>
            <div class="alesta-stat" style="background:#fffbeb;border:1px solid #fcd34d;">
                <span style="color:#b45309;"><?php echo esc_html((string) $audit_alt); ?></span>
                <small>Alt manquants</small>
            </div>
            <?php endif; ?>
            <?php if ($audit_broken > 0): ?>
            <div class="alesta-stat" style="background:#fef2f2;border:1px solid #fecaca;">
                <span style="color:#b91c1c;"><?php echo esc_html((string) $audit_broken); ?></span>
                <small>Liens cassés</small>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($llms_exists && $llms_count > 0): ?>
            <div class="alesta-stat" style="background:#faf5ff;border:1px solid #e9d5ff;">
                <span style="color:#5b21b6;"><?php echo esc_html((string) $llms_count); ?></span>
                <small>URLs llms.txt</small>
            </div>
            <?php endif; ?>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             CATALOGUE DES MODULES
        ══════════════════════════════════════════════════════════════ -->

        <!-- 01 SEO -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">01</span>
                <span class="alesta-section-title">SEO &amp; Référencement</span>
                <span class="alesta-section-desc">Optimisation on-page, balises, mots-clés, données structurées, visibilité IA</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128221;</div>
                    <div class="amc-info">
                        <div class="amc-name">Title &amp; Meta + Audit SEO <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="amc-desc">Génération Claude en lot &amp; audit score SEO, title, meta, H1/H2<?php if ($has_audit): ?> <span class="amc-kpi <?php echo $global_score >= 80 ? 'amc-kpi-good' : ($global_score >= 50 ? 'amc-kpi-warn' : 'amc-kpi-err'); ?>"><?php echo esc_html((string) $global_score); ?>/100</span><?php endif; ?></div>
                    </div>
                    <div class="amc-footer" style="display:flex;gap:6px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-meta')); ?>" class="button button-primary">Title &amp; Meta</a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-meta&tab=audit')); ?>" class="button">Audit SEO</a>
                    </div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#10067;</div>
                    <div class="amc-info"><div class="amc-name">FAQ Schema <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Rich snippets Google via JSON-LD</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-faq')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#127991;</div>
                    <div class="amc-info"><div class="amc-name">Données structurées <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Article, Product, Organization, LocalBusiness… Claude détecte le type par page</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-schema')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128273;</div>
                    <div class="amc-info"><div class="amc-name">Mots-clés <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Densité, synonymes LSI, analyse Claude</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-keywords')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128506;</div>
                    <div class="amc-info"><div class="amc-name">Sitemap XML</div><div class="amc-desc">Générer sitemap.xml et notifier Google &amp; Bing</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-sitemap')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#129302;</div>
                    <div class="amc-info">
                        <div class="amc-name">LLMs.txt pour IA <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="amc-desc">Fichier de découverte pour ChatGPT, Claude, Gemini…<?php if ($llms_exists): ?> <span class="amc-kpi amc-kpi-good"><?php echo esc_html((string) $llms_count); ?> URLs</span><?php endif; ?></div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-llms')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#129504;</div>
                    <div class="amc-info"><div class="amc-name">AI Metadata Generator <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Balises meta spécifiques aux crawlers IA</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-ai-metadata')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128203;</div>
                    <div class="amc-info"><div class="amc-name">Détecteur contenu dupliqué <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Analyse et alertes sur le contenu similaire</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-duplicates')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
            </div>
        </div>

        <!-- 02 Contenu -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">02</span>
                <span class="alesta-section-title">Contenu &amp; Rédaction</span>
                <span class="alesta-section-desc">Création, amélioration et enrichissement du contenu</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#10024;</div>
                    <div class="amc-info"><div class="amc-name">Amélioration texte <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Reformuler, simplifier, enrichir par Claude</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-improve')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128196;</div>
                    <div class="amc-info"><div class="amc-name">Résumés automatiques <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Extraits 2-3 phrases pour tous les articles et pages</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-summaries')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#127760;</div>
                    <div class="amc-info"><div class="amc-name">Traduction IA <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Traduit articles et pages en 20 langues via Claude Opus</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-translate')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128483;</div>
                    <div class="amc-info"><div class="amc-name">Chatbot IA <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Widget front-office connecté à Claude Haiku, configurable</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-chatbot')); ?>" class="button button-primary">Configurer</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128197;</div>
                    <div class="amc-info"><div class="amc-name">Plan éditorial <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Calendrier d'articles sur 1 à 3 mois</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-editorial')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
            </div>
        </div>

        <!-- 03 Médias -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">03</span>
                <span class="alesta-section-title">Médias &amp; Images</span>
                <span class="alesta-section-desc">Métadonnées, accessibilité et optimisation des images</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128444;</div>
                    <div class="amc-info">
                        <div class="amc-name">Traitement images <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="amc-desc">Titre, légende, alt, description — <?php echo esc_html((string) $total_images); ?> images<?php if ($audit_alt > 0): ?> <span class="amc-kpi amc-kpi-warn"><?php echo esc_html((string) $audit_alt); ?> alt manquants</span><?php endif; ?></div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-images')); ?>" class="button button-primary">Gérer</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128190;</div>
                    <div class="amc-info"><div class="amc-name">Nommage fichiers <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Audit SEO et renommage (SF3/SF4) par Claude</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-filenames')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
            </div>
        </div>

        <!-- 04 Performance -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">04</span>
                <span class="alesta-section-title">Performance &amp; Technique</span>
                <span class="alesta-section-desc">Vitesse, cache, compression, BDD, liens et redirections</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#9889;</div>
                    <div class="amc-info"><div class="amc-name">Gzip, Cache, HTTPS</div><div class="amc-desc">.htaccess : compression, cache navigateur, redirection HTTPS</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-cache')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#9888;</div>
                    <div class="amc-info"><div class="amc-name">Erreurs 4xx / 5xx</div><div class="amc-desc">Scanner les liens internes cassés<?php if ($audit_broken > 0): ?> <span class="amc-kpi amc-kpi-err"><?php echo esc_html((string) $audit_broken); ?> lien(s)</span><?php endif; ?></div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-links')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#129302;</div>
                    <div class="amc-info"><div class="amc-name">Robots.txt</div><div class="amc-desc">Éditer les règles d'indexation des moteurs de recherche</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-robots')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128200;</div>
                    <div class="amc-info"><div class="amc-name">Moniteur Core Web Vitals <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">LCP, CLS, INP en temps réel via PageSpeed API</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-cwv')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128257;</div>
                    <div class="amc-info"><div class="amc-name">Détecteur scripts bloquants <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Identification et conseils de report/defer</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-scripts')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-active">✓ Disponible</span>
                    <div class="amc-icon">&#128465;</div>
                    <div class="amc-info"><div class="amc-name">Nettoyeur BDD planifié</div><div class="amc-desc">Révisions, transients, spams — nettoyage automatique</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-db-cleaner')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-active">✓ Disponible</span>
                    <div class="amc-icon">&#128260;</div>
                    <div class="amc-info"><div class="amc-name">Redirections 404 auto <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Détection et suggestion IA des pages introuvables</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-redirects')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#127384;</div>
                    <div class="amc-info"><div class="amc-name">Optimiseur Google Fonts RGPD</div><div class="amc-desc">Auto-hébergement des polices pour la conformité RGPD</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-fonts')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
            </div>
        </div>

        <!-- 05 IA & Automatisation -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">05</span>
                <span class="alesta-section-title">IA &amp; Automatisation</span>
                <span class="alesta-section-desc">Traduction, modération, tags, chatbot, maintenance</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#127760;</div>
                    <div class="amc-info"><div class="amc-name">Traduction automatique <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">20 langues supportées — Claude Opus, tone ajustable</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-translate')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128172;</div>
                    <div class="amc-info"><div class="amc-name">Modération commentaires <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Spam, toxique, légitime — classé par Claude</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-comments')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#127991;</div>
                    <div class="amc-info"><div class="amc-name">Tags automatiques <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Catégories et tags appliqués par Claude</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-tags')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128679;</div>
                    <div class="amc-info"><div class="amc-name">Mode Maintenance</div><div class="amc-desc">Page de maintenance personnalisée avec accès admin</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-maintenance')); ?>" class="button button-primary">Configurer</a></div>
                </div>
            </div>
        </div>

        <!-- 06 Sécurité & Conformité -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">06</span>
                <span class="alesta-section-title">Sécurité &amp; Conformité</span>
                <span class="alesta-section-desc">Santé du site, audit sécurité, RGPD, journal et mises à jour</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128138;</div>
                    <div class="amc-info"><div class="amc-name">Health Check Dashboard</div><div class="amc-desc">Vue détaillée : PHP, SSL, disque, plugins, MySQL</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-health')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128272;</div>
                    <div class="amc-info"><div class="amc-name">Audit sécurité passif <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Fichiers exposés, tentatives login, permissions</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-security-audit')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#127987;</div>
                    <div class="amc-info"><div class="amc-name">Bannière RGPD souveraine</div><div class="amc-desc">Consentement conforme, sans dépendance externe</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-rgpd')); ?>" class="button button-primary">Configurer</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128214;</div>
                    <div class="amc-info">
                        <div class="amc-name">Journal d'activité admin <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="amc-desc">Historique des actions admin avec filtres, export CSV<?php if ($login_failed_count > 0): ?> <span class="amc-kpi amc-kpi-err"><?php echo esc_html((string) $login_failed_count); ?> login échoué<?php echo $login_failed_count > 1 ? 's' : ''; ?></span><?php endif; ?></div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-activity')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128197;</div>
                    <div class="amc-info">
                        <div class="amc-name">Mises à jour planifiées <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="amc-desc">WP Cron : plugins, thèmes, WordPress core — rapport e-mail<?php if ($pending_updates > 0): ?> <span class="amc-kpi amc-kpi-warn"><?php echo esc_html((string) $pending_updates); ?> en attente</span><?php endif; ?></div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-updates')); ?>" class="button button-primary">Planifier</a></div>
                </div>
            </div>
        </div>

        <!-- 07 Rapports -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">07</span>
                <span class="alesta-section-title">Rapports &amp; Tableau de bord</span>
                <span class="alesta-section-desc">Vue globale, statistiques et alertes</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128202;</div>
                    <div class="amc-info"><div class="amc-name">Dashboard SEO global <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Score SEO de toutes les pages en un coup d'œil</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-dashboard-seo')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128209;</div>
                    <div class="amc-info"><div class="amc-name">Rapport PDF mensuel <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Synthèse automatique par Claude</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-pdf')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128276;</div>
                    <div class="amc-info">
                        <div class="amc-name">Alertes &amp; Notifications <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="amc-desc">7 types de surveillance (login, admin, plugins, disque, site down…)<?php if ($alerts_enabled): ?> <span class="amc-kpi amc-kpi-good">Activées</span><?php endif; ?></div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-alerts')); ?>" class="button button-primary">Configurer</a></div>
                </div>
            </div>
        </div>

        <!-- 08 Avis & Réputation -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">08</span>
                <span class="alesta-section-title">Avis &amp; Réputation</span>
                <span class="alesta-section-desc">Récupération automatique de vos avis Google, Trustpilot et autres plateformes</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#11088;</div>
                    <div class="amc-info">
                        <div class="amc-name">Avis Google <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="amc-desc">Affichage de vos avis Google via shortcode — 4 templates (carousel, grille, liste, masonry)</div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-reviews')); ?>" class="button button-primary">Découvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status" style="background:#f3f4f6;color:#6b7280;">Bientôt</span>
                    <div class="amc-icon">&#128221;</div>
                    <div class="amc-info">
                        <div class="amc-name">Avis Trustpilot <?php echo Alesta_AI_Pro_Promo::dashboard_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="amc-desc">Récupération automatique de vos avis Trustpilot — module en préparation</div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-reviews-trustpilot')); ?>" class="button">Découvrir</a></div>
                </div>
            </div>
        </div>

        <!-- 09 Communication -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">09</span>
                <span class="alesta-section-title">Communication</span>
                <span class="alesta-section-desc">Boutons de contact flottants et chatbot pour engager vos visiteurs</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128172;</div>
                    <div class="amc-info">
                        <div class="amc-name">Talk to Me <span class="alesta-pro-pill" style="background:#d1fae5;border-color:#10b981;color:#065f46;">Free</span></div>
                        <div class="amc-desc">Bouton flottant multi-canal : WhatsApp, Messenger, téléphone, e-mail, Telegram, Instagram… (100 % gratuit)</div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-talk-to-me')); ?>" class="button button-primary">Configurer</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128483;</div>
                    <div class="amc-info">
                        <div class="amc-name">Chatbot IA visiteurs <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="amc-desc">Widget front-office connecté à Claude Haiku, personnalisable</div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-chatbot')); ?>" class="button button-primary">Découvrir</a></div>
                </div>
            </div>
        </div>

        <!-- 10 Réglages -->
        <div class="alesta-section-block">
            <div class="alesta-section-heading">
                <span class="alesta-section-num">10</span>
                <span class="alesta-section-title">Réglages &amp; Administration</span>
                <span class="alesta-section-desc">Configuration API, debug, rôles et budget tokens</span>
            </div>
            <div class="alesta-cards">
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#9881;</div>
                    <div class="amc-info"><div class="amc-name">Configuration API</div><div class="amc-desc">Clé API Anthropic, modèle Claude</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-settings')); ?>" class="button button-primary">Configurer</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128030;</div>
                    <div class="amc-info">
                        <div class="amc-name">Debug Manager</div>
                        <div class="amc-desc">Toggle WP_DEBUG, visionneuse debug.log<?php if ($debug_on): ?> <span class="amc-kpi amc-kpi-err">Debug actif</span><?php endif; ?></div>
                    </div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-debug')); ?>" class="button button-primary">Ouvrir</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128101;</div>
                    <div class="amc-info"><div class="amc-name">Rôles &amp; Accès <?php echo Alesta_AI_Pro_Promo::dashboard_badge('pro'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="amc-desc">Matrice de droits par rôle WordPress sur chaque module</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-roles')); ?>" class="button button-primary">Gérer</a></div>
                </div>
                <div class="alesta-module-card alesta-module-active">
                    <span class="amc-status amc-status-ok">✓ Disponible</span>
                    <div class="amc-icon">&#128176;</div>
                    <div class="amc-info"><div class="amc-name">Budget API</div><div class="amc-desc">Limite mensuelle de tokens Anthropic</div></div>
                    <div class="amc-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=alesta-ai-budget')); ?>" class="button button-primary">Configurer</a></div>
                </div>
            </div>
        </div>

        </div><!-- /wrap -->
        <?php
        wp_add_inline_script( 'alesta-ai', 'jQuery(function($){$.post(AlestaAI.ajax_url,{action:"alesta_test_api",nonce:AlestaAI.nonce},function(r){$("#api-status").text(r.success?"Claude connecté":"API non configurée").css("background",r.success?"#d1fae5":"#fee2e2").css("color",r.success?"#065f46":"#991b1b");});});' );
    }


    public function page_settings(): void {
        $key        = get_option( 'alesta_ai_api_key', '' );
        $model      = get_option( 'alesta_ai_model', 'claude-sonnet-4-5' );
        $has_claude = ! empty( $key );
        ?>
        <div class="wrap alesta-wrap">
            <div class="alesta-header">
                <div class="alesta-logo">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <div>
                        <h1>Reglages</h1>
                        <p>Configuration de l'API Anthropic</p>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <span id="badge-claude" style="font-size:12px;padding:4px 12px;border-radius:20px;font-weight:500;<?php echo $has_claude ? 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;' : 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;'; ?>">
                        <?php echo $has_claude ? '&#10003; Claude connecté' : '&#10007; Claude non configuré'; ?>
                    </span>
                </div>
            </div>

            <!-- Section Claude API -->
            <div class="alesta-card" style="flex-direction:column;align-items:flex-start;gap:16px;max-width:700px;margin-bottom:1.5rem;">
                <div style="display:flex;align-items:center;gap:10px;width:100%;padding-bottom:8px;border-bottom:1px solid #e5e7eb;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#1e3a5f;border-radius:8px;font-size:14px;color:#fff;font-weight:700;">C</span>
                    <div>
                        <div style="font-weight:600;font-size:14px;color:#111827;">API Claude (Anthropic)</div>
                        <div style="font-size:12px;color:#6b7280;">Configuration partagée avec Alesta AI Pro pour les fonctionnalités IA.</div>
                    </div>
                </div>
                <div style="width:100%">
                    <label style="display:block;font-weight:500;margin-bottom:6px;">Clé API Anthropic</label>
                    <input type="password" id="setting-apikey" class="regular-text" style="width:100%"
                           value="<?php echo esc_attr( $key ); ?>" placeholder="sk-ant-...">
                    <p class="description">Disponible sur <a href="https://console.anthropic.com" target="_blank" rel="noopener noreferrer">console.anthropic.com</a>. Stockée côté serveur.</p>
                </div>
                <div style="width:100%">
                    <label style="display:block;font-weight:500;margin-bottom:6px;">Modele Claude</label>
                    <select id="setting-model" style="width:100%">
                        <option value="claude-sonnet-4-5" <?php selected( $model, 'claude-sonnet-4-5' ); ?>>claude-sonnet-4-5 (recommande)</option>
                        <option value="claude-opus-4-5"   <?php selected( $model, 'claude-opus-4-5' ); ?>>claude-opus-4-5 (plus puissant)</option>
                        <option value="claude-haiku-4-5-20251001" <?php selected( $model, 'claude-haiku-4-5-20251001' ); ?>>claude-haiku-4-5 (plus rapide)</option>
                    </select>
                </div>
                <div style="display:flex;gap:10px;">
                    <button id="btn-save-settings" class="button button-primary">Enregistrer</button>
                    <button id="btn-test-api" class="button">Tester la connexion</button>
                </div>
                <div id="settings-feedback" style="display:none;"></div>
            </div>

        </div>
        <?php
    }

    public function page_coming_soon(): void {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin menu page slug, no form processing
        $labels = [
            'alesta-ai-seo'          => 'SEO & Referencement',
            'alesta-ai-meta'         => 'Title & Meta',
            'alesta-ai-faq'          => 'FAQ Schema',
            'alesta-ai-schema'       => 'Donnees structurees',
            'alesta-ai-keywords'     => 'Mots-cles',
            'alesta-ai-content'      => 'Contenu & Redaction',
            'alesta-ai-improve'      => 'Amelioration texte',
            'alesta-ai-editorial'    => 'Plan editorial',
            'alesta-ai-summaries'    => 'Resumes automatiques',
            'alesta-ai-media'        => 'Medias & Images',
            'alesta-ai-filenames'    => 'Nommage de fichiers',
            'alesta-ai-webp'         => 'Conversion WebP',
            'alesta-ai-perf'         => 'Performance & Technique',
            'alesta-ai-cwv'          => 'Core Web Vitals',
            'alesta-ai-links'        => 'Liens casses',
            'alesta-ai-https'        => 'HTTPS & Redirections',
            'alesta-ai-automation'   => 'IA & Automatisation',
            'alesta-ai-translate'    => 'Traduction automatique',
            'alesta-ai-comments'     => 'Moderation commentaires',
            'alesta-ai-tags'         => 'Tags automatiques',
            'alesta-ai-chatbot'      => 'Chatbot IA',
            'alesta-ai-reports'              => 'Rapports',
            'alesta-ai-reviews-section'      => 'Avis & Réputation',
            'alesta-ai-reviews'              => 'Avis Google',
            'alesta-ai-reviews-trustpilot'   => 'Avis Trustpilot',
            'alesta-ai-communication-section'=> 'Communication',
            'alesta-ai-talk-to-me'           => 'Talk to Me',
            'alesta-ai-dashboard-seo'=> 'Dashboard SEO global',
            'alesta-ai-pdf'          => 'Rapport PDF mensuel',
            'alesta-ai-alerts'       => 'Alertes & Notifications',
            'alesta-ai-settings-section' => 'Reglages',
            'alesta-ai-roles'        => 'Roles & Acces',
            'alesta-ai-budget'       => 'Budget API',
            // SEO — nouveaux modules
            'alesta-ai-ai-metadata'  => 'AI Metadata Generator',
            'alesta-ai-duplicates'   => 'Detecteur contenu duplique',
            // Performance — nouveaux modules
            'alesta-ai-perf-audit'   => 'Audit et recommandations',
            'alesta-ai-scripts'      => 'Detecteur scripts bloquants',
            'alesta-ai-db-cleaner'   => 'Nettoyeur BDD planifie',
            'alesta-ai-redirects'    => 'Redirections 404 automatiques',
            'alesta-ai-fonts'        => 'Optimiseur Google Fonts RGPD',
            // IA — nouveaux modules
            'alesta-ai-maintenance'  => 'Mode Maintenance',
            // Securite & Conformite
            'alesta-ai-security-section' => 'Sécurité & Conformité',
            'alesta-ai-health'       => 'Health Check Dashboard',
            'alesta-ai-security-audit' => 'Audit sécurité passif',
            'alesta-ai-rgpd'         => 'Bannière RGPD souveraine',
            'alesta-ai-activity'     => 'Journal activité admin',
            'alesta-ai-updates'      => 'Mises à jour planifiées',
        ];
        $title = isset( $labels[ $page ] ) ? $labels[ $page ] : ucfirst( str_replace( '-', ' ', str_replace( 'alesta-ai-', '', $page ) ) );
        ?>
        <div class="wrap alesta-wrap">
            <div class="alesta-header">
                <div class="alesta-logo">
                    <span class="dashicons dashicons-clock"></span>
                    <div>
                        <h1><?php echo esc_html( $title ); ?></h1>
                        <p>Module en cours de developpement</p>
                    </div>
                </div>
            </div>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:3rem;text-align:center;max-width:480px;margin:2rem 0;">
                <div style="font-size:48px;margin-bottom:1rem;">&#128679;</div>
                <h2 style="font-size:18px;color:#111827;margin-bottom:8px;">Bientot disponible</h2>
                <p style="color:#6b7280;font-size:14px;line-height:1.6;margin-bottom:1.5rem;">
                    Ce module sera disponible dans une prochaine version.
                </p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai' ) ); ?>" class="button button-primary">Retour au tableau de bord</a>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX
    // -------------------------------------------------------------------------

    public function ajax_test_api(): void {
        check_ajax_referer( 'alesta_ai_nonce', 'nonce' );
        $api    = new Alesta_AI_API();
        $result = $api->test_connection();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( ['message' => $result->get_error_message()] );
        }
        wp_send_json_success( ['message' => 'Connexion OK'] );
    }

    public function ajax_save_settings(): void {
        check_ajax_referer( 'alesta_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        $key   = sanitize_text_field( isset( $_POST['api_key'] ) ? wp_unslash( $_POST['api_key'] ) : '' );
        $model = sanitize_text_field( isset( $_POST['model'] ) ? wp_unslash( $_POST['model'] ) : 'claude-sonnet-4-5' );
        update_option( 'alesta_ai_api_key', $key );
        update_option( 'alesta_ai_model', $model );
        wp_send_json_success( ['message' => 'Réglages enregistrés.'] );
    }

}
