Product Reorder (Internal Manufacturing) — Simplified Implementation Plan

Goal

- Persist a snapshot of the BOM used for each internal-manufacturing reorder and ensure stock movements always match that snapshot.
- Keep the existing `rm_stock_movement` structure unchanged and keep logic simple: on update, delete and re-create movements.

Constraints

- DO NOT modify the `rm_stock_movement` table schema.
- DO NOT add new movement columns (no reference_item_id or similar).
- All deletes + inserts must run inside a DB transaction.
- This approach is NOT audit-safe; it prioritizes consistency and simplicity.

Core decision

- Add a single pivot table `reorder_product_raw_materials` that is the authoritative BOM snapshot for each reorder. This table is the only source of truth for which raw materials were used.

Schema (minimal)

- `product_reorders` (if missing): id, product_id, qty, status, created_by, created_at, updated_at, notes
- `reorder_product_raw_materials`: id, product_reorder_id (fk), raw_material_id, qty, created_at, updated_at

Create Reorder (behavior)

1. Begin DB transaction.
2. Load default BOM from `product_raw_materials`.
3. Apply user edits to the BOM (add/remove/update items).
4. Insert a `product_reorders` row.
5. Insert all BOM rows into `reorder_product_raw_materials` for that reorder.
6. For each BOM item, create the corresponding `rm_stock_movement` record using existing movement logic (e.g., PRODUCTION_RECEIPT). Link the movement via the existing movement `reference_id`/`reference_type` fields if available.
7. Commit transaction.

Update Reorder (behavior — simplified and critical)

- Allowed only when reorder `status` is not sold/finalized.
- Steps (all inside one DB transaction):
  1. Verify reorder is updatable.
  2. Delete all `rm_stock_movement` rows that belong to this reorder (use the existing `reference_id`/`reference_type` linkage).
  3. Delete all `reorder_product_raw_materials` rows for this reorder.
  4. Insert new `reorder_product_raw_materials` rows from the updated BOM provided by the user.
  5. Re-create `rm_stock_movement` records for each BOM row using the same logic as creation.
  6. Commit transaction.

Data consistency rules

- After create/update completes, the set of `reorder_product_raw_materials` rows must exactly match the `rm_stock_movement` records created for the reorder.
- No orphaned or duplicate movement records should remain (because update deletes and re-inserts atomically).

Notes on auditability and tradeoffs

- This strategy intentionally removes historical movement records on reorder updates. It is simpler and avoids complex diff/reverse logic but is not audit-safe.
- Document this limitation clearly for stakeholders.

API / Service changes (implementation notes)

- `ProductService::createInternalManufacturedProduct` (or reorder handler):
  - Persist reorder and BOM, create movements in a single transaction.
- `ProductService::updateInternalManufacturedProductReorder` (or similar):
  - Enforce status checks; perform delete-all + insert-all sequence inside a transaction.
- Use repository/service layering consistent with existing codebase.

Migrations

- Create migration: `create_reorder_product_raw_materials_table` (id, product_reorder_id, raw_material_id, qty, timestamps).
- Create migration: `create_product_reorders_table` if not present.

Testing

- Unit tests:
  - Create reorder: verify `reorder_product_raw_materials` and `rm_stock_movement` rows exist and match.
  - Update reorder (when not sold): verify old movements and BOM rows are removed and replaced with new ones.
  - Update reorder when sold: expect failure.
- Integration tests: end-to-end reorder create + update and stock balance verification.

Rollback / Backfill

- Default: apply behavior only to new and updated reorders after deployment.
- Optional: write ad-hoc backfill script if historical alignment is required.

Acceptance criteria

- Creating a reorder saves a `product_reorders` row and its `reorder_product_raw_materials` rows and creates matching `rm_stock_movement` records.
- Updating an allowed reorder deletes related `rm_stock_movement` and `reorder_product_raw_materials` rows and replaces them with the new BOM + movements, all inside a transaction.
- No orphaned/duplicate movement records after successful operations.

Next steps

- I can scaffold the two migrations and the new Eloquent model for `ReorderProductRawMaterial` and `ProductReorder`, then implement the create and update flows in `ProductService`. Which should I do first? (Start doing all)
