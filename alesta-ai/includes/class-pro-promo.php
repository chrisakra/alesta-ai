<?php
/**
 * Alesta AI — Pro feature promotional page.
 *
 * Renders a static informational page for features that are available in
 * the separate "Alesta AI Pro" plugin distributed outside WordPress.org.
 *
 * No license check, no payment flow, no remote calls — purely informational
 * with a single external link to the product page.
 */

defined('ABSPATH') || exit;

class Alesta_AI_Pro_Promo {

    const PRO_URL = 'https://www.alesta-ai.com/tarifs.html';

    /**
     * Renders the "Available in Alesta AI Pro" page for a given feature.
     */
    public static function render( string $feature_name, string $feature_desc = '', string $icon = '✨' ): void {
        wp_enqueue_style(
            'alesta-pro-promo',
            ALESTA_AI_URL . 'assets/pro-promo.css',
            [],
            ALESTA_AI_VERSION
        );
        ?>
        <div class="wrap alesta-wrap">
            <div class="alesta-pro-promo-wrap">
                <div class="alesta-pro-promo-card">

                    <div class="alesta-pro-promo-badge">Alesta AI Pro</div>

                    <div class="alesta-pro-promo-icon"><?php echo esc_html($icon); ?></div>

                    <h2 class="alesta-pro-promo-title">
                        <?php echo esc_html($feature_name); ?>
                    </h2>

                    <?php if ( $feature_desc ) : ?>
                    <p class="alesta-pro-promo-desc">
                        <?php echo esc_html($feature_desc); ?>
                    </p>
                    <?php endif; ?>

                    <p class="alesta-pro-promo-info">
                        <?php esc_html_e('Cette fonctionnalité fait partie d\'Alesta AI Pro, une extension distincte distribuée en dehors du dépôt WordPress.org.', 'alesta-ai'); ?>
                    </p>

                    <a href="<?php echo esc_url(self::PRO_URL); ?>"
                       class="alesta-pro-promo-btn"
                       target="_blank"
                       rel="noopener noreferrer">
                        <?php esc_html_e('Découvrir Alesta AI Pro', 'alesta-ai'); ?> &rarr;
                    </a>

                    <p class="alesta-pro-promo-note">
                        <?php esc_html_e('Vous serez redirigé vers alesta-ai.com.', 'alesta-ai'); ?>
                    </p>

                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Returns a small badge HTML to display next to feature names on the
     * dashboard. Purely a label — no functional restriction.
     *
     * @param string $tier  'solo' (light pill) or 'pro' (filled pill)
     */
    public static function dashboard_badge( string $tier = 'solo' ): string {
        $class = $tier === 'pro' ? 'alesta-pro-badge alesta-pro-badge--pro' : 'alesta-pro-badge';
        $label = $tier === 'pro' ? 'Pro' : 'Solo';
        return '<span class="' . $class . '">' . $label . '</span>';
    }
}
