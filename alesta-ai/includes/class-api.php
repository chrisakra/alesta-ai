<?php
defined('ABSPATH') || exit;

class Alesta_AI_API {

    private $api_key;
    private $model;
    private $endpoint = 'https://api.anthropic.com/v1/messages';

    // Tarifs en $ par million de tokens (input / output)
    private static $pricing = [
        'claude-opus-4'              => ['input' => 15.00, 'output' => 75.00],
        'claude-sonnet-4-5'          => ['input' =>  3.00, 'output' => 15.00],
        'claude-3-5-sonnet-20241022' => ['input' =>  3.00, 'output' => 15.00],
        'claude-3-5-haiku-20241022'  => ['input' =>  0.80, 'output' =>  4.00],
        'claude-haiku-4-5'           => ['input' =>  0.80, 'output' =>  4.00],
        'claude-3-haiku-20240307'    => ['input' =>  0.25, 'output' =>  1.25],
        'claude-3-opus-20240229'     => ['input' => 15.00, 'output' => 75.00],
    ];

    const USAGE_OPTION   = 'alesta_ai_api_usage';
    const MONTHLY_OPTION = 'alesta_ai_monthly_usage';
    const DAILY_OPTION   = 'alesta_ai_daily_usage';
    const BUDGET_OPTION  = 'alesta_ai_budget';

    public function __construct() {
        $this->api_key = get_option('alesta_ai_api_key', '');
        $this->model   = get_option('alesta_ai_model', 'claude-sonnet-4-5');
    }

