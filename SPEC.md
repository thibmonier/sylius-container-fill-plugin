# SPEC: Pallet & Truckload Builder (tmonier/sylius-container-fill-plugin) v1

Functional specification and design decisions. Public documentation: `README.md`.

## 1. Research summary

- **Gap on Sylius 2.** No open source or Sylius Plus plugin handles container filling, case
  packs or quantity multiples (Packagist, addons.sylius.com, Sylius Plus B2B Suite
  v4.0.0-ALPHA.6). The closest is `nedac/sylius-minimum-order-value-plugin` (amount, Sylius 1).
  Sylius core has no minimum, step or multiple; `ProductVariant` has `width`, `height`, `depth`
  and `weight` **without units**, often empty.
- **Other platforms.** Checkout blocking on total weight or volume exists (WPFactory "Order
  Min/Max Amount Limits" for WooCommerce), item-count containers too (WooCommerce Mix and
  Match), per-line multiples are common (Magento Qty Increments, Spryker Packaging Units,
  Shopware purchase steps, OroCommerce units). Nobody combines B2B container types (pallet,
  truck, sea container, carton), a binding constraint over several dimensions (places, payload,
  volume), the split into N containers and an accessible gauge with consistent blocking in the
  shop, the API and the admin. Netstock / StockIQ container builders validate the "binding
  constraint" model (purchasing side).
- **Demand** is real but sporadic and small-budget (Shopify community threads 2024-2025:
  mixed pallet unlocked at 95 %, full shipping container; a PrestaShop brief under 500 EUR).
  This is a niche differentiator, not a foundation plugin.
- Sources and the full comparison: decision memo
  `docs/2026-10-11-memo-remplissage-contenant.md` of the plugin workspace (not shipped).

## 2. Approach

| Approach | Retained |
|---|---|
| (a) Capacity units / places ("40 cases fill one EUR pallet", a semi has 33 places) | **Yes, primary**: exact for the commercial rule, explainable ("add 7 cases"), O(n) |
| Payload cap | **Yes, mandatory in v1**: 33 pallets for 24 t is ~727 kg per pallet, drinks or building materials reach 800-1000 kg: a places-only truck gauge would show 100 % for an illegal load |
| (b) Volume x efficiency | Secondary dimension, only when the container and every line have dimensions; never the only blocking criterion |
| (c) 3D bin packing (`dvdoug/boxpacker`) | No (v2, carton mode only, optional bridge) |
| (d) Layers | Partial: units per layer stored per capacity, "round up to the layer" action; full layer mode (stack height, side view) in v1.1 |

## 3. Model

All in plugin tables (`tmonier_container_*`), no core entity change.

- `ContainerType` (translatable): `code`, `kind` (`pallet` | `truck` | `sea_container` |
  `carton`), inner length / width / height and maximum load height (mm), **maximum payload (g,
  mandatory)**, `slots` (places, 1 for a pallet), `nestedType` (single-place type filling the
  places, e.g. EUR pallet in a semi), `volumeEfficiency` (%), `indicative`, `position`,
  `enabled`. Presets are shipped as a fixture, editable, flagged indicative (section 8).
- `ProductVariantLogistics` (1-1 with the variant): `unitsPerCase`, `caseWeight` (g),
  `stackable` (stored for v1.1, unused in v1), `ownPallet`, `caseMultipleRequired`. **This
  plugin owns the case data and the per-line "multiple of N" rule** (memo section 4); other
  plugins read it (quick order, section 7).
- `VariantContainerCapacity` (variant x container type): units filling one container,
  optional units per layer.
- `ContainerRule`: channel, scope (`all` | `taxon` with descendants | `product`), optional
  customer group, allowed container types (ordered by position), `mode` + `threshold`.
- `OrderLoadPlanSnapshot` (1-1 with the order, plugin table): plan, satisfied flag, override
  author, written when the checkout completes.

**Units.** Sylius stores bare numbers: the units of the core variant fields are configured
(`units.length`: mm/cm/m/in, `units.weight`: g/kg/lb; defaults cm and kg). The plugin stores
millimetres and grams.

