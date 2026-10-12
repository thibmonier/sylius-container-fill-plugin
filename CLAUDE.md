# CLAUDE.md

Sylius 2 plugin "Pallet & Truckload Builder": container types, logistics data per variant, a
load planner (places, payload, volume), fill rules enforced at checkout (shop, shop API,
workflow guard), a fill gauge in the cart, per-container shipping, CSV import of capacities.
Functional spec and decisions: `SPEC.md`. Public docs: `README.md`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console, PHPStan
and functional PHPUnit calls with `php -d memory_limit=2G` when running locally.

```bash
docker compose up -d mysql mailhog        # ports come from compose.override.yml (gitignored)
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # functional + integration, test DB + fixtures
vendor/bin/phpstan analyse                # level max, src + tests
vendor/bin/ecs check
vendor/bin/console lint:container
vendor/bin/console tmonier:container-fill:import-capacities file.csv --dry-run
```

Local env overrides (gitignored): `compose.override.yml` (MySQL published on 3330, MailHog on
8049/1049), `tests/TestApplication/.env.local` (DATABASE_URL + MAILER_DSN) and
`.env.test.local`. Databases: `tmonier_sylius_container_fill_dev` / `_test`, both loaded with
the default fixture suite, which includes `tmonier_container_fill_container_types` (13
presets) and `tmonier_container_fill_demo` (taxon "Pallet goods": spring water case of 6 sold
by whole cases, cement bags on their own pallet, floor tiles; EUR pallet capacities, the semi
derives from them; rule "full pallets or a full truck" on that taxon in every channel; buyer
`pallet@example.com` / `sylius`, verified). The core `shop_user` fixture leaves
`verified_at` NULL: use the demo buyer, or
`UPDATE sylius_shop_user SET verified_at = NOW() WHERE verified_at IS NULL;`. Dev server:
`symfony server:start -d --port=8097 --no-tls` (admin `sylius` / `sylius`), e.g.
`/en_US/products/spring-water-case`, then the cart. Stop it with `symfony server:stop`.

## Before publishing

- `tmonier/sylius-quick-order-plugin` is a dev dependency (`^1.0`, Packagist) used by the test
  application to test the bridge; the matrix hook needs quick order 1.1+, not yet published.
  Keep it out of `require`.

## Architecture

- Entities (`src/Entity`, XML mapping in `config/doctrine`, migration in `src/Migrations`):
  `ContainerType` (+ translation, translatable factory), `ProductVariantLogistics` (1-1
  variant), `VariantContainerCapacity`, `ContainerRule` (+ join table of types),
  `OrderLoadPlanSnapshot` (1-1 order). Weights in grams, lengths in millimetres; core variant
  fields converted with `Logistics\Units` (configured units).
- `Logistics/VariantLogisticsProvider` loads logistics + capacities for a set of variants in
  two queries; `VariantLogistics` resolves units per container (own capacity or nested type x
  places), unit weight (case weight, else core weight), unit volume (raw core dimensions).
- `Planner/SlotWeightPlanner` (pure): `ContainerSpec` + `PlanLine`s -> `FillResult`
  (`ContainerFill`s, `BindingConstraint`). Unit tested with `tests/Unit/Planner/PlannerFixtures`.
  Layer mode (1.1): `Planner/LayerPlanner` when the type has `layerMode` (single place, stack
  height and/or `maxLayers`) and every line has units per layer + layer height
  (`LayerPlannerTest`); side view `templates/shop/_stack.html.twig`.
- `Rule/`: `RuleEvaluator` and `SuggestionFinder` (pure), `ScopeMatcher`, `CartFillChecker`
  (service `tmonier_container_fill.cart_fill_checker`: applicable rules, type selection,
  `CartFillReport`: `outcomes` = every rule on its whole scope (blocking), `plan` = each line
  planned once by its most specific rule (`ScopeMatcher::precedence()`; display, container
  counts for shipping); `toArray()` used by the API and the snapshot; per-request cache,
  invalidated by `EventListener/CacheInvalidator` on flush, `kernel.reset`).
