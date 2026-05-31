<?php
/**
 * Alesta AI Pro — Purchase Flow
 *
 * Petite classe utilitaire pour générer les URLs de souscription/upgrade
 * vers alesta-ai.com (site marketing).
 *
 * Ajoute des UTM params trackés côté analytics + l'email du user actuel
 * pour préremplir le checkout Stripe (réduit la friction).
 *
 * @package AlestaAIPro\License
 * @since   2.0.0
 */

namespace AlestaAIPro\License;

defined( 'ABSPATH' ) || exit;

final class PurchaseFlow {

	private const BASE_URL = 'https://www.alesta-ai.com';

	/**
	 * URL de la page produit (pricing).
	 *
	 * @param string $utm_source Default = "plugin" (vs ads, organic, etc.)
	 * @param string $utm_medium Default = "wp-admin"
	 * @param string $utm_campaign Optionnel — page d'origine (ex. "settings", "upsell-keywords")
	 * @return string
	 */
	public static function pricing_url(
		string $utm_source = 'plugin',
		string $utm_medium = 'wp-admin',
		string $utm_campaign = ''
	): string {
		$query = [
			'utm_source'   => $utm_source,
			'utm_medium'   => $utm_medium,
			'utm_campaign' => $utm_campaign ?: 'license-page',
		];
		$user_email = wp_get_current_user()->user_email ?? '';
		if ( $user_email ) {
			$query['email'] = $user_email;
		}
		return self::BASE_URL . '/pricing?' . http_build_query( $query );
	}

	/**
	 * URL d'upgrade direct vers un plan donné (skip pricing page).
	 *
	 * @param string $plan ex. "AAP_SOLO", "AAP_AGENCY", "AAP_UNLIMITED"
	 * @param string $utm_campaign
	 * @return string
	 */
	public static function checkout_url( string $plan, string $utm_campaign = 'inline-upgrade' ): string {
		$query = [
			'plan'         => $plan,
			'utm_source'   => 'plugin',
			'utm_medium'   => 'wp-admin',
			'utm_campaign' => $utm_campaign,
		];
		$user_email = wp_get_current_user()->user_email ?? '';
		if ( $user_email ) {
			$query['email'] = $user_email;
		}
		return self::BASE_URL . '/checkout?' . http_build_query( $query );
	}

	/**
	 * URL du portail client (gestion abonnement, factures).
	 *
	 * @return string
	 */
	public static function billing_portal_url(): string {
		return self::BASE_URL . '/account/billing';
	}

	/**
	 * URL de la documentation (install, troubleshoot).
	 *
	 * @param string $page Section spécifique (ex. "installation", "license-activation")
	 * @return string
	 */
	public static function docs_url( string $page = '' ): string {
		return self::BASE_URL . '/docs' . ( $page ? '/' . ltrim( $page, '/' ) : '' );
	}
}
