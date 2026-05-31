<?php
/**
 * Alesta AI — Extensions API
 *
 * Surface publique d'extension exposée par le plugin Free aux plugins tiers
 * (typiquement : Alesta AI Pro, mais aussi de futures extensions communautaires).
 *
 * @package AlestaAI\Core
 * @since   2.0.0
 */

namespace AlestaAI\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Classe statique : pas d'instance.
 *
 * Pourquoi statique et pas singleton ?
 *   - Les hooks/filters WordPress sont des callables : passer une instance pour
 *     `add_filter` rajoute du couplage inutile.
 *   - L'API est sans état : tout est dans WP's hook system.
 *   - Plus simple à tester (pas de reset de singleton entre tests).
 *
 * Convention de nommage des hooks : `alesta_ai/{domaine}/{action}`.
 *   - underscore dans le namespace (`alesta_ai`)
 *   - slash pour la hiérarchie (Elementor, WooCommerce HPOS suivent ce style)
 *   - kebab-case dans les noms de domaine si multi-mot
 */
final class ExtensionsAPI {

	/**
	 * Bootstrap appelé depuis alesta-ai.php.
	 *
	 * Pour l'instant ne fait rien (toutes les méthodes sont statiques et idempotentes).
	 * On garde le point d'entrée pour pouvoir y ajouter plus tard de la télémétrie,
	 * du logging du contrat d'API ou des sanity checks de version.
	 */
	public static function init(): void {
		// no-op pour l'instant
	}

	// =========================================================================
	// API DE DÉCOUVERTE — utilisée par le Pro pour savoir ce qui est dispo
	// =========================================================================

	/**
	 * Retourne true si le plugin Pro est actif et chargé.
	 *
	 * Utilisée par les modules Free pour décider :
	 *   - d'afficher un upsell ("Débloquez X avec Pro")  →  is_pro_active() === false
	 *   - de déléguer une feature au Pro                  →  is_pro_active() === true
	 *
	 * @return bool
	 */
	public static function is_pro_active(): bool {
		return defined( 'ALESTA_AI_PRO_VERSION' );
	}

	/**
	 * Version du Pro (si actif), null sinon.
	 *
	 * @return string|null
	 */
	public static function pro_version(): ?string {
		return defined( 'ALESTA_AI_PRO_VERSION' ) ? ALESTA_AI_PRO_VERSION : null;
	}

	/**
	 * Retourne la liste des providers IA enregistrés (par le Pro).
	 *
	 * Filtre : `alesta_ai/ai/providers`
	 *   Format attendu : array<string, AIProviderInterface>
	 *   Clé = slug ("claude", "openai", "mistral"), valeur = instance du provider.
	 *
	 * En Free pur (sans Pro) : retourne `[]`.
	 *
	 * @return array<string, object>
	 */
	public static function get_ai_providers(): array {
		$providers = apply_filters( 'alesta_ai/ai/providers', [] );
		return is_array( $providers ) ? $providers : [];
	}

	/**
	 * Récupère un provider IA par son slug.
	 *
	 * @param string $slug ex. "claude"
	 * @return object|null
	 */
	public static function get_ai_provider( string $slug ): ?object {
		$providers = self::get_ai_providers();
		return $providers[ $slug ] ?? null;
	}

	/**
	 * Liste des features Pro actuellement disponibles (déclarées par le Pro).
	 *
	 * Filtre : `alesta_ai/pro/features`
	 *   Format : array<string, array{label: string, description: string, icon?: string}>
	 *
	 * Utilisée pour afficher la page "Découvrir Pro" même quand le Pro n'est pas actif
	 * (les features sont alors récupérées depuis un JSON statique embarqué côté Free).
	 *
	 * @return array<string, array>
	 */
	public static function get_pro_features(): array {
		$features = apply_filters( 'alesta_ai/pro/features', [] );
		return is_array( $features ) ? $features : [];
	}

	// =========================================================================
	// API D'INJECTION UI — le Pro accroche ses boutons IA dans les modules Free
	// =========================================================================
	//
	// Pattern : pour CHAQUE module SPLIT (redirects, scripts, perf-audit,
	// filenames, talk-to-me), on définit ~2 hooks bien nommés :
	//
	//   - `alesta_ai/admin/{module}/actions`     → injecte des boutons d'action
	//   - `alesta_ai/admin/{module}/sidebar`     → injecte un panneau latéral
	//
	// Le Free RENDU sans IA = comportement classique 100% local.
	// Le Pro PEUT injecter via les hooks pour ajouter "✨ Suggérer via IA".
	//
	// Les helpers ci-dessous wrappent les `do_action` pour offrir une API typée.
	// =========================================================================

	/**
	 * Helper rendu côté Free : émet le hook d'injection d'actions pour un module.
	 *
	 * À appeler depuis les templates admin Free, ex. :
	 *   <?php ExtensionsAPI::render_module_actions('redirects', ['post_id' => 42]); ?>
	 *
	 * Le Pro y attache ses boutons IA via :
	 *   add_action('alesta_ai/admin/redirects/actions', function($ctx) { ... });
	 *
	 * @param string $module_slug
	 * @param array  $context     Données partagées avec les hooks (post_id, settings…)
	 */
	public static function render_module_actions( string $module_slug, array $context = [] ): void {
		do_action( "alesta_ai/admin/{$module_slug}/actions", $context );
	}

	/**
	 * Pendant pour la sidebar contextuelle (panneau droit des pages admin).
	 *
	 * @param string $module_slug
	 * @param array  $context
	 */
	public static function render_module_sidebar( string $module_slug, array $context = [] ): void {
		do_action( "alesta_ai/admin/{$module_slug}/sidebar", $context );
	}

	// =========================================================================
	// API IA — BYOK (Bring Your Own Key Anthropic)
	// =========================================================================
	//
	// IMPORTANT : Alesta n'héberge JAMAIS de proxy IA. Chaque site utilise
	// SA propre clé Anthropic (stockée chiffrée via APIKeyVault). Le quota
	// est donc géré par Anthropic directement, pas par Alesta.
	//
	// Avantages BYOK :
	//   - Zéro coût opérationnel pour Alesta
	//   - Conformité RGPD simplifiée (data client → Anthropic, sans intermédiaire)
	//   - Pas de risque de leak de clé master Alesta
	//   - Client garde 100% contrôle facturation Claude
	//
	// Le Free expose Talk-to-Me comme widget statique uniquement. Si le user
	// veut activer le chat IA dans Talk-to-Me, il doit (a) configurer sa clé
	// Anthropic dans les réglages (gratuit, accessible aux Free) ET/OU
	// (b) acheter Alesta AI Pro qui débloque les modules IA avancés.

	/**
	 * Récupère la clé Anthropic chiffrée stockée pour ce site.
	 * Retourne null si pas configurée.
	 *
	 * Si le Pro est actif, il fournit son propre APIKeyVault avec gestion
	 * avancée (rotation, audit, multi-providers).
	 *
	 * @return string|null
	 */
	public static function get_user_anthropic_key(): ?string {
		if ( class_exists( '\\AlestaAI\\Core\\APIKeyVault' ) ) {
			return \AlestaAI\Core\APIKeyVault::get( 'anthropic' );
		}
		return null;
	}

	/**
	 * True si l'utilisateur a configuré une clé Anthropic valide.
	 * @return bool
	 */
	public static function has_user_anthropic_key(): bool {
		$key = self::get_user_anthropic_key();
		return is_string( $key ) && strlen( $key ) > 20 && strpos( $key, 'sk-ant-' ) === 0;
	}
}
