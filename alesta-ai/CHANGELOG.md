# Changelog — Alesta AI

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.2.5] — 2026-06-07

### Changed
* WordPress.org compliance pass: inline CSS/JS migrated to wp_enqueue, removed ABSPATH/WP_CONTENT_DIR hardcoded paths (replaced with get_home_path()), removed set_time_limit() calls, fixed ob_start/ob_get_clean cross-function pattern (now uses ob_start with closure callback), full wp_unslash() + sanitize_* coverage on $_POST/$_GET, sanitize_text_field/sanitize_textarea_field applied after all json_decode() calls on external API responses. Version constant corrected from 1.2.3 to 1.2.5.

---

## [1.2.2] — 2026-04-19

### Added

- **FAQ Schema — Header enrichi** : deux nouveaux indicateurs sont affichés à droite du titre de la page, cohérents avec ceux du tableau de bord principal :
  - **Badge statut API Claude** : affiche « Vérification… » au chargement, puis « ✓ Claude connecté » en vert ou « ✗ API non configurée » en rouge selon le résultat du test. Le test utilise la même action AJAX `alesta_test_api` que le tableau de bord.
  - **Badge plugin SEO actif** : affiche « Yoast SEO actif », « RankMath actif » ou « ⚠ Pas de plugin SEO » selon la détection, exactement comme sur le tableau de bord.
- Le badge « Schema.org FAQPage » existant reste en place à droite.

---

## [1.2.1] — 2026-04-19

### Fixed

- **FAQ Schema — Sauvegarde et activation** : le clic sur « Sauvegarder et activer » déclenchait un `location.reload()` qui réinitialisait la vue (filtre, scroll, recherche). La ligne mise à jour semblait alors disparaître alors qu'elle se retrouvait simplement déplacée par le tri alphabétique. Remplacé par une **mise à jour en place de la ligne** : la modal se ferme, les cellules FAQ/Actif/Actions sont repeintes avec les nouvelles valeurs, et la ligne est surlignée en vert pendant 1,5 s. Pareil pour la suppression et le toggle.
- **FAQ Schema — Compteurs du haut** (Pages/Produits, Avec FAQ, Schemas actifs) : désormais recalculés en JS à chaque action, plus besoin de recharger.

### Changed

- **FAQ Schema — Filtre type transformé en cases à cocher** : le sélecteur `<select>` « Tous les types / Pages / Articles / Produits » est remplacé par trois cases à cocher indépendantes (toutes cochées par défaut). Tu peux maintenant afficher plusieurs types simultanément — par exemple Pages + Produits sans Articles.

### Added

- **FAQ Schema — Sélection multiple + actions en lot** :
  - Nouvelle colonne avec une case à cocher par ligne.
  - Case « tout sélectionner » dans l'en-tête (avec état intermédiaire quand partiellement sélectionné).
  - Barre d'actions contextuelle qui remplace le bouton « Generer manquants en lot » dès qu'au moins une case est cochée :
    - **« Generer pour la selection »** — lance Claude sur chaque ligne cochée (régénère celles qui ont déjà une FAQ).
    - **« Activer la selection »** — active uniquement les lignes qui ont déjà une FAQ parmi la sélection.
    - **« Desactiver la selection »** — idem, désactivation.
  - Avancement affiché dans le bouton pendant le traitement (ex. `3/12`).
  - Après chaque opération en lot, les lignes concernées sont repeintes sur place (pas de rechargement de page).

---

## [1.2.0] — 2026-04-19

### Added

**Nouveau module : Données structurées (Schema.org)** — SEO & Référencement → Données structurées

- Injection JSON-LD automatique dans le `<head>` des pages publiques, pour 7 types de schémas : `Article`, `Product`, `Organization`, `LocalBusiness`, `Person`, `Service`, `Event`.
- **Détection automatique du type** par page via analyse du post_type, du slug et du titre. Les pages techniques (mentions légales, CGV, politique de confidentialité, panier, checkout, compte, 404, merci) sont explicitement exclues pour éviter le schema spam pénalisé par Google.
- **Architecture 100% dynamique** : le JSON-LD n'est jamais figé. Les données volatiles (prix, stock, note moyenne, nombre d'avis, images à la une, titres, dates de publication/modification, auteurs, logo du site) sont lues en temps réel à chaque chargement de page depuis WordPress et WooCommerce. Résultat : quand un client laisse un avis sur un produit, les étoiles dans Google suivent automatiquement au prochain crawl, sans action manuelle.
- **Page admin** avec scan du site, tableau des pages avec type détecté, statut (Actif / Inactif / Absent / Non applicable), modal d'édition dont les champs s'adaptent au type (description pour Article, brand+description pour Product, adresse+horaires+sameAs pour Organization/LocalBusiness, etc.).
- **Génération des champs éditoriaux par Claude** : seuls les champs rédactionnels (descriptions courtes, pitches, bios) sont demandés à Claude. Tout le reste est live.
- **Bouton "Tester avec Google Rich Results"** par ligne, qui ouvre directement l'outil de validation Google sur l'URL concernée.
- Complémentarité avec le module FAQ Schema existant (qui reste dédié au type `FAQPage`).

