# Audit qualité — sylius-container-fill-plugin

> Généré le 2026-10-14 · Branche `chore/quality-tooling` · Outillage claude-craft (ECS, PHPStan, Rector, PHPUnit) exécuté via Docker `ghcr.io/sylius/sylius-php:8.4-alpine`. La **revue qualitative** (sections 2–3) est réalisée par l'orchestrateur en lecture directe des fichiers sources — pas de sous-agents délégués.

## 1. Audit factuel (outils)

| Contrôle | Commande | Résultat |
|---|---|---|
| Style (ECS) | `vendor/bin/ecs check` | ✅ 0 erreur |
| Analyse statique (PHPStan **level max**) | `vendor/bin/phpstan analyse` | ✅ 0 erreur |
| Tests unitaires | `vendor/bin/phpunit --testsuite=unit` | ✅ 73 tests, 2 404 assertions |
| Validation composer | `composer validate --strict` | ✅ valide |
| Modernisation (Rector, dry-run) | `vendor/bin/rector process --dry-run` | ⚠️ 49 fichiers avec suggestions |
| Vulnérabilités dépendances | `composer audit` | ⚠️ 2 paquets abandonnés (transitifs Sylius) |
| Tests `non-unit` (functional/integration) | — | ⏭️ non exécutés localement (DB + app Sylius requise) — couverts par le CI |

**Rector** vient d'être ajouté (`rector/rector ^2.0`, `rector.php`, cibles `make rector` / `make rector-fix`). Les 49 fichiers signalés relèvent principalement de `FlipTypeControlToUseExclusiveTypeRector` — suggestions de modernisation non bloquantes, non appliquées. Lancer `make rector-fix` puis relancer ECS/PHPStan/tests.

**Paquets abandonnés** : `behat/transliterator` et `php-http/message-factory` sont des dépendances transitives de `sylius/sylius` et non gérables directement. Aucune advisory de sécurité connue.

## 2. Audit qualitatif (revue par l'orchestrateur)

| Dimension | Score | Synthèse |
|---|---|---|
| PHP / Clean Archi / SOLID | **8/10** | Typage strict partout, `final` sur les services, interfaces sur tous les composants critiques, planificateurs purs. Défaut DIP isolé. |
| Intégration Symfony / Sylius | **8/10** | Triple enforcement (subscriber kernel.request + validateur + workflow guard), `ResetInterface`, priorité documentée, bypass admin sécurisé. |
| Sécurité (OWASP 2025) | **8/10** | DQL 100 % paramétré, RBAC double sur le bypass admin, validation input CSV exhaustive, `quantityLimit` sur les actions Live. Risques résiduels ciblés. |

### Points forts