- Checkout: `Checkout/CheckoutEntrySubscriber` (kernel.request priority 4, routes
  `sylius_shop_checkout_*`), `Validator/OrderContainerFillSatisfied` (groups
  `sylius_checkout_complete` on `Order` and on the API `CompleteOrder` command, see
  `config/validation/Order.xml`), `Validator/CaseMultiple` (group `sylius` on `OrderItem`,
  the shop `AddToCartCommand` and the API `AddItemToCart` / `ChangeItemQuantityInCart`;
  opt-in per variant; quantity field min/step by `Form/Type/Extension/CartItemTypeExtension`), `Checkout/CheckoutCompleteGuardListener` (workflow guard),
  `Checkout/FillOverrideChecker` (admin + `override_role`), `Checkout/LoadPlanSnapshotListener`
  (`workflow.sylius_order_checkout.completed.complete`). Only orders in state `cart` are
  checked (grandfathering).
- Shop: `Twig/Component/ContainerGaugeComponent` (Live component `tmonier_container_fill:gauge`,
  LiveActions `complete` / `roundToLayer` via `Cart/CartQuantityAdjuster`, emit
  `sylius:shop:cart_changed` outside the cart page; inside it they redirect to the cart),
  templates `templates/shop` + `templates/shared/plan.html.twig`, hooks `config/twig_hooks`.
- API: `Api/Resource/OrderLoadPlan` + `Api/OrderLoadPlanProvider` (`config/services_api.php`).
- Shipping: `Shipping/PerContainerRateCalculator` (`tmonier_per_container`),
  `Shipping/ContainerTypeRuleChecker` (`tmonier_container_type`); tags written in
  `config/services.php` (no autoconfiguration).
- Admin: resource routes `config/routes/admin.yaml` (hook prefixes
  `tmonier_container_fill_admin.container_type` / `.container_rule`), grids `config/grids`,
  forms `src/Form`, variant Logistics tab = unmapped field added by
  `Form/Type/Extension/ProductVariantTypeExtension`, written by
  `Form/Logistics/VariantLogisticsManager` on `sylius.product_variant.pre_create/pre_update`
  (and `sylius.product.*` for simple products, tab in `templates/admin/product`, fields in
  `templates/admin/logistics/_fields.html.twig`).
- Quick order bridge: `DependencyInjection/Compiler/QuickOrderBridgePass` decorates
  `tmonier_quick_order.quantity_constraint_provider` only when quick order is installed.
- CSV: `Csv/CapacityCsvImporter` (all or nothing) / `CapacityCsvExporter`, commands in `src/Command`.
- Fixtures: `Fixture/ContainerTypePresets` (indicative values, sources in SPEC.md),
  `ContainerTypePresetFixture`, `ContainerFillDemoFixture` (both idempotent).

## Rules

- No core entity change: plugin tables only. Never add a fill constraint to the `sylius`
  validation group (partial carts must save).
- Tests never depend on random fixtures: `tests/TestCatalog.php` creates container types,
  products, rules, customers, carts and checkout-ready carts (own zone, shipping and payment
  methods) inside the rolled-back transaction (`ContainerFillTestTrait`), and disables the
  other rules (demo rule included). Carts of a logged-in customer use
  `setCustomerWithAuthorization()`. The test application resets the new-cart context on each
  request (`tests/TestApplication/src/Cart`, `when@test`). No `markTestSkipped`
  (`NoSkippedTestsTest`).
- DBAL 3 / Sylius 2.2: `tests/TestApplication/config/dbal_savepoints.php` (nested transactions).
- Labels in `messages`, validation messages in `validators`; every key in en, fr, de, es, it,
  nl, pl (`TranslationsTest` also checks the keys used in src/templates/config). Clear the
  cache (dev and test) after adding a translation file.
- Gauge accessibility: `role="meter"` with `aria-valuetext`, text next to every colour, no
  animation under `prefers-reduced-motion`; keep it when changing templates.
- `ResourceInterface::getId()` is `mixed`: use `Logistics\EntityId` instead of casts.
