# Alesta AI — Plugin WordPress

Plugin WordPress connecté à Claude (Anthropic) pour automatiser les métadonnées d'images et le SEO.

## Installation

1. Copier le dossier `alesta-ai/` dans `/wp-content/plugins/`
2. Activer le plugin dans WordPress → Extensions
3. Aller dans **Alesta AI → Réglages**
4. Saisir votre clé API Anthropic (disponible sur console.anthropic.com)
5. Tester la connexion

## Fonctionnalités v1.0

### Module Images
- Renseigne automatiquement pour chaque image :
  - **Titre** — optimisé SEO (5-8 mots)
  - **Légende** — contextuelle pour le lecteur
  - **Texte alternatif (alt)** — accessibilité + SEO
  - **Description longue** — contenu riche en mots-clés naturels
- Traitement par lot depuis l'interface admin
- Barre de progression en temps réel
- Option "écraser les champs existants"
- Journal détaillé image par image

## Structure des fichiers

```
alesta-ai/
├── alesta-ai.php                   ← Point d'entrée
├── includes/
│   ├── class-api.php               ← Client Anthropic API
│   ├── class-image-processor.php  ← Logique traitement images
│   └── class-admin.php            ← Pages admin + AJAX
└── assets/
    ├── admin.js                    ← Interface interactive
    └── admin.css                   ← Styles admin
```

## Sécurité

- Clé API stockée dans `wp_options`, jamais exposée côté front
- Tous les appels AJAX protégés par nonce WordPress
- Vérification `current_user_can('manage_options')` sur chaque action
- Données sanitisées avant insertion en base

## Modèles disponibles

| Modèle | Usage |
|--------|-------|
| claude-sonnet-4-5 | Recommandé — bon équilibre qualité/coût |
| claude-opus-4-5 | Plus puissant — pour contenu exigeant |
| claude-haiku-4-5 | Plus rapide et moins cher |

## Roadmap

- [ ] Module SEO (title, meta description, analyse contenu)
- [ ] Génération de contenu article
- [ ] Traduction automatique
- [ ] Modération de commentaires
- [ ] Génération de résumés