- `declare(strict_types=1)` + classes `final` sur tous les services (seules les entités Sylius Resource sont non-final par convention d'extension).
- Interfaces pour chaque composant remplaçable : `CartFillCheckerInterface`, `VariantLogisticsProviderInterface`, `LoadPlannerInterface`, repositories.
- `CartFillChecker` implémente `ResetInterface` (reset du cache par requête) et `invalidate()` incrémente la version de cache — cohérence parfaite avec `CacheInvalidator`.
- Triple enforcement checkout : `CheckoutEntrySubscriber` (kernel.request priority 4) + `OrderContainerFillSatisfiedValidator` (groupe `sylius_checkout_complete`) + `CheckoutCompleteGuardListener` (workflow guard) — aucun point d'entrée non couvert.
- `FillOverrideChecker` : double vérification `AdminUserInterface` + `isGranted($role)` ; bypass uniquement pour le rôle configuré + log `notice` audité.
- `CapacityCsvImporter` : validation exhaustive avant tout write (`check()` sur chaque ligne), flush unique en cas de succès complet, déduplication par `($variant_code, $container_type)`.
- `SlotWeightPlanner` + `LayerPlanner` : algorithmes purs, sans dépendance de service — testabilité maximale (73 tests unitaires, 2 404 assertions).
- `CartQuantityAdjuster` : validation de range `[1, quantityLimit]`, vérification que la ligne appartient au panier courant (server-side), dispatch des événements Sylius attendus.

## 3. Recommandations priorisées

### 🟠 Majeur

1. **Import CSV sans limite de taille** (`src/Csv/CapacityCsvImporter.php:45-46`, OWASP A05:2025). Le contenu CSV est accepté en `string` sans contrôle de longueur : un fichier de plusieurs dizaines de Mo peut épuiser la RAM du process PHP (DoS). → Ajouter une vérification `strlen($contents) > MAX_SIZE` au début d'`import()` et retourner une erreur explicite. La commande CLI doit aussi contrôler la taille avant lecture.

2. **Absence de vérification de l'adapter state-machine** (`src/Checkout/CheckoutCompleteGuardListener.php:22-34`, OWASP A04:2025). Contrairement à `sylius-order-hold-plugin`, ce plugin n'a pas de `StateMachineAdapterCheckPass` pour refuser winzou à la compilation. Si winzou est installé, le `GuardEvent` n'est jamais émis et ce troisième layer de sécurité est silencieusement inactif. Le subscriber et le validateur restent actifs, mais la défense en profondeur est fragilisée. → Ajouter un CompilerPass (modèle order-hold) ou un warning explicite au démarrage quand winzou est détecté.

3. **Couplage concret `SlotWeightPlanner → LayerPlanner`** (`src/Planner/SlotWeightPlanner.php:24-27`, DIP). `new LayerPlanner()` est instancié en dur dans le constructeur si `null` est passé. Cela empêche la substitution et le mock en tests. → Rendre `LayerPlanner` injectable uniquement via le constructeur (supprimer la valeur `null` par défaut et la créer via le conteneur).

### 🟡 Mineur

- **Rector** : 49 fichiers à moderniser (`make rector-fix`), principalement `FlipTypeControlToUseExclusiveTypeRector` (meilleure précision de types). Non bloquant.
- **`ContainerGaugeComponent::complete()`** (`src/Twig/Component/ContainerGaugeComponent.php:104`) : `outcomes[$rule]` accédé sans `array_key_exists()` préalable — si `$rule` dépasse les bornes, PHP émet une notice. Le `?? null` évite le fatal error, mais un log ou un guard explicite serait plus propre.
- **`ContainerRule` non-final** (`src/Entity/ContainerRule.php`): voulu par design Sylius Resource (extension via `protected`), mais à documenter explicitement comme comportement attendu pour éviter les surprises des contributeurs.
- **Paquets abandonnés transitifs** (`behat/transliterator`, `php-http/message-factory`) : à surveiller lors des montées de version Sylius. Planifier le passage à `psr/http-factory`.

### ℹ️ Pour information (choix de conception assumés)

- Le mode `layerMode` est optionnel par type : si des lignes sans données de couches coexistent, le planner revient gracieusement en mode capacités — comportement bien documenté dans les docblocks.
- La propriété `$report` publique de `CartFillReport` est intentionnelle pour les templates Twig (accès direct sans accesseurs) — cohérent avec la philosophie DTO Sylius.
- `CartFillChecker::$rules` est vidé à la fois par `reset()` et `invalidate()` — l'invalidation de règles après un flush d'admin (ajout/modification de règle) est ainsi correctement propagée.

## 4. Verdict

Plugin **technique et rigoureux** : outillage au vert (ECS, PHPStan level max, 73 tests unitaires avec 2 404 assertions), typage strict, triple enforcement checkout, aucune injection SQL. La couverture de la logique métier (planificateurs purs, algorithmes bin-packing) est particulièrement solide. Les axes d'amélioration prioritaires sont la **protection mémoire sur l'import CSV** et l'**ajout du CompilerPass pour l'adapter state-machine** — deux points qui renforcent la robustesse en production sans impacter l'architecture existante. Le défaut DIP `SlotWeightPlanner → LayerPlanner` est mineur mais à corriger avant publication.
