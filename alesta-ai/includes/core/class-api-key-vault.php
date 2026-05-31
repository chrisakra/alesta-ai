<?php
/**
 * Alesta AI — API Key Vault (v2.0 Free)
 *
 * Stockage chiffré AES-256-GCM de clés API tierces (Anthropic, OpenAI…).
 *
 * Migré depuis l'ancien Pro 1.3.x vers le Free 2.0 : c'est une infra
 * technique générique qui peut servir à TOUS les modules (Free et Pro).
 * Aucune logique métier IA/payante — juste du chiffrement at-rest.
 *
 * Le Pro peut étendre via le filtre `alesta_ai/vault/providers` pour ajouter
 * de nouveaux providers (OpenAI, Mistral, etc.).
 *
 * @package AlestaAI\Core
 * @since   2.0.0
 */

namespace AlestaAI\Core;

defined( 'ABSPATH' ) || exit;

final class APIKeyVault {

	/** Algorithme : AES-256 GCM (authenticated encryption) */
	private const CIPHER = 'aes-256-gcm';

	/** Taille IV pour GCM (12 bytes recommandés par NIST) */
	private const IV_LEN = 12;

	/** Taille tag d'authentification GCM (16 bytes standard) */
	private const TAG_LEN = 16;

	/** Nom de l'option WP pour un provider donné */
	private static function option_name( string $provider ): string {
		return 'alesta_ai_vault_' . sanitize_key( $provider ) . '_enc';
	}

	// =========================================================================
	// API PUBLIQUE
	// =========================================================================

	/**
	 * Récupère la clé déchiffrée pour un provider.
	 *
	 * @param string $provider ex. "anthropic", "openai"
	 * @return string|null     Clé en clair, ou null si pas configurée
	 */
	public static function get( string $provider ): ?string {
		$enc = get_option( self::option_name( $provider ), null );
		if ( ! $enc ) {
			// Fallback : migrer depuis option legacy plaintext si présente
			$legacy = self::migrate_legacy( $provider );
			return $legacy;
		}

		return self::decrypt( $enc );
	}

	/**
	 * Stocke la clé chiffrée pour un provider.
	 *
	 * @param string $provider
	 * @param string $key Clé en clair (sera chiffrée avant stockage DB)
	 * @return bool       true si stockée OK
	 */
	public static function set( string $provider, string $key ): bool {
		if ( strlen( $key ) < 10 ) {
			return false;
		}
		$enc = self::encrypt( $key );
		if ( $enc === null ) {
			return false;
		}
		update_option( self::option_name( $provider ), $enc, false );

		do_action( 'alesta_ai/vault/key_updated', $provider );
		return true;
	}

	/**
	 * Supprime la clé pour un provider.
	 *
	 * @param string $provider
	 */
	public static function delete( string $provider ): bool {
		do_action( 'alesta_ai/vault/key_deleted', $provider );
		return delete_option( self::option_name( $provider ) );
	}

	/**
	 * True si une clé est configurée pour ce provider.
	 *
	 * @param string $provider
	 * @return bool
	 */
	public static function has( string $provider ): bool {
		$key = self::get( $provider );
		return is_string( $key ) && strlen( $key ) > 10;
	}

	/**
	 * Liste des providers supportés.
	 * Le Free ne déclare qu'Anthropic. Le Pro peut en ajouter via le filtre.
	 *
	 * @return array<string, array{label: string, key_prefix: string, docs_url: string}>
	 */
	public static function providers(): array {
		$defaults = [
			'anthropic' => [
				'label'      => 'Anthropic (Claude)',
				'key_prefix' => 'sk-ant-',
				'docs_url'   => 'https://console.anthropic.com/settings/keys',
			],
		];
		return apply_filters( 'alesta_ai/vault/providers', $defaults );
	}

	// =========================================================================
	// CHIFFREMENT
	// =========================================================================

	/**
	 * Chiffre une chaîne en AES-256-GCM avec AUTH_KEY comme master key.
	 * AUTH_KEY est une constante PHP de wp-config.php (64 chars random générés
	 * par WordPress à l'installation). C'est l'équivalent d'une "machine key".
	 *
	 * @param string $plaintext
	 * @return string|null base64-encoded blob (iv + ciphertext + tag), ou null si erreur
	 */
	private static function encrypt( string $plaintext ): ?string {
		if ( ! defined( 'AUTH_KEY' ) || strlen( AUTH_KEY ) < 32 ) {
			return null;
		}

		$key = hash( 'sha256', AUTH_KEY, true ); // 32 bytes pour AES-256
		$iv  = random_bytes( self::IV_LEN );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN );
		if ( $ct === false ) {
			return null;
		}

		return base64_encode( $iv . $ct . $tag );
	}

	/**
	 * Déchiffre un blob produit par self::encrypt().
	 *
	 * @param string $blob base64-encoded
	 * @return string|null
	 */
	private static function decrypt( string $blob ): ?string {
		if ( ! defined( 'AUTH_KEY' ) ) {
			return null;
		}
		$raw = base64_decode( $blob, true );
		if ( $raw === false || strlen( $raw ) < self::IV_LEN + self::TAG_LEN + 1 ) {
			return null;
		}

		$iv  = substr( $raw, 0, self::IV_LEN );
		$tag = substr( $raw, -self::TAG_LEN );
		$ct  = substr( $raw, self::IV_LEN, -self::TAG_LEN );

		$key = hash( 'sha256', AUTH_KEY, true );
		$pt  = openssl_decrypt( $ct, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );
		return $pt === false ? null : $pt;
	}

	// =========================================================================
	// MIGRATION LEGACY
	// =========================================================================

	/**
	 * Migration douce depuis options legacy plaintext (ancien Pro 1.x).
	 * Lit la valeur, la réécrit chiffrée, supprime l'ancienne.
	 *
	 * @param string $provider
	 * @return string|null Plaintext key si migration faite, null sinon
	 */
	private static function migrate_legacy( string $provider ): ?string {
		// Mappings provider → ancien nom d'option (v1.3.x)
		$legacy_keys = [
			'anthropic' => [ 'alesta_ai_api_key', 'alesta_ai_anthropic_api_key' ],
		];
		if ( ! isset( $legacy_keys[ $provider ] ) ) {
			return null;
		}

		foreach ( $legacy_keys[ $provider ] as $legacy_opt ) {
			$plaintext = get_option( $legacy_opt, '' );
			if ( $plaintext && strlen( $plaintext ) > 10 ) {
				self::set( $provider, $plaintext );
				delete_option( $legacy_opt );
				do_action( 'alesta_ai/vault/legacy_migrated', $provider, $legacy_opt );
				return $plaintext;
			}
		}
		return null;
	}
}
