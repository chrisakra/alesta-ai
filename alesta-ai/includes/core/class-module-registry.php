<?php
/**
 * Alesta AI — Module Registry
 *
 * Registre central des modules Free + Pro. C'est le mécanisme qui permet
 * au Pro de :
 *   - Découvrir tous les modules Free chargés (et leurs metadata)
 *   - Enregistrer ses propres modules Pro
 *   - Overrider un module Free (ex. remplacer KeywordsModule statique par KeywordsAIModule)
 *
 * @package AlestaAI\Core
 * @since   2.0.0
 */

namespace AlestaAI\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton — on a besoin d'un état partagé (la liste des modules) entre
 * tous les modules qui s'enregistrent successivement.
 */
final class ModuleRegistry {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Liste des modules enregistrés.
	 *
	 * @var array<string, array{class: string, source: string, instance: ?object, meta: array}>
	 */
	private $modules = [];

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// =========================================================================
	// REGISTRATION
	// =========================================================================

	/**
	 * Enregistre un module.
	 *
	 * @param string $slug    ID unique du module (ex. "seo/sitemap", "content/improve")
	 * @param string $class   FQCN de la classe du module (sera instancié à la demande)
	 * @param array  $meta    Metadata : name, description, source ('free'|'pro'), category, icon, capability_required…
	 * @return bool true si enregistré, false si déjà existant (sauf override explicite)
	 */
	public function register( string $slug, string $class, array $meta = [] ): bool {
		$source = $meta['source'] ?? 'free';

		// Anti-doublon — sauf override Pro→Free explicite (cas KeywordsAIModule remplace KeywordsModule)
		if ( isset( $this->modules[ $slug ] ) ) {
			$existing = $this->modules[ $slug ];
			$is_override = ( $source === 'pro' && $existing['source'] === 'free' );
			if ( ! $is_override ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf( 'Module "%s" déjà enregistré (source: %s)', $slug, $existing['source'] ),
					'2.0.0'
				);
				return false;
			}
			// Hook informatif : Pro remplace une implémentation Free.
			do_action( 'alesta_ai/module/overridden', $slug, $existing, $class );
		}

		$this->modules[ $slug ] = [
			'class'    => $class,
			'source'   => $source,
			'instance' => null, // lazy : pas instancié tant que pas demandé
			'meta'     => array_merge( [
				'name'                => $slug,
				'description'         => '',
				'category'            => 'other',
				'icon'                => null,
				'capability_required' => 'manage_alesta_ai',
			], $meta ),
		];

		do_action( 'alesta_ai/module/registered', $slug, $this->modules[ $slug ] );

		return true;
	}

	/**
	 * Enregistre un module Pro (raccourci sémantique).
	 *
	 * @param string $slug
	 * @param string $class
	 * @param array  $meta
	 * @return bool
	 */
	public function register_pro( string $slug, string $class, array $meta = [] ): bool {
		$meta['source'] = 'pro';
		return $this->register( $slug, $class, $meta );
	}

	// =========================================================================
	// LOOKUP & INSTANCIATION
	// =========================================================================

	/**
	 * Retourne l'instance d'un module (la crée à la demande).
	 *
	 * @param string $slug
	 * @return object|null
	 */
	public function get( string $slug ): ?object {
		if ( ! isset( $this->modules[ $slug ] ) ) {
			return null;
		}

		if ( null === $this->modules[ $slug ]['instance'] ) {
			$class = $this->modules[ $slug ]['class'];
			if ( ! class_exists( $class ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf( 'Classe "%s" introuvable pour le module "%s"', $class, $slug ),
					'2.0.0'
				);
				return null;
			}
			$this->modules[ $slug ]['instance'] = new $class();
		}

		return $this->modules[ $slug ]['instance'];
	}

	/**
	 * Liste tous les modules enregistrés (sans les instancier).
	 *
	 * @param array $filter Filtre optionnel : ['source' => 'pro', 'category' => 'seo'…]
	 * @return array<string, array>
	 */
	public function all( array $filter = [] ): array {
		if ( empty( $filter ) ) {
			return $this->modules;
		}
		return array_filter( $this->modules, function ( $module ) use ( $filter ) {
			foreach ( $filter as $key => $value ) {
				$actual = $module['meta'][ $key ] ?? ( $module[ $key ] ?? null );
				if ( $actual !== $value ) {
					return false;
				}
			}
			return true;
		} );
	}

	/**
	 * Test d'existence.
	 *
	 * @param string $slug
	 * @return bool
	 */
	public function has( string $slug ): bool {
		return isset( $this->modules[ $slug ] );
	}
}