### Changed

- **Tableau de bord** : la carte « Données structurées » passe de « ⏳ Bientôt » à « ✓ Disponible » avec un bouton Ouvrir.
- **Barre de progression globale** : 10 / 20 modules opérationnels (50 %).

### Technical

- Nouvelles classes : `Alesta_AI_Schema_Module` (logique) et `Alesta_AI_Admin_Schema` (page admin).
- Nouveaux assets : `assets/schema.js`, `assets/schema.css`.
- Nouvelles actions AJAX : `alesta_schema_bulk`, `alesta_schema_generate`, `alesta_schema_save`, `alesta_schema_toggle` (toutes protégées par nonce + `manage_options`).
- Méta persistée par post : `_alesta_schema_config` (structure `['type' => string, 'active' => bool, 'extras' => array]`).

---

## [1.1.0.16] — 2026-04-19

### Added
- **Barre de progression globale** en tête du tableau de bord : affiche le nombre de modules opérationnels sur le total prévu (`9 / 20 modules opérationnels · 45%`) avec une jauge bleue dégradée, pour visualiser en un coup d'œil l'avancement du plugin.
- **Badges d'état « ✓ Disponible » (vert) et « ⏳ Bientot » (gris)** en coin supérieur droit de chaque carte module — lisibles sans avoir à lire la description ni repérer le bouton.
- **Indicateurs KPI dynamiques** dans les descriptions de trois cartes actives, alimentés par le dernier audit SEO persisté :
  - Audit SEO → pastille score `XX/100` (verte ≥80, orange ≥50, rouge sinon)
  - Traitement images → pastille orange `N alt manquants` si l'audit en détecte
  - Erreurs 4xx/5xx → pastille rouge `N lien(s) cassé(s)` si l'audit en détecte
