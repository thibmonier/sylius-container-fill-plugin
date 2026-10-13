# Pallet & Truckload Builder for Sylius

[![Build](https://github.com/thibmonier/sylius-container-fill-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-container-fill-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**B2B container filling for Sylius 2: "full pallets only", "fill the truck to 95 %", "sold by
case of 6", with a fill gauge in the cart and the rules enforced at checkout.**

Wholesalers of drinks, building materials, packaging or food often sell by the pallet or the
truckload: a mixed pallet must be complete, a truck must be worth the trip, a product ships by
whole cases. Sylius 2 has no minimum, step or multiple of quantity, and no notion of container.
Checkout blocking on a total weight exists on other platforms, but nobody combines B2B
container types, a binding constraint over places and payload, the split into several
containers and an accessible gauge.

This plugin adds container types (pallets, trucks, sea containers, cartons), logistics data per
variant, a load planner, fill rules per channel, scope and customer group, a gauge in the cart
that tells the buyer what to add, and the same blocking in the shop, the shop API and the
admin.

## Features

| Need | What the plugin does |
|---|---|
| Describe the containers | **Container types**: pallet, truck, sea container, carton; inner dimensions, load height, **payload (mandatory)**, places (33 EUR pallets in a semi), nested type. Indicative presets: EUR / half / UK / US pallets, standard and mega semi-trailers, US 53 ft, 20 ft / 40 ft / 40 ft HC containers, cartons. |
| Describe the products | **Logistics tab** on the variant form and on the product form of simple products: units per case, case weight, own pallet, "sold by whole cases", layer height and stackable; units filling each container type and units per layer. Falls back on the Sylius weight and dimensions (units set in the configuration). |
| Know how full the order is | **Load planner**: fill = max(places, payload), the **binding constraint** is shown ("limited by the weight"), the volume is an indicator when every dimension is known (never blocking); lines split into N containers (first-fit decreasing), products on their own pallet kept apart. |
| Commercial rules | **Rules** per channel, scope (all, taxon with its children, product) and optional customer group: **full containers only**, **minimum fill %**, **multiple of N** (or of the case), **maximum containers**, on a list of allowed container types. |
| Build pallets by layers | **Layer mode** (1.1, opt-in per container type): a pallet is a stack of layers, one product per layer, limited by its load height and/or a maximum number of layers and by its payload; non-stackable products end a stack; side view of each pallet in the gauge, "room for 3 more layers", product page preview in layers ("90 = 1.5 layers ≈ 18 % of a EUR pallet"). |
| Help the buyer | **Gauge in the cart** (server-side SVG truck places and pallet stack, accessible meter, text next to every colour, reduced motion), "add 30 cases to complete pallet 2" and "round up to the layer" buttons, checkout button disabled with the reason. Indicative **preview on product pages** (share of a container taken by the typed quantity, current fill of the cart). |
| Enforce the rules | Checkout entry redirected to the cart, **checkout completion refused in the shop and the shop API (422)**, workflow guard as defence in depth. Partial carts always save; placed orders stay valid when rules change. |
| Exceptions | Administrators with a dedicated role may complete an order that does not meet the rules: logged and recorded on the order. |
| After the order | **Load plan snapshot** on the admin order page and in the shop API. |
| Freight | **Per container** shipping calculator (amount x containers) and a **container type** shipping rule. Amounts labelled indicative. |
| Bulk data | `tmonier:container-fill:import-capacities` / `export-capacities` (CSV, dry run, all or nothing). |
| Headless | `GET /api/v2/shop/orders/{tokenValue}/load-plan`. |
| Quick order | With [tmonier/sylius-quick-order-plugin](https://github.com/thibmonier/sylius-quick-order-plugin): quick order quantities follow the case multiples; the gauge shows under the order matrix (quick order 1.1+). |

Also included: translations in English, French, German, Spanish, Italian, Dutch and Polish;
Twig Hooks everywhere; demo fixtures; MySQL, MariaDB and PostgreSQL support. No core entity is
changed: the plugin has its own tables.

## Requirements

| | Version |
|---|---|
| PHP | 8.2+ (Sylius 2.2), 8.3+ (Sylius 2.3) |
| Sylius | 2.2.9+, 2.3 |
| Symfony | 6.4, 7.4 (Sylius 2.2); 6.4, 7.4, 8.0 (Sylius 2.3, Symfony 8 needs PHP 8.4) |

Sylius 2.2.9 is the floor because the plugin takes part in the checkout: earlier 2.2 releases
are affected by GHSA-2rv4-pjmm-7fxf and GHSA-vv4h-q2x8-74g4.

## Installation

1. Require the package:

    ```bash
    composer require tmonier/sylius-container-fill-plugin
    ```

2. Register the bundle in `config/bundles.php` (Symfony Flex does it automatically once a recipe exists):

    ```php
    Tmonier\SyliusContainerFillPlugin\TmonierSyliusContainerFillPlugin::class => ['all' => true],
    ```

3. Import the configuration in `config/packages/tmonier_sylius_container_fill.yaml`:

    ```yaml
    imports:
        - { resource: "@TmonierSyliusContainerFillPlugin/config/config.yaml" }
    ```

4. Import the admin routes in `config/routes/tmonier_sylius_container_fill.yaml`:

    ```yaml
    tmonier_sylius_container_fill:
        resource: "@TmonierSyliusContainerFillPlugin/config/routes.yaml"
    ```

    The shop API operation is registered with the Sylius API (`/api/v2`) automatically.

5. Run the migrations (plugin tables only):

    ```bash
    bin/console doctrine:migrations:migrate
    ```

6. Load the container type presets (optional, idempotent), then review them in the admin:

    ```yaml
    # config/packages/tmonier_sylius_container_fill.yaml
    sylius_fixtures:
        suites:
            container_types:
                fixtures:
                    tmonier_container_fill_container_types: ~
    ```

    ```bash
    bin/console sylius:fixtures:load container_types
    ```

    `tmonier_container_fill_demo` adds demo products, a rule and the verified buyer
    `pallet@example.com` / `sylius`: for demo shops only.

## Configuration

```yaml
# config/packages/tmonier_sylius_container_fill.yaml
tmonier_sylius_container_fill:
    units:
        length: cm          # unit of the Sylius variant width, height, depth: mm, cm, m, in
        weight: kg          # unit of the Sylius variant weight: g, kg, lb
    override_role: ROLE_TMONIER_CONTAINER_FILL_OVERRIDE   # null disables overrides
    workflow_guard: true    # also guard the "complete" transition of sylius_order_checkout
    cart_gauge: true        # gauge in the cart, checkout button disabled with the reason
    product_preview: true   # indicative preview on product pages
```

Sylius stores bare numbers for the variant weight and dimensions: set the units your catalog
uses. The plugin stores its own data in millimetres and grams; the admin forms take
kilograms.

**Overrides.** Nobody has the override role by default. Grant it to the administrators who may
complete an order that does not meet the rules (admin order creation, admin API):

```yaml
# config/packages/security.yaml
security:
    role_hierarchy:
        ROLE_ADMINISTRATION_ACCESS: [ROLE_TMONIER_CONTAINER_FILL_OVERRIDE]
```

Every override is logged on the `tmonier_container_fill` Monolog channel and shown on the load
plan of the order.

### Container types

| Field | Notes |
|---|---|
| Kind | `pallet`, `truck`, `sea_container`, `carton` |
| Maximum payload | **required**, in kg: the payload cap applies to every container |
| Places | 1 for a pallet or a carton; 33 for a standard semi-trailer (EUR pallets) |
| Places hold | the single-place type filling the places: a truck of 33 EUR pallet places derives the capacity of a product from its EUR pallet capacity |
| Inner dimensions, load height, volume efficiency | optional; when the container and every product have dimensions, the volume is shown as an indicator ("may exceed the volume"): it never splits nor blocks, the capacities you enter decide |
| Indicative | shown as "indicative" to buyers; untick once checked with your carrier |

The presets are indicative figures from public sources (see `SPEC.md`): check them with your
carriers and pallet networks (load heights from 1.8 to 2.2 m, payloads from 1000 to 1500 kg per
EUR pallet).

### Layer mode (1.1)

Tick **Plan by layers** on a single-place container type (a pallet, a carton) that has a load
height (or inner height) and/or a **maximum number of layers**. Give each product a **layer
height** (Logistics tab) and its **units per layer** for that type (capacity row). Then:

- a pallet is a stack of layers, one product per layer, heavy layers at the bottom; a partial
  layer takes the height of a full one but counts for its share in the fill, and a pallet with a
  partial layer that can be completed is not "full" (full pallets are made of full layers);
- a pallet is limited by its load height, its maximum number of layers and its payload
  (whichever binds is shown: "limited by stack height");
- a non-stackable product bears nothing: at most one of its layers per pallet, on top;
- the gauge draws a side view of each pallet with its layers, says how many layers still fit,
  and "round up to the layer" completes partial layers;
- the product page speaks in layers: "90 = 1.5 layers ≈ 18 % of a EUR pallet".

Without layer data for every product of a plan, or on a type without layer mode, the plan uses
the capacities, as in 1.0. Upgrading from 1.0 needs the migration (nullable columns, layer mode
off): nothing changes until you opt in.

### Logistics data (Logistics tab of the variant form, or of the product form for simple products)

| Field | Notes |
|---|---|
| Units per case, case weight | the case weight wins over the Sylius weight |
| Sold by whole cases | the quantity field starts at one case and steps by the case; a quantity that is not a multiple of the case is refused when adding to the cart (shop and shop API), when changing it in the cart (shop and API), and the checkout stays blocked |
| Own pallet | never mixed with other products in a container |
| Layer height (mm), stackable | layer mode: height of one layer of the product; a non-stackable product bears nothing (at most one of its layers per pallet, on top) |
| Capacity per container type | units filling one container (capacity mode, trucks), units per layer (layer mode, "round up to the layer") |

A product in the scope of a rule without capacity or weight makes the plan **incomplete**: the
rule is not met and the gauge names the product. The plugin never assumes missing data is fine.

### Rules

| Field | Values |
|---|---|
| Channel | required |
| Customer group | empty = everybody; a group rule replaces the general rule of the same scope for that group |
| Scope | `all`, `taxon` (with its children), `product` |
| Container types | allowed types, tried in order: the first one meeting the rule is retained |
| Mode | `full_only`; `min_fill_percent` (threshold 1-100); `multiple_of` (threshold, or the case of each line when empty); `max_containers` (threshold) |

Overloaded containers and incomplete data fail every mode. An enabled rule whose container
types are all disabled blocks the checkout of its products (flagged in the rule grid): disable
the rule rather than its types. Rules apply to the lines in their
scope; carts without lines in the scope of a rule show no gauge. When several rules match a
line, **all of them must be met** (a global "at most 1 truck" rule also counts the products of
a taxon rule). The load plan shown in the gauge, on the order and in the API, and counted by
the per container shipping, plans each line once, with its most specific rule: product, then
taxon (deepest first), then all products; at equal scope the customer group rule, then the
oldest. Lines of different rules are planned in separate containers.

### Shipping

- **Per container of the load plan** calculator: amount per channel x number of containers of
  the applicable rules (one when no rule applies).
- **Container type** rule: the shipping method is available when the load plan uses one of the
  selected types.

One Sylius shipment per order. The amounts are labelled indicative and the plan reads "Freight
is confirmed after the order": when your per-container price commits you, confirm freight
after the order or quote it (e.g. with
[tmonier/sylius-quote-request-plugin](https://github.com/thibmonier/sylius-quote-request-plugin)).

### CSV import and export

```bash
bin/console tmonier:container-fill:export-capacities > capacities.csv
bin/console tmonier:container-fill:import-capacities capacities.csv --dry-run
bin/console tmonier:container-fill:import-capacities capacities.csv
```

Columns: `variant_code;container_type;capacity;units_per_layer;units_per_case;case_weight_kg;layer_height_mm`
(`layer_height_mm` since 1.1, optional: 1.0 files import unchanged)
(`;` or `,`, UTF-8 with or without BOM). The first three are required; an empty `capacity`
removes the capacity; empty optional columns keep the stored values. Every line is checked
first: **one rejected line and nothing is written** (errors by line number).

### Shop API

`GET /api/v2/shop/orders/{tokenValue}/load-plan` returns the plan of a cart (computed now,
`source: cart`) or the plan recorded when the order was placed (`source: snapshot`):
satisfied flag, `plan` (the load plan: per planning rule the container type and containers,
each line counted once) and `containerCount`, `rules` (every applicable rule checked on its
whole scope: container type, containers with fill %, places, weight and volume ratios, binding
constraint, units per product, violations and suggestion), line violations ("sold by whole
cases"). A registered customer's order is only visible to that customer.
`PATCH /api/v2/shop/orders/{tokenValue}/complete` answers 422 while the rules are not met.

### Twig hooks

| Hook | Entry |
|---|---|
| `sylius_shop.cart.index.content.form.sections.general#right` | `tmonier_container_fill_gauge` (priority 50) and an override of `checkout` |
| `sylius_shop.product.show.content.info.summary.add_to_cart` | `tmonier_container_fill_preview` |
| `tmonier_quick_order.shop.product.matrix` | `tmonier_container_fill_gauge` (quick order 1.1+) |
| `sylius_admin.product_variant.{create,update}.content.form.*` | Logistics tab |
| `sylius_admin.product.{create,update}.content.form.*` | Logistics tab of simple products |
| `sylius_admin.order.show.content.sections#left` | load plan card |

The gauge is the Live component `tmonier_container_fill:gauge`: it listens to
`sylius:shop:cart_changed`; its actions emit it (on the cart page they reload the page).

## Screenshots

![Cart gauge in layer mode: side view of the pallet, "add 414" to complete it and room left for more layers](docs/images/shop-cart-layers.jpg)

![Cart gauge: a semi-trailer filled to 95 % by weight, 23 of 33 places used](docs/images/shop-cart-truck.jpg)

![Indicative preview on the product page: 90 = 1.5 layers of a EUR pallet](docs/images/shop-product-preview.jpg)

![Logistics tab of a product: case, weight, layer height and capacity per container](docs/images/admin-product-logistics.jpg)

![Container type form: plan by layers, load height and maximum layers](docs/images/admin-container-type-layers.jpg)

![Fill rule form](docs/images/admin-container-rule-form.jpg)

![Load plan recorded on the order](docs/images/admin-order-load-plan.jpg)

## Limitations

- A commercial filling rule, **not a physical loading plan**: no 3D bin packing, rotation,
  stacking order, axle loads, securing or ADR (the carrier's responsibility).
- One container type per order (per rule), one Sylius shipment; no double deck in mega
  trailers; no loading metres (LDM).
- Layer mode: one product per layer (no mixed layers), single-place container types (pallets,
  cartons); trucks count pallet places by capacity. `stackable` is only used in layer mode.
- The product page preview is indicative: the cart is the reference for mixed carts.
- Rules per customer group, not per organisation.

## Development

See `CLAUDE.md` for the local environment and commands, `SPEC.md` for the design and decisions.

## License

MIT, see [LICENSE](LICENSE).