**Fallbacks.** Weight: case weight / units per case, else the core variant weight. Volume: core
width x height x depth read from the raw fields (`getShippingVolume()` returns 0, not null,
when a dimension is missing, verified on 2.3.0). Units per container: the capacity for the
type, else the capacity for its nested type x places. A line in a rule scope without units per
container or without weight makes the plan **incomplete**: the rule is not met ("incomplete
logistics data" names the products). Missing data never passes silently.

## 4. Planner

`LoadPlannerInterface::plan(ContainerSpec, list<PlanLine>): FillResult`; default
`SlotWeightPlanner` (pure, unit tested on reference fixtures):

- Fill of a container = max(places ratio, payload ratio, volume ratio when available); the
  **binding constraint** (`slots` | `weight` | `volume`) is exposed.
- Split into N containers by first-fit decreasing on whole cases (a partial last case is one
  smaller item); `ownPallet` lines never share a single-place container and take whole places
  in multi-place ones.
- **One container type per order** (per rule) in v1.
- `FillResult`: containers (ratios, binding, units per line, full flag), count, incomplete
  lines, volume availability.

`Rule\SuggestionFinder` proposes the smallest addition completing a container or meeting the
rule ("Add 30 x Cement bag to complete pallet 2"); the wording depends on the binding
constraint (exact units only when places bind).

## 5. Rules

Per channel, the enabled rules whose scope matches at least one cart line apply to the lines in
their scope. A rule of the customer's group **replaces** the rules without group of the same
scope (same taxon / product / all). For each rule, the allowed types are tried in order: the
first type meeting the rule is retained, else the first type with complete data (its
violations and suggestion are shown).

| Mode | Met when |
|---|---|
| `full_only` | every container is full |
| `min_fill_percent` | every container is filled to at least `threshold` % (1-100) |
| `multiple_of` | every line is a multiple of `threshold`, or of its case when empty |
| `max_containers` | the plan needs at most `threshold` containers |

Any mode also fails on incomplete data and on overloaded containers.

`Rule\CartFillChecker` computes the report on read, cached per request (key: lines and
quantities, channel, customer group, data version bumped by `EventListener\CacheInvalidator`
on any flush of a container type, rule, logistics, capacity or variant). No order processor: nothing is written to the cart.

## 6. Checkout blocking (memo 3.5 and corrections 11-13)

| Where | How |
|---|---|
| Checkout entry (web) | `Checkout\CheckoutEntrySubscriber`, `kernel.request` priority **4** (after RouterListener 32 and the firewall 8, before CheckoutResolver 0) on the `sylius_shop_checkout_*` routes: redirect to the cart summary with a flash. Works with `sylius_shop.checkout_resolver.enabled: false`. No guard on the `address` transition (cart -> address redirect loop). Tested with a signed-in customer's cart. |
| Checkout complete (web) | class constraint `OrderContainerFillSatisfied` on `Order`, group `sylius_checkout_complete` |
| Checkout complete (API) | the same constraint mapped on `Sylius\Bundle\ApiBundle\Command\Checkout\CompleteOrder` (the API validates the command, not the order): 422 |
| Defence in depth | `Checkout\CheckoutCompleteGuardListener` on `workflow.sylius_order_checkout.guard.complete` (Symfony Workflow adapter, the Sylius 2 default; `workflow_guard: false` disables it) |
| Cart | **never** a fill constraint in group `sylius` (a partial cart must save); only the opt-in per-line `CaseMultiple` constraint is in `sylius` |
| Admin | override by permission (`override_role`, nobody by default), logged on the `tmonier_container_fill` channel and recorded on the snapshot |
| Grandfathering | constraints and guard only check orders in state `cart`: placed orders stay valid when rules change |

## 7. Shop, API and integrations

- **Cart gauge**: Live component `tmonier_container_fill:gauge` in
  `sylius_shop.cart.index.content.form.sections.general#right`, priority 50 (between summary
  100 and checkout 0); `#[LiveListener('sylius:shop:cart_changed')]`; server-side SVG (truck
  place grid, pallet stack), `role="meter"` with `aria-valuetext` ("pallet 2 of 3: 84 %,
  limited by the weight"), `aria-live` status, text next to every colour, no animation under
  `prefers-reduced-motion`. LiveActions "round up to the layer" and "complete" change the cart
  and emit `cart_changed` themselves (product page, quick order matrix); inside the cart page
  they reload the page instead (the cart form component would otherwise keep stale quantities). The `checkout` hook entry is overridden:
  button disabled with the reason.
- **Product page**: indicative preview under the add to cart quantity (units per case, share of
  a container per case, current cart fill). **Decision**: rendered server side in the add to
  cart hook (re-rendered by the core add to cart live component), not a Stimulus controller:
  no JavaScript to ship and the cart stays the reference (memo 3.6: not additive in mixed carts).
- **Shop API (decision)**: separate read-only resource
  `GET /api/v2/shop/orders/{tokenValue}/load-plan` (`ContainerFillLoadPlan`): computed now for
  a cart, the snapshot for a placed order. Chosen over an embedded `Order` property: no change
  to the core normalization, no conflict with other plugins decorating the order. Access
  follows the core cart rule: a registered customer's order only for that customer (404
  otherwise), guest orders by token.
- **Shipping**: calculator `tmonier_per_container` (amount per channel x number of containers,
  at least 1) and rule `tmonier_container_type` (method available only when every applicable
  rule plans one of the configured types; computed on demand, shipping methods are listed
  outside the processing chain). One Sylius shipment. Amounts labelled indicative.
- **Quick order** (`tmonier/sylius-quick-order-plugin`, optional, `suggest`): when its
  `QuantityConstraintProviderInterface` and service `tmonier_quick_order.quantity_constraint_provider`
  exist, `QuickOrderBridgePass` decorates the provider: variants with
  `caseMultipleRequired` get minimum = multiple = units per case. The gauge is registered under
  the quick order matrix hook `tmonier_quick_order.shop.product.matrix` (1.1+, harmless before).
- **Admin**: CRUD container types and rules (Configuration menu), Logistics tab on the variant
  form (unmapped field, written in the plugin tables on `sylius.product_variant.pre_create` /
  `pre_update`), load plan card on the order page.
- **CSV**: `tmonier:container-fill:import-capacities` / `export-capacities`
  (`variant_code;container_type;capacity;units_per_layer;units_per_case;case_weight_kg`), dry
  run, **all or nothing** (decision: a rejected line leaves the data unchanged).

## 8. Presets (indicative, editable)

| Code | Values | Sources |
|---|---|---|
| `EUR_PALLET` | 1200 x 800, load height 1656 mm (2.0 m total, 144 mm deck), 1000 kg | EPAL (1500 kg spread / 1000 kg otherwise); GEODIS ~2.0 m / 1000 kg, DHL / Eurosender ~2.2 m: pekaes.geodis.pl/en/?p=1262, eurosender.com/en/business/help/maximum-pallet-height |
| `EUR_HALF_PALLET`, `UK_PALLET`, `US_PALLET` | 800 x 600 / 500 kg; 1200 x 1000 / 1000 kg; 48 x 40 in / 1000 kg | memo 3.1 and correction 17 |
| `SEMI_13_6` | 13620 x 2480 x 2700 mm, 33 EUR places, 24 t | DFDS equipment, Eurowag (32 places in Benelux: unverified) |
| `MEGA_13_6` | 2950 mm inner height, 33 places, 24 t; double deck not modelled | memo correction 5 |
| `US_53FT` | 26 US pallets, ~20 t (unverified) | memo 3.1 |
| `CONTAINER_20` / `_40` / `_40HC` | 28.2 t / 26.7 t / 26.5 t payload (a 40 ft carries less than a 20 ft), 11 / 23 / 23 EUR places | container data sheets, memo correction 8 |
| `CARTON_S/M/L` | 300x200x150 / 400x300x300 / 600x400x400 mm, 10 / 20 / 30 kg | arbitrary examples |

Road limits (EU Directive 96/53/EC: 40 t, 44 t intermodal) may apply before the payload of a
container; carriers' rules apply.

## 9. Decisions taken without explicit specification (conservative)

1. Shop API exposure as a separate resource (section 7).
2. Product preview rendered server side instead of Stimulus (section 7).
3. Override role granted to nobody by default; no override from the shop.
4. Incomplete logistics data in a rule scope blocks the checkout (never assumed fine).
5. A line outside every rule scope is not planned (no gauge without applicable rule).
6. Per-container shipping without applicable rule charges one container.
7. CSV import all or nothing; the variant form saves logistics only when the whole form is valid.
8. `stackable` stored but not used before the v1.1 layer mode.
9. Group rules replace the general rule of the same scope instead of adding to it.
10. Translations in en, fr, de, es, it, nl, pl (instead of the memo's en + fr).

## 10. Out of scope (v1)

3D bin packing, rotations, heavy-at-the-bottom stacking; double deck in mega trailers; several
container types in one order; one shipment per pallet; ADR, axle loads, securing (carrier's
responsibility); 3D viewer; automatic filler product suggestions; rules per organisation;
loading metres (LDM). Next: v1.1 layer mode, v2 optional BoxPacker bridge (cartons), RFQ
fallback "quote this truck" (with tmonier/sylius-quote-request-plugin).
