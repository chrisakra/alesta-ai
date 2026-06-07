<?php
defined('ABSPATH') || exit;

class Alesta_AI_Admin_Htaccess {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void {
        $pages = ['alesta-ai-cache', 'alesta-ai-perf'];
        $match = false;
        foreach ($pages as $p) { if (strpos($hook, $p) !== false) { $match = true; break; } }
        if (!$match) return;

        $ver = ALESTA_AI_VERSION . '.' . time();
        wp_enqueue_script('alesta-ai-htaccess', ALESTA_AI_URL . 'assets/htaccess.js', ['jquery'], $ver, true);
        wp_enqueue_style('alesta-ai-htaccess',  ALESTA_AI_URL . 'assets/htaccess.css', [], $ver);
        wp_localize_script('alesta-ai-htaccess', 'AlestaAI', [
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('alesta_ai_nonce'),
            'minify_nonce' => wp_create_nonce('alesta_minify_nonce'),
        ]);
    }

    public function render_page(string $active_tab = 'cache'): void {
        ?>
        <div class="wrap alesta-wrap" id="alesta-htaccess-wrap">

            <!-- Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:#1e3a5f;border-radius:8px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="dashicons dashicons-performance" style="font-size:28px;color:#a0aec0;"></span>
                    <div>
                        <h1 style="color:#fff;margin:0;font-size:18px;">Performance & Optimisation</h1>
                        <p style="color:#94a3b8;margin:0;font-size:13px;">Optimisation du fichier .htaccess pour améliorer la vitesse du site</p>
                    </div>
                </div>
                <div id="htaccess-status-bar" style="font-size:12px;color:#94a3b8;">Chargement...</div>
            </div>

            <!-- Statut global .htaccess -->
            <div id="htaccess-global-status" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:20px;display:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div style="display:flex;align-items:center;gap:20px;">
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">FICHIER .HTACCESS</div>
                            <div id="htaccess-file-status" style="font-size:13px;font-weight:600;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">ECRITURE</div>
                            <div id="htaccess-write-status" style="font-size:13px;font-weight:600;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">DERNIERE SAUVEGARDE</div>
                            <div id="htaccess-backup-date" style="font-size:13px;color:#374151;"></div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button id="btn-backup" class="button" style="font-size:12px;">Sauvegarder maintenant</button>
                        <button id="btn-restore" class="button" style="font-size:12px;color:#991b1b;border-color:#fca5a5;" disabled>Restaurer la sauvegarde</button>
                    </div>
                </div>
            </div>

            <!-- Onglets -->
            <div style="display:flex;gap:0;margin-bottom:0;border-bottom:2px solid #e5e7eb;">
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='cache'?'htaccess-tab-active':'' ); ?>"
                    data-tab="cache" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='cache'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='cache'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='cache'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    Cache navigateur
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='gzip'?'htaccess-tab-active':'' ); ?>"
                    data-tab="gzip" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='gzip'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='gzip'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='gzip'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    Compression GZIP
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='https'?'htaccess-tab-active':'' ); ?>"
                    data-tab="https" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='https'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='https'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='https'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    HTTPS
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='minify-css'?'htaccess-tab-active':'' ); ?>"
                    data-tab="minify-css" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='minify-css'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='minify-css'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='minify-css'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    🎨 Minify CSS
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='minify-js'?'htaccess-tab-active':'' ); ?>"
                    data-tab="minify-js" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='minify-js'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='minify-js'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='minify-js'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    ⚡ Minify JS
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='minify-html'?'htaccess-tab-active':'' ); ?>"
                    data-tab="minify-html" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='minify-html'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='minify-html'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='minify-html'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    📄 Minify HTML
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='preload-css'?'htaccess-tab-active':'' ); ?>"
                    data-tab="preload-css" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='preload-css'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='preload-css'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='preload-css'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    🚀 Preload CSS
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='www'?'htaccess-tab-active':'' ); ?>"
                    data-tab="www" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='www'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='www'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='www'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    🌐 WWW
                </button>
            </div>

            <!-- Contenu onglets -->
            <div id="htaccess-tabs-content" style="background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px;padding:24px;">

                <!-- Onglet Cache -->
                <div id="tab-cache" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='cache'?'block':'none' ); ?>;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">Cache navigateur</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Indique aux navigateurs des visiteurs de conserver les fichiers statiques en cache.
                                Les images, CSS et JS ne sont pas retelecharges a chaque visite - la page se charge instantanement pour les visiteurs qui reviennent.
                            </p>

                            <!-- Statut -->
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                                <span style="font-size:13px;color:#374151;">Statut :</span>
                                <span id="cache-status-badge" style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">Chargement...</span>
                            </div>

                            <!-- Options durees -->
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:16px;margin-bottom:16px;">
                                <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:12px;">DUREES DE CACHE</div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div>
                                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px;">Images</label>
                                        <select id="cache-img-duration" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                                            <option value="1 month">1 mois</option>
                                            <option value="6 months">6 mois</option>
                                            <option value="1 year" selected>1 an (recommande)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px;">CSS / JavaScript</label>
                                        <select id="cache-css-duration" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                                            <option value="1 week">1 semaine</option>
                                            <option value="1 month" selected>1 mois (recommande)</option>
                                            <option value="6 months">6 mois</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px;">Polices</label>
                                        <select id="cache-font-duration" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                                            <option value="6 months">6 mois</option>
                                            <option value="1 year" selected>1 an (recommande)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex;gap:8px;">
                                <button id="btn-apply-cache" class="button button-primary" style="font-size:13px;">Activer le cache navigateur</button>
                                <button id="btn-remove-cache" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;display:none;">Désactiver</button>
                            </div>
                        </div>

                        <!-- Preview code -->
                        <div style="flex:1;min-width:280px;">
                            <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:8px;">APERCU DU CODE .HTACCESS</div>
                            <pre id="cache-preview" style="background:#1e2a3a;color:#a8d8a8;padding:16px;border-radius:6px;font-size:11px;overflow:auto;max-height:320px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
                        </div>
                    </div>
                </div>

                <!-- Onglet GZIP -->
                <div id="tab-gzip" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='gzip'?'block':'none' ); ?>;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">Compression GZIP</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Compresse les fichiers HTML, CSS et JavaScript avant de les envoyer au navigateur.
                                Reduit le poids des pages de 60 a 80% - impact direct sur le temps de chargement et le score Google PageSpeed.
                            </p>

                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                                <span style="font-size:13px;color:#374151;">Statut :</span>
                                <span id="gzip-status-badge" style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">Chargement...</span>
                            </div>

                            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:#1e40af;">
                                La compression GZIP est automatiquement activée sur les serveurs SiteGround. Ces règles constituent une sécurité supplémentaire pour les navigateurs qui ne détectent pas automatiquement la compression.
                            </div>

                            <div style="display:flex;gap:8px;">
                                <button id="btn-apply-gzip" class="button button-primary" style="font-size:13px;">Activer la compression GZIP</button>
                                <button id="btn-remove-gzip" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;display:none;">Désactiver</button>
                            </div>
                        </div>

                        <div style="flex:1;min-width:280px;">
                            <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:8px;">APERCU DU CODE .HTACCESS</div>
                            <pre id="gzip-preview" style="background:#1e2a3a;color:#a8d8a8;padding:16px;border-radius:6px;font-size:11px;overflow:auto;max-height:320px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
                        </div>
                    </div>
                </div>

                <!-- Onglet HTTPS -->
                <div id="tab-https" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='https'?'block':'none' ); ?>;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">Redirection HTTPS</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Force toutes les URLs HTTP vers HTTPS via une redirection 301.
                                Indispensable pour la sécurité et le SEO - Google pénalise les sites sans HTTPS.
                            </p>

                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                                <span style="font-size:13px;color:#374151;">Statut .htaccess :</span>
                                <span id="https-status-badge" style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">Chargement...</span>
                            </div>

                            <!-- Statut URL WordPress -->
                            <div id="https-url-alert" style="display:none;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;margin-bottom:16px;">
                                <div style="font-size:12px;font-weight:600;color:#713f12;margin-bottom:6px;">URL WordPress encore en HTTP</div>
                                <div style="font-size:12px;color:#713f12;margin-bottom:10px;">
                                    Votre URL WordPress est configurée en HTTP. La redirection .htaccess ne suffit pas - il faut aussi corriger l'URL dans les Réglages WordPress.
                                </div>
                                <button id="btn-fix-https-url" class="button" style="font-size:12px;background:#713f12;color:#fff;border-color:#713f12;">
                                    Corriger l'URL WordPress en HTTPS
                                </button>
                            </div>

                            <div style="display:flex;gap:8px;">
                                <button id="btn-apply-https" class="button button-primary" style="font-size:13px;">Activer la redirection HTTPS</button>
                                <button id="btn-remove-https" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;display:none;">Désactiver</button>
                            </div>
                        </div>

                        <div style="flex:1;min-width:280px;">
                            <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:8px;">APERCU DU CODE .HTACCESS</div>
                            <pre id="https-preview" style="background:#1e2a3a;color:#a8d8a8;padding:16px;border-radius:6px;font-size:11px;overflow:auto;max-height:200px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
                        </div>
                    </div>
                </div>

                <!-- ================================================================
                     Onglet Minify CSS
                     ================================================================ -->
                <div id="tab-minify-css" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='minify-css'?'block':'none' ); ?>;">
                    <?php
                    $ms  = Alesta_AI_Minify_Module::settings();
                    $st  = Alesta_AI_Minify_Module::get_stats();
                    $css_on = ! empty($ms['css_enabled']);
                    ?>
                    <div style="display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">🎨 Minification CSS</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Supprime les commentaires, espaces et caractères superflus des fichiers CSS chargés sur le frontend.
                                Les fichiers minifiés sont mis en cache dans <code>wp-content/cache/alesta-minify/</code>.
                                Les fichiers originaux ne sont jamais modifiés.
                            </p>

                            <!-- Toggle -->
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                                <span style="font-size:13px;color:#374151;font-weight:600;">Activer la minification CSS :</span>
                                <label class="minify-toggle" style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;">
                                    <input type="checkbox" class="minify-switch" data-type="css_enabled" <?php checked($css_on); ?>
                                           style="opacity:0;width:0;height:0;">
                                    <span class="minify-slider" style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $css_on?'#1e3a5f':'#d1d5db'; ?>;border-radius:24px;transition:.3s;">
                                        <span style="position:absolute;top:3px;left:<?php echo $css_on?'23':'3'; ?>px;width:18px;height:18px;background:#fff;border-radius:50%;transition:.3s;display:block;"></span>
                                    </span>
                                </label>
                                <span class="minify-status-label" data-type="css_enabled" style="font-size:12px;color:<?php echo $css_on?'#16a34a':'#9ca3af'; ?>;font-weight:600;">
                                    <?php echo $css_on ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>

                            <!-- Exclusions -->
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:16px;margin-bottom:16px;">
                                <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">EXCLUSIONS (handles séparés par virgule)</div>
                                <textarea id="minify-css-excludes" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:4px;padding:8px;font-size:12px;font-family:monospace;resize:vertical;" rows="3"
                                    placeholder="elementor-frontend, woocommerce-layout, mon-theme-style"><?php echo esc_textarea($ms['css_excludes']); ?></textarea>
                                <p style="font-size:11px;color:#9ca3af;margin:6px 0 0;">Les CSS déjà minifiés (.min.css) sont automatiquement ignorés.</p>
                            </div>

                            <div style="display:flex;gap:8px;align-items:center;">
                                <button id="btn-save-css" class="button button-primary" style="font-size:13px;">💾 Enregistrer</button>
                                <button id="btn-clear-minify-cache" class="button" style="font-size:13px;">🗑 Vider le cache</button>
                                <span class="spinner" id="spinner-css" style="float:none;margin:0 4px;"></span>
                                <span id="msg-css" style="font-size:12px;"></span>
                            </div>
                        </div>

                        <!-- Stats cache -->
                        <div style="flex:0 0 220px;min-width:200px;">
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
                                <div style="font-size:11px;font-weight:700;color:#6b7280;margin-bottom:12px;">CACHE MINIFICATION</div>
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    <div>
                                        <div style="font-size:11px;color:#9ca3af;">Fichiers CSS en cache</div>
                                        <div id="stat-css-files" style="font-size:20px;font-weight:700;color:#1e3a5f;"><?php echo esc_html($st['css_files']); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size:11px;color:#9ca3af;">Fichiers JS en cache</div>
                                        <div id="stat-js-files" style="font-size:20px;font-weight:700;color:#1e3a5f;"><?php echo esc_html($st['js_files']); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size:11px;color:#9ca3af;">Taille totale cache</div>
                                        <div id="stat-total-size" style="font-size:16px;font-weight:600;color:#374151;"><?php echo esc_html($st['total_size']); ?></div>
                                    </div>
                                </div>
                                <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb;">
                                    <div style="font-size:11px;color:#9ca3af;margin-bottom:4px;">Dossier cache</div>
                                    <code style="font-size:10px;color:#374151;word-break:break-all;">wp-content/cache/alesta-minify/</code>
                                    <div style="margin-top:8px;font-size:11px;color:<?php echo Alesta_AI_Minify_Module::ensure_cache_dir() ? '#16a34a' : '#dc2626'; ?>;">
                                        <?php echo Alesta_AI_Minify_Module::ensure_cache_dir() ? '✅ Accessible en écriture' : '❌ Non accessible'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================================
                     Onglet Minify JS
                     ================================================================ -->
                <div id="tab-minify-js" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='minify-js'?'block':'none' ); ?>;">
                    <?php $js_on = ! empty($ms['js_enabled']); ?>
                    <div style="display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">⚡ Minification JavaScript</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Supprime les commentaires et réduit les espaces dans les fichiers JavaScript.
                                Approche conservatrice : les chaînes de caractères et le code fonctionnel ne sont pas altérés.
                                jQuery, jQuery Migrate et les scripts WordPress core sont exclus automatiquement.
                            </p>

                            <!-- Toggle -->
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                                <span style="font-size:13px;color:#374151;font-weight:600;">Activer la minification JS :</span>
                                <label class="minify-toggle" style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;">
                                    <input type="checkbox" class="minify-switch" data-type="js_enabled" <?php checked($js_on); ?>
                                           style="opacity:0;width:0;height:0;">
                                    <span class="minify-slider" style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $js_on?'#1e3a5f':'#d1d5db'; ?>;border-radius:24px;transition:.3s;">
                                        <span style="position:absolute;top:3px;left:<?php echo $js_on?'23':'3'; ?>px;width:18px;height:18px;background:#fff;border-radius:50%;transition:.3s;display:block;"></span>
                                    </span>
                                </label>
                                <span class="minify-status-label" data-type="js_enabled" style="font-size:12px;color:<?php echo $js_on?'#16a34a':'#9ca3af'; ?>;font-weight:600;">
                                    <?php echo $js_on ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>

                            <!-- Avertissement -->
                            <div style="background:#fefce8;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:#92400e;line-height:1.6;">
                                ⚠️ <strong>Conseil :</strong> Testez sur un environnement de staging avant d'activer en production.
                                En cas de problème, videz le cache ou désactivez pour revenir au comportement normal.
                                Les fichiers <code>.min.js</code> déjà minifiés sont automatiquement ignorés.
                            </div>

                            <!-- Exclusions -->
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:16px;margin-bottom:16px;">
                                <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">EXCLUSIONS (handles séparés par virgule)</div>
                                <textarea id="minify-js-excludes" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:4px;padding:8px;font-size:12px;font-family:monospace;resize:vertical;" rows="3"
                                    placeholder="elementor, wc-cart, mon-script-custom"><?php echo esc_textarea($ms['js_excludes']); ?></textarea>
                                <p style="font-size:11px;color:#9ca3af;margin:6px 0 0;">Toujours exclus : jquery, jquery-core, jquery-migrate, wp-embed, wp-polyfill.</p>
                            </div>

                            <div style="display:flex;gap:8px;align-items:center;">
                                <button id="btn-save-js" class="button button-primary" style="font-size:13px;">💾 Enregistrer</button>
                                <button class="button btn-clear-minify-cache-js" style="font-size:13px;">🗑 Vider le cache</button>
                                <span class="spinner" id="spinner-js" style="float:none;margin:0 4px;"></span>
                                <span id="msg-js" style="font-size:12px;"></span>
                            </div>
                        </div>

                        <!-- Stats réutilisées -->
                        <div style="flex:0 0 220px;min-width:200px;">
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
                                <div style="font-size:11px;font-weight:700;color:#6b7280;margin-bottom:12px;">CACHE MINIFICATION</div>
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    <div>
                                        <div style="font-size:11px;color:#9ca3af;">Fichiers CSS en cache</div>
                                        <div style="font-size:20px;font-weight:700;color:#1e3a5f;"><?php echo esc_html($st['css_files']); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size:11px;color:#9ca3af;">Fichiers JS en cache</div>
                                        <div style="font-size:20px;font-weight:700;color:#1e3a5f;"><?php echo esc_html($st['js_files']); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size:11px;color:#9ca3af;">Taille totale cache</div>
                                        <div style="font-size:16px;font-weight:600;color:#374151;"><?php echo esc_html($st['total_size']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================================
                     Onglet Minify HTML
                     ================================================================ -->
                <div id="tab-minify-html" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='minify-html'?'block':'none' ); ?>;">
                    <?php $html_on = ! empty($ms['html_enabled']); ?>
                    <div style="display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">📄 Minification HTML</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Réduit le poids des pages HTML à la volée via un buffer PHP (<code>ob_start</code>).
                                Les blocs <code>&lt;script&gt;</code>, <code>&lt;style&gt;</code>, <code>&lt;pre&gt;</code> et <code>&lt;textarea&gt;</code>
                                sont préservés intégralement. L'admin et les requêtes AJAX sont exclus automatiquement.
                            </p>

                            <!-- Toggle -->
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                                <span style="font-size:13px;color:#374151;font-weight:600;">Activer la minification HTML :</span>
                                <label class="minify-toggle" style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;">
                                    <input type="checkbox" class="minify-switch" data-type="html_enabled" <?php checked($html_on); ?>
                                           style="opacity:0;width:0;height:0;">
                                    <span class="minify-slider" style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $html_on?'#1e3a5f':'#d1d5db'; ?>;border-radius:24px;transition:.3s;">
                                        <span style="position:absolute;top:3px;left:<?php echo $html_on?'23':'3'; ?>px;width:18px;height:18px;background:#fff;border-radius:50%;transition:.3s;display:block;"></span>
                                    </span>
                                </label>
                                <span class="minify-status-label" data-type="html_enabled" style="font-size:12px;color:<?php echo $html_on?'#16a34a':'#9ca3af'; ?>;font-weight:600;">
                                    <?php echo $html_on ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>

                            <!-- Options -->
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:16px;margin-bottom:16px;">
                                <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:12px;">OPTIONS</div>
                                <label style="display:flex;align-items:flex-start;gap:8px;margin-bottom:10px;cursor:pointer;font-size:13px;color:#374151;">
                                    <input type="checkbox" id="html-remove-comments" <?php checked( !empty($ms['html_remove_comments']) ); ?> style="margin-top:2px;">
                                    <span><strong>Supprimer les commentaires HTML</strong><br>
                                    <span style="font-size:11px;color:#9ca3af;">Les commentaires conditionnels IE et les blocs WordPress (<code>&lt;!--wp:--&gt;</code>) sont conservés.</span></span>
                                </label>
                                <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;color:#374151;">
                                    <input type="checkbox" id="html-remove-whitespace" <?php checked( !empty($ms['html_remove_whitespace']) ); ?> style="margin-top:2px;">
                                    <span><strong>Réduire les espaces entre balises</strong><br>
                                    <span style="font-size:11px;color:#9ca3af;">Compresse les sauts de ligne et indentations entre les éléments HTML.</span></span>
                                </label>
                            </div>

                            <div style="display:flex;gap:8px;align-items:center;">
                                <button id="btn-save-html" class="button button-primary" style="font-size:13px;">💾 Enregistrer</button>
                                <span class="spinner" id="spinner-html" style="float:none;margin:0 4px;"></span>
                                <span id="msg-html" style="font-size:12px;"></span>
                            </div>
                        </div>

                        <!-- Info visuelle -->
                        <div style="flex:0 0 240px;min-width:200px;">
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px;font-size:12px;color:#374151;">
                                <div style="font-size:11px;font-weight:700;color:#6b7280;margin-bottom:12px;">CE QUI EST PRÉSERVÉ</div>
                                <?php foreach ([
                                    '&lt;script&gt; … &lt;/script&gt;',
                                    '&lt;style&gt; … &lt;/style&gt;',
                                    '&lt;pre&gt; … &lt;/pre&gt;',
                                    '&lt;textarea&gt; … &lt;/textarea&gt;',
                                    'Commentaires IE &lt;!--[if …]&gt;',
                                    'Balises noindex WordPress',
                                ] as $item) : ?>
                                <div style="display:flex;gap:6px;margin-bottom:6px;">
                                    <span style="color:#16a34a;">✓</span>
                                    <span><?php echo esc_html( $item ); ?></span>
                                </div>
                                <?php endforeach; ?>
                                <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb;font-size:11px;color:#6b7280;line-height:1.6;">
                                    Gain typique : <strong>5 — 15 %</strong> sur le poids HTML.<br>
                                    Aucun fichier créé — traitement à la volée.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================================
                     Onglet Preload CSS
                     ================================================================ -->
                <div id="tab-preload-css" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='preload-css'?'block':'none' ); ?>;">
                    <?php $preload_on = ! empty($ms['preload_enabled']); ?>
                    <div style="display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">🚀 Preload CSS</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Injecte des balises <code>&lt;link rel="preload" as="style"&gt;</code> dans le <code>&lt;head&gt;</code> pour signaler
                                au navigateur de charger les feuilles de style en priorité. Améliore le LCP et réduit le blocage du rendu.
                            </p>

                            <!-- Toggle -->
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                                <span style="font-size:13px;color:#374151;font-weight:600;">Activer les hints Preload CSS :</span>
                                <label class="minify-toggle" style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;">
                                    <input type="checkbox" class="minify-switch" data-type="preload_enabled" <?php checked($preload_on); ?>
                                           style="opacity:0;width:0;height:0;">
                                    <span class="minify-slider" style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $preload_on?'#1e3a5f':'#d1d5db'; ?>;border-radius:24px;transition:.3s;">
                                        <span style="position:absolute;top:3px;left:<?php echo $preload_on?'23':'3'; ?>px;width:18px;height:18px;background:#fff;border-radius:50%;transition:.3s;display:block;"></span>
                                    </span>
                                </label>
                                <span class="minify-status-label" data-type="preload_enabled" style="font-size:12px;color:<?php echo $preload_on?'#16a34a':'#9ca3af'; ?>;font-weight:600;">
                                    <?php echo $preload_on ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>

                            <!-- Mode -->
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:16px;margin-bottom:14px;">
                                <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:12px;">MODE</div>
                                <label style="display:flex;gap:8px;align-items:flex-start;margin-bottom:10px;cursor:pointer;font-size:13px;color:#374151;">
                                    <input type="radio" name="preload_mode" value="all" <?php checked($ms['preload_mode'], 'all'); ?> style="margin-top:3px;" id="preload-mode-all">
                                    <span><strong>Automatique</strong> — précharger tous les CSS enqueued<br>
                                    <span style="font-size:11px;color:#9ca3af;">Utilise les exclusions pour affiner.</span></span>
                                </label>
                                <label style="display:flex;gap:8px;align-items:flex-start;cursor:pointer;font-size:13px;color:#374151;">
                                    <input type="radio" name="preload_mode" value="manual" <?php checked($ms['preload_mode'], 'manual'); ?> style="margin-top:3px;" id="preload-mode-manual">
                                    <span><strong>Manuel</strong> — spécifier les handles à précharger<br>
                                    <span style="font-size:11px;color:#9ca3af;">Idéal pour cibler uniquement les CSS critiques.</span></span>
                                </label>
                            </div>

                            <!-- Handles manuels -->
                            <div id="preload-manual-section" style="display:<?php echo $ms['preload_mode']==='manual'?'block':'none'; ?>;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:14px;margin-bottom:14px;">
                                <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:6px;">Handles à précharger (séparés par virgule)</label>
                                <input type="text" id="preload-handles" value="<?php echo esc_attr($ms['preload_handles']); ?>"
                                       style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:4px;padding:7px 10px;font-size:12px;font-family:monospace;"
                                       placeholder="my-theme-style, woocommerce-general" />
                                <p style="font-size:11px;color:#9ca3af;margin:5px 0 0;">Le handle est le 1er paramètre de <code>wp_enqueue_style('handle', ...)</code>.</p>
                            </div>

                            <!-- Exclusions -->
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:14px;margin-bottom:16px;">
                                <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:6px;">Exclusions (handles séparés par virgule)</label>
                                <input type="text" id="preload-excludes" value="<?php echo esc_attr($ms['preload_excludes']); ?>"
                                       style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:4px;padding:7px 10px;font-size:12px;font-family:monospace;"
                                       placeholder="dashicons, admin-bar" />
                            </div>

                            <div style="display:flex;gap:8px;align-items:center;">
                                <button id="btn-save-preload" class="button button-primary" style="font-size:13px;">💾 Enregistrer</button>
                                <span class="spinner" id="spinner-preload" style="float:none;margin:0 4px;"></span>
                                <span id="msg-preload" style="font-size:12px;"></span>
                            </div>
                        </div>

                        <!-- Explications -->
                        <div style="flex:0 0 260px;min-width:220px;">
                            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;font-size:12px;color:#1e40af;margin-bottom:12px;">
                                <strong>Comment ça fonctionne ?</strong>
                                <p style="margin:8px 0 0;line-height:1.7;color:#374151;">
                                    Le navigateur reçoit un hint <code>preload</code> avant même de parser le HTML.
                                    Il commence à télécharger le CSS en parallèle, réduisant le blocage du rendu (render-blocking).
                                </p>
                                <div style="background:#fff;border-radius:4px;padding:10px;margin-top:10px;font-family:monospace;font-size:11px;color:#374151;line-height:1.7;overflow:auto;">
                                    &lt;link rel="preload"<br>
                                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;as="style"<br>
                                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;href="style.css"&gt;
                                </div>
                            </div>
                            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;font-size:12px;color:#166534;">
                                <strong>Impact attendu</strong><br>
                                <span style="color:#374151;line-height:1.7;">
                                    ✓ Amélioration du LCP<br>
                                    ✓ Réduction du blocage du rendu<br>
                                    ✓ Score PageSpeed amélioré<br>
                                    ✓ Compatible CDN et cache
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================================
                     Onglet WWW
                     ================================================================ -->
                <?php
                $siteurl     = get_option('siteurl', '');
                $homeurl     = get_option('home', '');
                $has_www     = (bool) preg_match('#^https?://www\.#i', $siteurl);
                $www_marker  = 'Alesta-WWW';
                // .htaccess lives at the WordPress root by definition — ABSPATH
                // is the only correct anchor. Read-only lookup via WP's
                // extract_from_markers().
                $www_htaccess_active = file_exists(ABSPATH . '.htaccess')
                    && !empty(array_filter(extract_from_markers(ABSPATH . '.htaccess', $www_marker)));
                ?>
                <div id="tab-www" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='www'?'block':'none' ); ?>;">
                    <div style="display:flex;flex-direction:column;gap:24px;">

                        <!-- Section 1 : .htaccess WWW redirect -->
                        <div style="display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:300px;">
                                <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">🌐 Redirection WWW (.htaccess)</h3>
                                <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                    Force toutes les URLs sans <code>www</code> vers <code>www.votresite.com</code> via une redirection 301.
                                    Évite le contenu dupliqué et améliore la cohérence SEO.
                                </p>

                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                                    <span style="font-size:13px;color:#374151;">Statut .htaccess :</span>
                                    <span id="www-status-badge" style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;background:<?php echo $www_htaccess_active ? '#dcfce7' : '#f3f4f6'; ?>;color:<?php echo $www_htaccess_active ? '#166534' : '#6b7280'; ?>;">
                                        <?php echo $www_htaccess_active ? '✅ Actif' : '⚫ Inactif'; ?>
                                    </span>
                                </div>

                                <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:#713f12;line-height:1.6;">
                                    ⚠️ <strong>Important :</strong> N'activez pas la redirection WWW si votre certificat SSL ne couvre pas le sous-domaine <code>www</code>.
                                    Vérifiez d'abord que <code>https://www.<?php echo esc_html(preg_replace('#^https?://(www\.)?#i','',wp_parse_url($siteurl, PHP_URL_HOST))); ?></code> est accessible.
                                </div>

                                <div style="display:flex;gap:8px;">
                                    <button id="btn-apply-www" class="button button-primary" style="font-size:13px;<?php echo $www_htaccess_active ? 'display:none;' : ''; ?>">Activer la redirection WWW</button>
                                    <button id="btn-remove-www" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;<?php echo !$www_htaccess_active ? 'display:none;' : ''; ?>">Désactiver</button>
                                </div>
                            </div>

                            <div style="flex:1;min-width:280px;">
                                <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:8px;">APERÇU DU CODE .HTACCESS</div>
                                <pre id="www-preview" style="background:#1e2a3a;color:#a8d8a8;padding:16px;border-radius:6px;font-size:11px;overflow:auto;max-height:200px;line-height:1.5;margin:0;white-space:pre-wrap;"><?php
                                if ($www_htaccess_active) {
                                    echo esc_html("# BEGIN Alesta-WWW\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{HTTP_HOST} !^www\\. [NC]\n    RewriteRule ^ https://www.%{HTTP_HOST}%{REQUEST_URI} [R=301,L]\n</IfModule>\n# END Alesta-WWW");
                                } else {
                                    echo '— Inactif —';
                                }
                                ?></pre>
                            </div>
                        </div>

                        <!-- Divider -->
                        <hr style="border:none;border-top:1px solid #e5e7eb;margin:0;">

                        <!-- Section 2 : URL WordPress -->
                        <div>
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">⚙️ URL WordPress avec WWW</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Modifie les réglages <strong>Adresse du site</strong> et <strong>Adresse WordPress</strong> dans la base de données
                                pour y ajouter (ou retirer) le préfixe <code>www</code>.
                            </p>

                            <!-- URL actuelle -->
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:14px 16px;margin-bottom:16px;">
                                <div style="font-size:11px;font-weight:700;color:#9ca3af;margin-bottom:10px;">CONFIGURATION ACTUELLE</div>
                                <div style="display:flex;flex-direction:column;gap:6px;font-size:13px;">
                                    <div style="display:flex;gap:10px;">
                                        <span style="color:#6b7280;min-width:180px;">Adresse WordPress (siteurl)</span>
                                        <code style="color:#1e3a5f;font-weight:600;"><?php echo esc_html($siteurl); ?></code>
                                        <?php if ($has_www): ?>
                                            <span style="background:#dcfce7;color:#166534;font-size:11px;padding:1px 8px;border-radius:10px;font-weight:600;">www ✅</span>
                                        <?php else: ?>
                                            <span style="background:#f3f4f6;color:#6b7280;font-size:11px;padding:1px 8px;border-radius:10px;">sans www</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;gap:10px;">
                                        <span style="color:#6b7280;min-width:180px;">Adresse du site (home)</span>
                                        <code style="color:#1e3a5f;font-weight:600;"><?php echo esc_html($homeurl); ?></code>
                                    </div>
                                </div>
                            </div>

                            <!-- Alerte déconnexion -->
                            <div style="background:#fff3cd;border:2px solid #ffc107;border-radius:8px;padding:14px 16px;margin-bottom:16px;">
                                <div style="font-size:13px;font-weight:700;color:#856404;margin-bottom:6px;">
                                    ⚠️ Vous serez déconnecté après cette opération
                                </div>
                                <div style="font-size:12px;color:#856404;line-height:1.7;">
                                    WordPress invalide la session admin lorsque l'URL du site change.
                                    Vous serez automatiquement redirigé vers la page de connexion.
                                    <strong>Assurez-vous de connaître vos identifiants avant de continuer.</strong>
                                </div>
                            </div>

                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <?php if (!$has_www): ?>
                                <button id="btn-add-www-url" class="button button-primary" style="font-size:13px;">
                                    ➕ Ajouter www dans les URLs WordPress
                                </button>
                                <?php else: ?>
                                <button id="btn-remove-www-url" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;">
                                    ➖ Retirer www des URLs WordPress
                                </button>
                                <?php endif; ?>
                                <span id="www-url-msg" style="font-size:13px;align-self:center;"></span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    // =========================================================================
    // PLACEHOLDER "BIENTÔT DISPONIBLE"
    // =========================================================================

    private static function render_coming_soon( string $title, string $icon, string $desc, array $features ): void {
        ?>
        <div style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:40px 24px;max-width:600px;margin:0 auto;">
            <div style="font-size:48px;margin-bottom:16px;"><?php echo esc_html($icon); ?></div>
            <h3 style="font-size:18px;font-weight:700;color:#111827;margin:0 0 8px;">
                <?php echo esc_html($title); ?>
                <span style="font-size:11px;background:#eff6ff;color:#1d4ed8;padding:2px 10px;border-radius:10px;margin-left:8px;vertical-align:middle;font-weight:600;">Prochainement</span>
            </h3>
            <p style="font-size:13px;color:#6b7280;line-height:1.7;margin:0 0 24px;"><?php echo esc_html($desc); ?></p>
            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px 24px;text-align:left;width:100%;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.5px;margin-bottom:12px;">CE QUE CETTE FONCTIONNALITÉ APPORTERA</div>
                <?php foreach ($features as $f) : ?>
                <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;font-size:13px;color:#374151;">
                    <span style="color:#16a34a;flex-shrink:0;margin-top:1px;">✓</span>
                    <?php echo esc_html($f); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
