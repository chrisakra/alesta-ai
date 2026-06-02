/**
 * Alesta AI Pro — Keywords AI module front script
 *
 * Extrait depuis le module class-keywords-ai-module.php (v2.0.4) où le script
 * était inliné. Refactor pour conformité best practices WP (wp_enqueue_script
 * + wp_localize_script pour les variables PHP -> JS via objet AlestaAIKeywords).
 *
 * Variables PHP injectées via wp_localize_script :
 *   window.AlestaAIKeywords = {
 *     restUrl: '<?php echo esc_url_raw( rest_url( 'alesta-ai-pro/v1/keywords/generate' ) ); ?>',
 *     nonce:   '<?php echo wp_create_nonce( 'wp_rest' ); ?>',
 *     i18n:    { generating, suggested, generic_error, network_error, button_label },
 *   }
 *
 * @package AlestaAIPro\Modules\Seo
 * @since   2.0.4
 */

( function () {
	'use strict';

	function init() {
		var btn = document.getElementById( 'alesta-ai-keywords-generate' );
		if ( ! btn || ! window.AlestaAIKeywords ) {
			return;
		}

		var config = window.AlestaAIKeywords;
		var result = document.getElementById( 'alesta-ai-keywords-result' );

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = config.i18n.generating;

			// Parse le contexte data-attribute (JSON injecté par esc_attr+wp_json_encode côté PHP)
			var context = {};
			try {
				context = JSON.parse( btn.dataset.context || '{}' );
			} catch ( e ) {
				context = {};
			}

			fetch( config.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				body: JSON.stringify( { context: context } ),
			} )
				.then( function ( res ) { return res.json(); } )
				.then( function ( data ) {
					renderResult( result, data, config );
				} )
				.catch( function ( err ) {
					renderError( result, config.i18n.network_error + ' ' + ( err && err.message ? err.message : '' ) );
				} )
				.then( function () {
					// finally — réinitialise le bouton
					btn.disabled = false;
					btn.textContent = config.i18n.button_label;
				} );
		} );
	}

	/**
	 * Rendu sécurisé du résultat : on construit le DOM via createElement +
	 * textContent pour éviter toute injection XSS, plutôt que innerHTML.
	 */
	function renderResult( container, data, config ) {
		if ( ! container ) {
			return;
		}
		// Vide le conteneur
		while ( container.firstChild ) {
			container.removeChild( container.firstChild );
		}

		if ( data && Array.isArray( data.keywords ) && data.keywords.length ) {
			var p = document.createElement( 'p' );
			var strong = document.createElement( 'strong' );
			strong.textContent = config.i18n.suggested;
			p.appendChild( strong );
			container.appendChild( p );

			var ul = document.createElement( 'ul' );
			data.keywords.forEach( function ( k ) {
				var li = document.createElement( 'li' );
				li.textContent = String( k );
				ul.appendChild( li );
			} );
			container.appendChild( ul );
			return;
		}

		var message = ( data && data.message ) ? String( data.message ) : config.i18n.generic_error;
		renderError( container, message );
	}

	function renderError( container, message ) {
		if ( ! container ) {
			return;
		}
		while ( container.firstChild ) {
			container.removeChild( container.firstChild );
		}
		var p = document.createElement( 'p' );
		p.className = 'error';
		p.textContent = message;
		container.appendChild( p );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