    /**
     * Envoie un message à Claude et retourne la réponse texte.
     * Tracke automatiquement les tokens consommés.
     *
     * @param string $prompt
     * @param int    $max_tokens
     * @return string|WP_Error
     */
    public function ask(string $prompt, int $max_tokens = 1024) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Clé API Anthropic non configurée.');
        }

        // Vérification du budget mensuel
        $budget = self::get_budget_settings();
        if ($budget['monthly_limit'] > 0 && $budget['block_on_limit']) {
            $monthly    = get_option(self::MONTHLY_OPTION, []);
            $month      = gmdate('Y-m');
            $month_cost = (float) ($monthly[$month]['cost'] ?? 0.0);
            if ($month_cost >= $budget['monthly_limit']) {
                return new WP_Error(
                    'budget_exceeded',
                    sprintf('Budget API mensuel atteint (%.4f$ / %.2f$). Augmentez votre limite dans Budget API.', $month_cost, $budget['monthly_limit'])
                );
            }
        }

        $response = wp_remote_post($this->endpoint, [
            'timeout' => 60,
            'headers' => [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => $this->model,
                'max_tokens' => $max_tokens,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = $body['error']['message'] ?? 'Erreur API inconnue.';
            return new WP_Error('api_error', $msg);
        }

        // Tracker les tokens de cette requête
        if (!empty($body['usage'])) {
            $this->track_usage(
                (int) ($body['usage']['input_tokens']  ?? 0),
                (int) ($body['usage']['output_tokens'] ?? 0),
                $body['model'] ?? $this->model
            );
        }

        return $body['content'][0]['text'] ?? '';
    }

    /**
     * Accumule les tokens dans l'option WordPress.
     */
    private function track_usage(int $input, int $output, string $model): void {
        $usage = get_option(self::USAGE_OPTION, []);

        $usage['total_input']  = (int) ($usage['total_input']  ?? 0) + $input;
        $usage['total_output'] = (int) ($usage['total_output'] ?? 0) + $output;
        $usage['calls']        = (int) ($usage['calls']        ?? 0) + 1;
        $usage['last_model']   = $model;
        $usage['last_call']    = current_time('mysql');
        if (empty($usage['since'])) {
            $usage['since'] = current_time('mysql');
        }

        // Coût cumulé
        $usage['total_cost_usd'] = self::compute_cost(
            $usage['total_input'],
            $usage['total_output'],
            $model
        );

        update_option(self::USAGE_OPTION, $usage, false);

        // ── Tracking mensuel ──
        $month   = gmdate('Y-m');
        $monthly = get_option(self::MONTHLY_OPTION, []);
        $monthly[$month]['input']  = (int) ($monthly[$month]['input']  ?? 0) + $input;
        $monthly[$month]['output'] = (int) ($monthly[$month]['output'] ?? 0) + $output;
        $monthly[$month]['calls']  = (int) ($monthly[$month]['calls']  ?? 0) + 1;
        $monthly[$month]['cost']   = self::compute_cost(
            $monthly[$month]['input'], $monthly[$month]['output'], $model
        );
        // Alerte email si seuil atteint
        $budget = self::get_budget_settings();
        if ($budget['monthly_limit'] > 0 && !empty($budget['alert_email'])) {
            $limit     = $budget['monthly_limit'];
            $threshold = $budget['alert_threshold'] / 100;
            $cost_now  = $monthly[$month]['cost'];
            $sent_at   = (float) ($monthly[$month]['alert_sent'] ?? 0);
            if ($cost_now >= $limit * $threshold && $sent_at < $limit * $threshold) {
                $monthly[$month]['alert_sent'] = $cost_now;
                wp_mail(
                    $budget['alert_email'],
                    '[Alesta AI] Seuil de budget API atteint',
                    sprintf(
                        "Bonjour,\n\nVotre budget API mensuel Alesta AI a atteint %d%% de la limite fixée.\n\nDépenses : %.4f$ sur %.2f$ autorisés.\n\nGérez votre budget depuis le tableau de bord Alesta AI.",
                        $budget['alert_threshold'], $cost_now, $limit
                    )
                );
            }
        }
        update_option(self::MONTHLY_OPTION, $monthly, false);

        // ── Tracking journalier ──
        $day   = gmdate('Y-m-d');
        $daily = get_option(self::DAILY_OPTION, []);
        if (count($daily) > 90) {
            ksort($daily);
            $daily = array_slice($daily, -90, null, true);
        }
        $daily[$day]['input']  = (int) ($daily[$day]['input']  ?? 0) + $input;
        $daily[$day]['output'] = (int) ($daily[$day]['output'] ?? 0) + $output;
        $daily[$day]['calls']  = (int) ($daily[$day]['calls']  ?? 0) + 1;
        $daily[$day]['cost']   = self::compute_cost(
            $daily[$day]['input'], $daily[$day]['output'], $model
        );
        update_option(self::DAILY_OPTION, $daily, false);
    }

    /**
     * Calcule le coût en USD pour des tokens donnés.
     */
    public static function compute_cost(int $input, int $output, string $model): float {
        // Correspondance partielle : on cherche le modèle le plus proche
        $price = null;
        foreach (self::$pricing as $key => $p) {
            if (strpos($model, $key) !== false || strpos($key, $model) !== false) {
                $price = $p;
                break;
            }
        }
        // Fallback sur Sonnet si modèle inconnu
        if (!$price) {
            $price = self::$pricing['claude-sonnet-4-5'];
        }
        return round(
            ($input  / 1_000_000) * $price['input'] +
            ($output / 1_000_000) * $price['output'],
            6
        );
    }

    /**
     * Retourne les statistiques d'usage cumulées.
     */
    public static function get_usage_stats(): array {
        $usage = get_option(self::USAGE_OPTION, []);
        return [
            'total_input'    => (int)   ($usage['total_input']    ?? 0),
            'total_output'   => (int)   ($usage['total_output']   ?? 0),
            'calls'          => (int)   ($usage['calls']          ?? 0),
            'total_cost_usd' => (float) ($usage['total_cost_usd'] ?? 0.0),
            'last_model'     => (string)($usage['last_model']     ?? ''),
            'last_call'      => (string)($usage['last_call']      ?? ''),
            'since'          => (string)($usage['since']          ?? ''),
        ];
    }

    /**
     * Remet le compteur global à zéro.
     */
    public static function reset_usage(): void {
        delete_option(self::USAGE_OPTION);
    }

    /**
     * Remet à zéro uniquement le mois en cours.
     */
    public static function reset_monthly(): void {
        $month   = gmdate('Y-m');
        $monthly = get_option(self::MONTHLY_OPTION, []);
        unset($monthly[$month]);
        update_option(self::MONTHLY_OPTION, $monthly, false);
    }

    /**
     * Supprime absolument tout l'historique.
     */
    public static function reset_all(): void {
        delete_option(self::USAGE_OPTION);
        delete_option(self::MONTHLY_OPTION);
        delete_option(self::DAILY_OPTION);
    }

    /**
     * Statistiques mensuelles (toutes les périodes).
     */
    public static function get_monthly_stats(): array {
        return get_option(self::MONTHLY_OPTION, []);
    }

    /**
     * Statistiques journalières (N derniers jours).
     */
    public static function get_daily_stats(int $days = 30): array {
        $daily = get_option(self::DAILY_OPTION, []);
        ksort($daily);
        return array_slice($daily, -$days, null, true);
    }

    /**
     * Retourne les réglages du budget.
     */
    public static function get_budget_settings(): array {
        $b = get_option(self::BUDGET_OPTION, []);
        return [
            'monthly_limit'   => (float)  ($b['monthly_limit']   ?? 0),
            'alert_threshold' => (int)    ($b['alert_threshold'] ?? 80),
            'block_on_limit'  => (bool)   ($b['block_on_limit']  ?? false),
            'alert_email'     => (string) ($b['alert_email']     ?? get_option('admin_email', '')),
        ];
    }

    /**
     * Sauvegarde les réglages du budget.
     */
    public static function save_budget_settings(array $data): void {
        update_option(self::BUDGET_OPTION, [
            'monthly_limit'   => max(0, (float) ($data['monthly_limit']   ?? 0)),
            'alert_threshold' => max(1, min(100, (int) ($data['alert_threshold'] ?? 80))),
            'block_on_limit'  => !empty($data['block_on_limit']),
            'alert_email'     => sanitize_email($data['alert_email'] ?? ''),
        ]);
    }

    /**
     * Teste la connexion API.
     */
    public function test_connection() {
        $result = $this->ask('Réponds uniquement "OK" en un seul mot.', 10);
        if (is_wp_error($result)) {
            return $result;
        }
        return true;
    }
}
