# Changelog

All notable changes to `tmonier/sylius-container-fill-plugin`.

## 1.1.0

### Added
- Layer mode, opt-in per container type ("Plan by layers", maximum number of layers): pallets
  and cartons planned as stacks of layers (one product per layer) limited by the load height,
  the number of layers and the payload; binding constraint "stack height".
- Layer height per product (Logistics tab, CSV column `layer_height_mm`); `stackable` is now
  honoured in layer mode (a non-stackable product ends a stack).
- Gauge: side view of each pallet (server-side SVG with a text legend), remaining layers;
  "round up to the layer" and suggestions work in layers.
- Product page preview in layers ("90 = 1.5 layers ≈ 18 % of a EUR pallet").
- Load plan (order page, snapshot, shop API): layers of each container.
- Demo fixture: the EUR pallet of the demo is planned by layers.

### Upgrade
- Run the migration (`Version20261020100000`: nullable / defaulted columns). Layer mode is off
  for existing container types: plans are unchanged until you opt in.
- CSV exports gain a trailing `layer_height_mm` column; 1.0 files still import.

## 1.0.0

First release: container types with indicative presets, logistics data per variant, load
planner (places, payload, volume indicator), fill rules enforced at checkout (shop, shop API,
workflow guard) with admin override, cart gauge, per-container shipping, CSV import/export,
quick order bridge, 7 languages.