- **Compteurs de diagnostic supplémentaires** dans le bandeau de chiffres clés (affichés uniquement s'il y a un problème à signaler) : « Alt manquants » et « Liens cassés », tirés du dernier audit.

### Changed
- **Tableau de bord principal** : tous les boutons des modules opérationnels pointent désormais vers leur page (Title & Meta, FAQ Schema, Mots-clés, Amélioration texte, Traitement images, Nommage fichiers, Gzip/Cache/HTTPS, Erreurs 4xx/5xx, Dashboard SEO global, Configuration). Les modules encore en développement affichent clairement « ⏳ Bientot ».
- **Bandeau de chiffres clés** : le compteur « Produits WooCommerce » est renommé en « Produits » et l'indicateur « Score SEO global » affiche la date du dernier audit en sous-titre.
- **Onglet Images de l'Audit SEO** : suppression du tableau « Pages les plus concernées » qui faisait doublon avec les modules dédiés. L'onglet ne contient plus que la carte d'action jaune (total alt manquants + redirection vers Traitement images / Nommage fichiers).

### Technical
- Nouvelles classes CSS : `.amc-status`, `.amc-status-ok`, `.amc-status-soon`, `.amc-kpi-*`, `.alesta-progress-card`, `.alesta-progress-bar`, `.alesta-progress-fill`.
- `padding-top` des cartes modules augmenté à 28 px pour accueillir le badge d'état sans recouvrir le contenu.

---

## [1.1.0.15] — 2026-04-18

### Changed
- **Onglet Images de l'Audit SEO refondu en redirection contextuelle** : l'ancien tableau « Page / Images totales / Sans alt / Aperçu URLs » affichait systématiquement « image inconnue » et faisait doublon avec le module dédié. Il est remplacé par une carte d'action qui totalise les images sans alt, renvoie explicitement vers **Médias → Traitement images** (génération alt/title/caption via Claude) et **Médias → Nommage fichiers** (audit SEO des noms de fichiers). Un tableau de diagnostic allégé affiche les 5 pages les plus concernées pour contexte, avec un lien vers chaque page. Le compteur « Alt manquants » du bandeau de résumé reste en place comme KPI global.

---

## [1.1.0.14] — 2026-04-18

### Fixed
- **Incohérence du score global** : le score initial (calculé en PHP) suivait la formule `100 − erreurs×15 − avertissements×5` en comptant `status` au niveau des items, tandis que la mise à jour incrémentale après modification SEO (calculée en JS) utilisait la moyenne des scores SEO. Cela expliquait les sauts du type `20/100 → 80/100` après une simple correction de meta. Les deux logiques sont désormais alignées : **score global = moyenne des scores SEO**, et les compteurs Erreurs / Avertissements totalisent les issues individuelles (type `error`/`warning`) sur SEO + contenu + performance + liens, à l'initial comme en ré-audit ciblé.

### Added
- **Persistance du dernier audit** : chaque audit complet est sauvegardé dans l'option WordPress `alesta_ai_last_audit` (timestamp, périmètre, résultats). À l'ouverture de la page « Audit SEO », les résultats précédents sont rechargés automatiquement avec un libellé « Dernier audit : JJ/MM/AAAA à HH:MM » à côté du bouton de lancement. Le périmètre (types et vérifications) est également restauré. Plus besoin de relancer un scan pour retrouver le rapport.
- Les ré-audits ciblés (après application de suggestions Claude) mettent également à jour l'audit persisté — ainsi le rapport reste à jour même en rechargeant la page.

### Changed
- **Label du type de contenu** : « Produits WooCommerce » → « Produits ».

### Security
- Nouvelle action AJAX `alesta_get_last_audit` : nonce + `current_user_can('manage_options')`.

---

## [1.1.0.13] — 2026-04-18

### Added
- **3 variantes de meta description** au lieu d'une seule dans la modal Suggestions Claude. Le prompt demande explicitement à Claude de produire 3 angles différents (factuel, call-to-action, bénéfices), chacun entre 120 et 160 caractères. Compteur de caractères colorisé (vert si 120–160, orange si trop court, rouge si trop long).
- **Mot-clé principal sélectionnable** : le mot-clé suggéré par Claude et les mots-clés secondaires sont maintenant affichés comme des puces cliquables. Le mot-clé choisi est appliqué comme focus keyword sur Yoast (`_yoast_wpseo_focuskw`), RankMath (`rank_math_focus_keyword`), All in One SEO (`_aioseop_keywords`) et en natif Alesta (`_alesta_focus_keyword`).
- `apply_seo_fields()` : nouveau paramètre optionnel `$keyword` (rétrocompatible).

### Changed
- **Comportement des boutons « Appliquer »** dans la modal :
  - « Appliquer » sur un titre ou une meta individuelle → enregistre la valeur, met visuellement la ligne en état sélectionné (fond vert pâle, libellé « ✓ Sélectionné »), **ne ferme plus la modal** → l'utilisateur peut enchaîner ses choix (titre puis meta puis mot-clé).
  - Bouton global renommé en « ✓ Appliquer la sélection (titre + meta + mot-clé) » → applique la sélection courante + ferme la modal + rafraîchit la ligne du tableau SEO.
- La ligne SEO du tableau est également rafraîchie à chaque application individuelle (mise à jour silencieuse, sans fermeture de la modal).

### Compatibility
- Rétrocompat conservée avec l'ancien format `meta_description` (chaîne simple) au cas où Claude renverrait l'ancienne structure : elle est automatiquement convertie en une liste `meta_suggestions` à un élément.

---

## [1.1.0.12] — 2026-04-18

### Changed
- **Audit SEO → modal Suggestions Claude** : après clic sur « Appliquer » (titre seul, meta seule, ou « ✓ Appliquer le meilleur titre + meta »), la fenêtre se ferme automatiquement après un court feedback visuel, puis la ligne concernée du tableau SEO est ré-auditée et mise à jour (score, badges d'issues, score global, compteurs Erreurs/Avertissements) sans relancer le scan complet. Surlignage vert transitoire sur la ligne mise à jour.

### Security
- `alesta_audit_post` (AJAX ré-audit ciblé) : ajout du contrôle `current_user_can('manage_options')`, comme pour les autres actions du module.

---

## [1.1.0.11] — 2026-04-18

### Fixed
- **Audit SEO (boucle infinie)** : le callback `page_audit_redirect` redirigeait sur son propre slug (`?page=alesta-ai-audit`), provoquant une boucle de redirection en JavaScript. Remplacé par un rendu direct via `Alesta_AI_Admin_Audit::page_audit()`.

---

## [1.0.0] — 2026-04-15

### Added

**SEO & Referencing**
- SEO Audit: automatic scoring per page (title, meta, H1/H2, content length, keyword density)
- Title & Meta: bulk generation via Claude, inline editing, Yoast SEO integration, CSV export
- FAQ Schema: AI-generated FAQ structured data with JSON-LD injection in `<head>`
- Keywords: per-page suggestions, per WooCommerce category, cannibalization detection
- SEO Dashboard: global SEO table for all pages/posts/products with PDF client report

**Media & Images**
- Image management: AI-generated alt text, title, caption, description (3 variants per image)
- File naming: SEO score audit, Claude name suggestions, database update (SF3), physical rename with occurrence check and double confirmation (SF4)

**Performance & Optimization**
- Browser cache via .htaccess (`mod_expires`, `mod_headers`) with configurable durations
- GZIP compression via .htaccess (`mod_deflate`)
- HTTPS redirection via .htaccess (301 redirect) + WordPress URL correction
- Automatic .htaccess backup before every modification, restore button always available
- HTTP errors (4xx/5xx): batch scanner for all internal links across pages, posts and products
- Elementor compatibility for error detection and link correction
- One-click link correction with URL replacement in post content and Elementor data

**Settings**
- API key configuration with connection test
- Budget tracking (estimated token cost per operation)

---

## Notes

- Requires Anthropic API key (Claude)
- Yoast SEO recommended for full Title & Meta integration
- WordPress 6.0+, PHP 7.4+
