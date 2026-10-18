# Database Upgrade Guide: Custom Pack Templates & Explicit Cart Groups

## Overview
This upgrade introduces:
1. Owner-managed **Custom Pack Templates** (`commerce_pack_templates`).
2. Workspace-scoped **Explicit Cart Groups** (`commerce_cart_groups`).
3. Association of cart lines to groups (`commerce_cart_items.group_id`).
4. Persistence of immutable custom pack snapshots on orders (`commerce_order_groups.pack_snapshot`).

Existing databases are upgraded non-destructively without table drops or schema resets.

---

## Upgrade Steps for Existing Databases

1. **Run Migrations**:
   ```bash
   php artisan migrate
   ```
   This executes migration `2026_10_18_000001_add_pack_templates_and_cart_groups.php`.

2. **Database Changes Applied**:
   - Creates table `commerce_pack_templates` if not already present.
   - Creates table `commerce_cart_groups` if not already present.
   - Adds `group_id` foreign key column to `commerce_cart_items` if not present.
   - Adjusts unique index on `commerce_cart_items` to `['cart_id', 'group_id', 'variant_id']`.
   - Adds nullable `pack_snapshot` JSON column to `commerce_order_groups` if not present.

3. **Legacy Cart Data Compatibility**:
   - Pre-existing cart items keep their exact quantities with `group_id = NULL`.
   - The cart summary identifies rows with `group_id = NULL` as `mode = 'unconfirmed'`.
   - Customers can confirm these rows as retail via:
     - `POST /api/storefront/v1/cart/items/{id}/confirm-retail`, or
     - `PATCH /api/storefront/v1/cart/items/{id}` with `{ "quantity": <qty> }`.
   - Wholesale-only products cannot be confirmed as retail; customers are instructed to remove the item and add a valid wholesale pack.
   - Checkout is blocked with an actionable message until all legacy items are either confirmed as retail or removed.

4. **Historical Orders**:
   - Existing orders and historical fulfillment boxes remain completely unchanged.
   - New custom pack orders store an immutable snapshot in `pack_snapshot` and create multi-product packing boxes linked to the custom pack.
