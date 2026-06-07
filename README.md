# Alesta AI v2.0 — Refactor "Pattern Elementor"

> 📦 Squelette de migration du plugin Alesta AI monolithique (1.3.22) vers
> une architecture **Free + Pro Addon** inspirée d'Elementor.

## Statut : 🚧 Squelette infrastructure uniquement (S2 de la roadmap)

Cette migration scinde l'actuel `Alesta AI Pro 1.3.22` (350 lignes, 259 fichiers PHP)
en **2 plugins WordPress distincts qui cohabitent** :

```
wp-content/plugins/
├── alesta-ai/         ← FREE, distribué sur wordpress.org
└── alesta-ai-pro/     ← PRO, distribué via GitHub Releases / Galiance Cockpit
                          Header: "Requires Plugins: alesta-ai"
```

---

## Ce qui est déjà livré

### `alesta-ai/` (Free 2.0)
- ✅ `alesta-ai.php` — bootstrap + autoload PSR-4 manuel + hook `alesta_ai/loaded`
- ✅ `includes/core/class-extensions-api.php` — API publique pour le Pro :
  - `is_pro_active()`, `get_ai_providers()`, `get_pro_features()`
  - `render_module_actions($slug, $ctx)` — injection UI
  - `free_monthly_quota()` / `record_quota_usage()` — quota Talk-to-Me 100 msg/mois
- ✅ `includes/core/class-module-registry.php` — registre central
  - `register($slug, $class, $meta)` pour Free
  - `register_pro($slug, $class, $meta)` pour Pro
  - Pattern override Pro→Free (`KeywordsModule` → `KeywordsAIModule`)

### `alesta-ai-pro/` (Pro 2.0)
- ✅ `alesta-ai-pro.php` — header `Requires Plugins: alesta-ai`
  - Polyfill WP < 6.5 (admin_notice + deactivate si Free absent)
  - Bootstrap consommateur de `alesta_ai/loaded` (5 add_filter + register_pro de démonstration)

---

## Ce qui reste à faire (S3-S4 de la roadmap)

### S3 — Migration des modules
Pour chacun des **17 modules PRO** identifiés par l'audit :
1. Copier `Alesta AI Pro version/.../class-X-module.php`
2. Renamespacer en `\AlestaAIPro\Modules\...`
3. Refactor pour consommer les hooks Free au lieu d'instancier directement
4. Enregistrer dans `alesta-ai-pro.php` via `$registry->register_pro(...)`

Pour chacun des **27 modules FREE** :
1. Copier depuis l'ancien Free 1.2.2 ET/OU depuis le Pro (les versions Pro sont plus à jour)
2. Renamespacer en `\AlestaAI\Modules\...`
3. Retirer les références à `Alesta_AI_API` (ancien système)
4. Ajouter les hooks d'injection (`do_action('alesta_ai/admin/X/actions')`)
5. Enregistrer dans `includes/modules/index.php`

Pour chacun des **4 modules SPLIT** (redirects, scripts, perf-audit, filenames) :
1. Migrer le cœur statique en Free
2. Migrer la partie IA en Pro (qui consomme le hook d'injection du Free)

### S4 — Migration + déploiement
- Script `Migration_2_0_0` pour les sites existants
- Update `lib/alesta/activation.ts` côté Galiance Cockpit (install free + install pro)
- Test sur 2-3 sites Galiance, monitoring 7 jours
- Push wp.org pour le Free + GitHub Releases / Galiance Cockpit pour le Pro

---

## Pourquoi cette architecture ?

| Bénéfice | Modèle actuel (Pro standalone) | Modèle Elementor (Pro = addon) |
|---|---|---|
| Stats wp.org | 0 install gardé | Chaque Pro = +1 install Free |
| Reviews wp.org | Aucune | Clients Pro contribuent aux reviews |
| Upsell in-product | Impossible | Bannières contextuelles dans Free |
| Downgrade gracieux | Site cassé si license expire | Free continue à marcher |
| Codebase | Pro = clone Free + features | Pro = juste features, hérite du Free |
| Conformité GPL wp.org | Border line | Nickel |

Voir audit complet dans la conversation Galiance Cockpit (sub-agent général-purpose).

---

## Notes d'implémentation

### Convention namespace
- Free  : `\AlestaAI\{Core,Modules,...}`
- Pro   : `\AlestaAIPro\{Providers,Modules,...}`

### Convention hooks (Elementor-style)
- `alesta_ai/{domaine}/{action}` — slash pour la hiérarchie
- Exemples :
  - `alesta_ai/loaded` — signal de fin de boot Free
  - `alesta_ai/ai/providers` — filtre des providers IA
  - `alesta_ai/admin/{module}/actions` — point d'injection UI
  - `alesta_ai/module/registered` — événement d'enregistrement
  - `alesta_ai/module/overridden` — événement d'override Pro→Free

### Quota Talk-to-Me Free
- Défaut : **100 messages / mois / site**
- Coût estimé Claude Sonnet : ~0,15€/site/mois
- Le Pro override en `PHP_INT_MAX` (illimité)
- Stockage : `wp_options['alesta_ai_quota_used_YYYY-MM']`

### Sécurité
- Aucun système de licence externe dans le Free (BYOK Anthropic, conforme wp.org)
- Pas de clé API hardcodée
- `_doing_it_wrong()` pour les violations du contrat d'API
- Capability custom `manage_alesta_ai` (mapping rôles via module `roles`)
