<?php
defined('ABSPATH') || exit;

/**
 * Budget API — Page admin (Alesta AI)
 */
class Alesta_AI_Admin_Budget {

    public function __construct() {
        add_action('admin_enqueue_scripts',          [$this, 'enqueue']);
        add_action('wp_ajax_alesta_budget_save',         [$this, 'ajax_save']);
        add_action('wp_ajax_alesta_budget_reset_month',  [$this, 'ajax_reset_month']);
        add_action('wp_ajax_alesta_budget_reset_global', [$this, 'ajax_reset_global']);
        add_action('wp_ajax_alesta_budget_export',       [$this, 'ajax_export']);
    }

    /* ── Assets ── */
    public function enqueue( string $hook ): void {
        if ( strpos($hook, 'alesta-ai-budget') === false ) return;
        $ver = ALESTA_AI_VERSION . '.' . time();
        wp_enqueue_style ('alesta-budget', ALESTA_AI_URL . 'assets/budget.css', [], $ver);
        wp_enqueue_script('alesta-budget', ALESTA_AI_URL . 'assets/budget.js', ['jquery'], $ver, true);
        wp_localize_script('alesta-budget', 'AlestaBudget', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('alesta_budget_nonce'),
        ]);
    }

    /* ── AJAX : Enregistrer les réglages ── */
    public function ajax_save(): void {
        check_ajax_referer('alesta_budget_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Accès refusé.');

        Alesta_AI_API::save_budget_settings([
            'monthly_limit'   => sanitize_text_field(wp_unslash($_POST['monthly_limit']   ?? '')),
            'alert_threshold' => (int) sanitize_text_field(wp_unslash($_POST['alert_threshold'] ?? 80)),
            'block_on_limit'  => !empty($_POST['block_on_limit']) && wp_unslash($_POST['block_on_limit']) === '1',
            'alert_email'     => sanitize_email(wp_unslash($_POST['alert_email'] ?? '')),
        ]);
        wp_send_json_success(['msg' => 'Réglages enregistrés.']);
    }

    /* ── AJAX : Reset mois en cours ── */
    public function ajax_reset_month(): void {
        check_ajax_referer('alesta_budget_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Accès refusé.');
        Alesta_AI_API::reset_monthly();
        wp_send_json_success(['msg' => 'Compteur du mois réinitialisé.']);
    }

    /* ── AJAX : Reset global ── */
    public function ajax_reset_global(): void {
        check_ajax_referer('alesta_budget_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Accès refusé.');
        Alesta_AI_API::reset_all();
        wp_send_json_success(['msg' => 'Toutes les statistiques ont été supprimées.']);
    }

    /* ── AJAX : Export CSV ── */
    public function ajax_export(): void {
        check_ajax_referer('alesta_budget_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Accès refusé.');

        $daily = Alesta_AI_API::get_daily_stats(90);
        $rows  = [['Date', 'Tokens Input', 'Tokens Output', 'Appels', 'Coût (USD)']];
        foreach ($daily as $date => $d) {
            $rows[] = [
                $date,
                (int)   ($d['input']  ?? 0),
                (int)   ($d['output'] ?? 0),
                (int)   ($d['calls']  ?? 0),
                number_format((float)($d['cost'] ?? 0), 6, '.', ''),
            ];
        }
        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(
                fn($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                $row
            )) . "\r\n";
        }
        wp_send_json_success(['csv' => $csv, 'filename' => 'alesta-api-budget-' . gmdate('Y-m-d') . '.csv']);
    }

    /* ── Rendu de la page ── */
    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) wp_die('Accès refusé.');

        $usage   = Alesta_AI_API::get_usage_stats();
        $budget  = Alesta_AI_API::get_budget_settings();
        $monthly = Alesta_AI_API::get_monthly_stats();
        $daily30 = Alesta_AI_API::get_daily_stats(30);

        $month       = gmdate('Y-m');
        $mdata       = $monthly[$month] ?? [];
        $month_cost  = (float) ($mdata['cost']   ?? 0);
        $month_calls = (int)   ($mdata['calls']  ?? 0);
        $month_in    = (int)   ($mdata['input']  ?? 0);
        $month_out   = (int)   ($mdata['output'] ?? 0);

        // Jauge
        $limit      = $budget['monthly_limit'];
        $pct        = ($limit > 0) ? min(100, round(($month_cost / $limit) * 100, 1)) : 0;
        $gauge_cls  = ($pct >= 90) ? 'bgt-fill-danger' : (($pct >= $budget['alert_threshold']) ? 'bgt-fill-warn' : 'bgt-fill-ok');
        $pct_cls    = ($pct >= 90) ? 'bgt-pct-danger' : (($pct >= $budget['alert_threshold']) ? 'bgt-pct-warn' : 'bgt-pct-ok');

        // Graphique
        $costs      = array_column($daily30, 'cost');
        $max_cost   = !empty($costs) ? max($costs) : 0;
        $max_cost   = max($max_cost, 0.000001);
        ?>
        <div class="wrap" id="alesta-budget-wrap">

            <!-- ── En-tête ── -->
            <div class="bgt-header">
                <div class="bgt-header-left">
                    <span class="dashicons dashicons-chart-area bgt-header-icon"></span>
                    <div>
                        <h1>Budget API</h1>
                        <p>Suivi de consommation et limites mensuelles Claude (Anthropic)</p>
                    </div>
                </div>
                <div class="bgt-header-right">
                    <span class="bgt-cost-badge">
                        Total cumulé : <strong>$<?php echo esc_html(number_format($usage['total_cost_usd'], 4, ',', ' ')); ?></strong>
                        &nbsp;·&nbsp; <?php echo esc_html(number_format($usage['calls'])); ?> appels
                    </span>
                    <?php if (!empty($usage['since'])): ?>
                    <span class="bgt-since-badge">depuis le <?php echo esc_html(date_i18n('d M Y', strtotime($usage['since']))); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($usage['last_model'])): ?>
                    <span class="bgt-model-badge"><?php echo esc_html($usage['last_model']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Stats du mois ── -->
            <div class="bgt-section-title">📅 <?php echo esc_html(date_i18n('F Y')); ?></div>
            <div class="bgt-stats">
                <div class="bgt-card bgt-card-cost">
                    <div class="bgt-card-icon">💰</div>
                    <div class="bgt-card-num">$<?php echo esc_html(number_format($month_cost, 4, ',', ' ')); ?></div>
                    <div class="bgt-card-label">Coût ce mois</div>
                </div>
                <div class="bgt-card bgt-card-calls">
                    <div class="bgt-card-icon">🔁</div>
                    <div class="bgt-card-num"><?php echo esc_html(number_format($month_calls)); ?></div>
                    <div class="bgt-card-label">Appels API</div>
                </div>
                <div class="bgt-card bgt-card-in">
                    <div class="bgt-card-icon">📥</div>
                    <div class="bgt-card-num"><?php echo esc_html(number_format($month_in)); ?></div>
                    <div class="bgt-card-label">Tokens entrants</div>
                </div>
                <div class="bgt-card bgt-card-out">
                    <div class="bgt-card-icon">📤</div>
                    <div class="bgt-card-num"><?php echo esc_html(number_format($month_out)); ?></div>
                    <div class="bgt-card-label">Tokens sortants</div>
                </div>
            </div>

            <!-- ── Jauge budget ── -->
            <?php if ($limit > 0): ?>
            <div class="bgt-gauge-wrap">
                <div class="bgt-gauge-header">
                    <span class="bgt-gauge-title">🎯 Utilisation du budget mensuel</span>
                    <span class="bgt-gauge-pct <?php echo esc_attr($pct_cls); ?>">
                        <?php echo esc_html($pct); ?>% utilisé
                    </span>
                </div>
                <div class="bgt-gauge-bar">
                    <div class="bgt-gauge-fill <?php echo esc_attr($gauge_cls); ?>" style="width:<?php echo esc_attr($pct); ?>%;"></div>
                </div>
                <div class="bgt-gauge-legend">
                    <span>$<?php echo esc_html(number_format($month_cost, 4, ',', ' ')); ?> dépensés</span>
                    <span>Limite : $<?php echo esc_html(number_format($limit, 2, ',', ' ')); ?> / mois</span>
                </div>
                <?php if ($pct >= 100): ?>
                <div class="bgt-alert bgt-alert-danger">
                    ⛔ Limite mensuelle atteinte.
                    <?php if ($budget['block_on_limit']): ?>
                    Les appels API sont <strong>bloqués</strong> jusqu'à la fin du mois ou la hausse du budget.
                    <?php else: ?>
                    Mode avertissement actif — les appels <strong>continuent</strong>.
                    <?php endif; ?>
                </div>
                <?php elseif ($pct >= $budget['alert_threshold']): ?>
                <div class="bgt-alert bgt-alert-warn">
                    ⚠️ Seuil d'alerte atteint (<?php echo esc_html($budget['alert_threshold']); ?>%).
                    <?php if (!empty($budget['alert_email'])): ?>
                    Un email a été envoyé à <em><?php echo esc_html($budget['alert_email']); ?></em>.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="bgt-gauge-wrap bgt-gauge-empty">
                <span class="dashicons dashicons-info-outline" style="color:#f59e0b;margin-right:8px;vertical-align:middle;"></span>
                Aucun budget mensuel configuré.
                <a href="#bgt-settings">Définir un budget →</a>
            </div>
            <?php endif; ?>

            <!-- ── Graphique journalier ── -->
            <div class="bgt-chart-wrap">
                <div class="bgt-chart-header">
                    <span class="bgt-chart-title">📊 Dépenses quotidiennes — 30 derniers jours</span>
                    <button class="button bgt-btn-export" id="bgt-btn-export">⬇ Exporter CSV (90 jours)</button>
                </div>
                <?php if (empty($daily30)): ?>
                <div class="bgt-chart-empty">
                    Aucune donnée disponible. Les dépenses apparaîtront ici au fil des appels Claude.
                </div>
                <?php else: ?>
                <div class="bgt-chart-outer">
                    <div class="bgt-chart">
                        <?php foreach ($daily30 as $date => $d):
                            $cost  = (float)($d['cost']  ?? 0);
                            $calls = (int)  ($d['calls'] ?? 0);
                            $h     = max(2, round(($cost / $max_cost) * 100));
                            $tip   = date_i18n('d M Y', strtotime($date)) . ' — $' . number_format($cost, 5, ',', ' ') . ' · ' . $calls . ' appels';
                        ?>
                        <div class="bgt-bar-wrap" title="<?php echo esc_attr($tip); ?>">
                            <div class="bgt-bar" style="height:<?php echo esc_attr($h); ?>%;"></div>
                            <div class="bgt-bar-date"><?php echo esc_html(gmdate('d/m', strtotime($date))); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Historique mensuel ── -->
            <?php
            $monthly_sorted = $monthly;
            krsort($monthly_sorted);
            if (count($monthly_sorted) > 0):
            ?>
            <div class="bgt-monthly-wrap">
                <div class="bgt-section-title">🗓 Historique mensuel</div>
                <table class="bgt-monthly-table">
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th>Coût ($)</th>
                            <th>Appels</th>
                            <th>Tokens entrants</th>
                            <th>Tokens sortants</th>
                            <?php if ($limit > 0): ?><th>% du budget</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_sorted as $m => $d):
                            $m_cost = (float)($d['cost']   ?? 0);
                            $m_pct  = ($limit > 0) ? min(100, round(($m_cost / $limit) * 100, 1)) : null;
                        ?>
                        <tr <?php if ($m === $month) echo 'class="bgt-row-current"'; ?>>
                            <td><?php echo esc_html(date_i18n('F Y', strtotime($m . '-01'))); ?></td>
                            <td><strong>$<?php echo esc_html(number_format($m_cost, 4, ',', ' ')); ?></strong></td>
                            <td><?php echo esc_html(number_format((int)($d['calls']  ?? 0))); ?></td>
                            <td><?php echo esc_html(number_format((int)($d['input']  ?? 0))); ?></td>
                            <td><?php echo esc_html(number_format((int)($d['output'] ?? 0))); ?></td>
                            <?php if ($limit > 0): ?>
                            <td>
                                <div class="bgt-mini-bar-wrap">
                                    <div class="bgt-mini-bar" style="width:<?php echo esc_attr($m_pct ?? 0); ?>%;background:<?php echo ($m_pct >= 90 ? '#ef4444' : ($m_pct >= $budget['alert_threshold'] ? '#f59e0b' : '#10b981')); ?>;"></div>
                                </div>
                                <span class="bgt-mini-pct"><?php echo esc_html($m_pct ?? 0); ?>%</span>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- ── Réglages ── -->
            <div class="bgt-settings" id="bgt-settings">
                <div class="bgt-settings-header">
                    <h3>⚙️ Réglages du budget</h3>
                    <p>Définissez une limite mensuelle et configurez les alertes automatiques.</p>
                </div>
                <div class="bgt-settings-body">
                    <div class="bgt-settings-grid">

                        <div class="bgt-field">
                            <label for="bgt-limit">Budget mensuel maximum ($)</label>
                            <input type="number" id="bgt-limit" min="0" step="0.01" placeholder="Ex : 5.00"
                                   value="<?php echo esc_attr($limit > 0 ? $limit : ''); ?>">
                            <div class="bgt-field-note">Laissez vide pour ne pas fixer de limite.</div>
                        </div>

                        <div class="bgt-field">
                            <label>
                                Seuil d'alerte email :
                                <strong><span id="bgt-threshold-val"><?php echo esc_html($budget['alert_threshold']); ?></span>%</strong>
                            </label>
                            <input type="range" id="bgt-threshold" min="10" max="100" step="5"
                                   value="<?php echo esc_attr($budget['alert_threshold']); ?>">
                            <div class="bgt-field-note">Un email est envoyé quand ce pourcentage du budget est atteint.</div>
                        </div>

                        <div class="bgt-field">
                            <label>Si la limite est atteinte</label>
                            <div class="bgt-toggle-group">
                                <div class="bgt-toggle-opt <?php echo !$budget['block_on_limit'] ? 'active' : ''; ?>" data-val="0">
                                    ⚠️ Avertir seulement
                                </div>
                                <div class="bgt-toggle-opt <?php echo $budget['block_on_limit'] ? 'active' : ''; ?>" data-val="1">
                                    ⛔ Bloquer les appels
                                </div>
                            </div>
                            <input type="hidden" id="bgt-block" value="<?php echo esc_attr($budget['block_on_limit'] ? '1' : '0'); ?>">
                        </div>

                        <div class="bgt-field">
                            <label for="bgt-email">Email de notification</label>
                            <input type="email" id="bgt-email"
                                   placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
                                   value="<?php echo esc_attr($budget['alert_email']); ?>">
                            <div class="bgt-field-note">Laissez vide pour désactiver les alertes email.</div>
                        </div>

                    </div>
                    <div class="bgt-settings-footer">
                        <button class="button button-primary" id="bgt-btn-save">Enregistrer les réglages</button>
                        <span id="bgt-settings-msg"></span>
                    </div>
                </div>
            </div>

            <!-- ── Zone de réinitialisation ── -->
            <div class="bgt-danger">
                <div class="bgt-danger-header">
                    <h3>🗑 Réinitialisation des données</h3>
                </div>
                <div class="bgt-danger-body">
                    <div class="bgt-danger-item">
                        <button class="button" id="bgt-btn-reset-month">
                            Remettre à zéro ce mois
                        </button>
                        <p class="bgt-danger-desc">Réinitialise les statistiques de <?php echo esc_html(date_i18n('F Y')); ?> uniquement.</p>
                    </div>
                    <div class="bgt-danger-sep"></div>
                    <div class="bgt-danger-item">
                        <button class="button bgt-btn-danger" id="bgt-btn-reset-global">
                            Réinitialiser tout l'historique
                        </button>
                        <p class="bgt-danger-desc">Supprime l'intégralité de l'historique de consommation. Irréversible.</p>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }
}
