# Leat Loyalty Module Changelog

---
# 1.2.0 - Order API implementation

## Dependency:
piggy/piggy-php-sdk: 3.12.2 -> 3.12.7

## FEATURES

### Category & Product Catalog Sync
Two new cron jobs export the full Magento catalog to Leat so that order line items can reference known products and categories.

#### `CategoryExport` (`Cron/Data/CategoryExport.php`)
* **Purpose:** Exports all active, store-scoped Magento categories to the Leat `product-categories` endpoint.
* **Filters:** Path below store root, `level > 1`, `is_active = 1`.
* **Payload per category:** `external_identifier` (category ID), `name`.
* **Batching:** Batched in chunks of 250 items using `AbstractCron::buildBatches()`. Collections are paginated (250 per page) and cleared after each page to avoid memory spikes on large catalogs.
* **Throttling:** Runs at most once per week per shop UUID. Tracks last successful run via the `FlagManager` flag: `leat_category_export_last_run_{shopUUID}`.
* **Deduplication:** Deduplicates across stores sharing the same shop UUID within one cron execution (`processedShopUUIDs` registry).
* **Error Handling:** On error, the flag is still written (`success = false` + message), the exception is logged to the `category_export` logger, and it is then re-thrown.

#### `ProductExport` (`Cron/Data/ProductExport.php`)
* **Purpose:** Exports all store-scoped Magento products to the Leat `products` endpoint.
* **Payload per product:** `external_identifier` (SKU), `name`. Conditionally includes `categories` (list of `external_identifier`) and `description` (when non-empty).
* **Batching:** Batched in chunks of 100 items using `AbstractCron::buildBatches()`. Collections are paginated (500 per page) and cleared after each page.
* **Throttling/Deduplication:** Follows the same weekly throttle, per-shop-UUID deduplication, and flag-tracking pattern as `CategoryExport` using flag: `leat_product_export_last_run_{shopUUID}`.
* **Logging:** Logs progress per batch (batch number, item count, running total) to the `product_export` logger.
* **Error Handling:** Catches `LocalizedException`, `PiggyRequestException`, and any `Throwable`; writes the error message to the flag, logs, and re-throws.

#### `DataExport` (`Cron/Data/DataExport.php`)
* **Purpose:** Orchestrator cron that sequentially executes `CategoryExport` then `ProductExport`.
* **Details:** Registered as `leat_loyalty_sync_categories_and_products`. Runs every minute (actual execution is throttled to weekly in code).
* **Dependency:** If `CategoryExport` throws an error, `ProductExport` will not be executed.

---

### Admin "Sync Now" Button Integration
* The existing **Sync Data** button in admin (`LoyaltyAdminUI > Controller\Adminhtml\Sync\Data`) now also triggers category and product exports on demand, bypassing the weekly throttle.
* **Mechanism:** Writes `{time: 0, success: -1}` to both `leat_category_export_last_run_{shopUUID}` and `leat_product_export_last_run_{shopUUID}` flags before the attribute sync runs. On the next cron tick (within 1 minute), `DataExport` sees the flags as expired and runs immediately.
* **Status Handling:** The "in progress" flag state (`time = 0, success = -1`) is recognized by the Category and Product status blocks in admin, which display *"Sync in progress..."* until the cron completes and overwrites the flag with the real result.
* **UX Update:** Success message updated to acknowledge the asynchronous nature of the catalog export: *"Category and product export in progress, refresh page to see status."*

---

### Order API Export (New Export Mode)
* A new export mode **"Enabled | Order API"** has been added alongside the existing legacy transaction-based mode (*"Enabled | Legacy Transaction-based"*).
* Full order payloads are submitted to the Leat Order API via the new `OrderApiBuilder` (`Builder/Service/OrderApiBuilder.php`).
* **Payload includes:** Customer/shop UUID, line items, discounts, charges, payments, and timestamps.
* **Queue:** New queue type `Contact\Order\CreateAndProcess` registered as `create_and_process` in `di.xml`.
* **Config Flags:** New configuration flags control payload content (all depend on Order API mode):
    * Include shipping as charge
    * Include tax as charge
    * Separate shipping and tax (splits shipping tax into its own charge)
* **Default Values:** Order export disabled (`0`), tax/shipping as charge turned on (`1`).

---

### Returns / Credit Memo Export
* **New Cron:** `ReturnExport` (`Cron/Order/ReturnExport.php`), runs every minute.
* **Processing Filters:**
    * Processes credit memos created within a 6-hour window (`CREDITMEMO_RETRIEVAL_CUTOFF = '-6 hours'`).
    * Skips memos whose parent order was not exported to Leat.
    * Skips customers not present in the store's contact collection.
* **Persistence:** Marks exported memos with `exported_to_leat = true` and an `exported_to_leat_at` timestamp.
* **New Builder:** `ReturnApiBuilder` (extends `OrderApiBuilder`). Builds return payloads from credit memos including line items, positive adjustments, return-level discounts, and shipping/tax charges.
* **Queue:** New queue type `Contact\Order\Return\CreateAndProcess` registered as `return_create_and_process` in `di.xml`.
* **Database Updates:** New columns added to `sales_creditmemo`:
    * `exported_to_leat` (bool)
    * `exported_to_leat_at` (timestamp)
* **Admin Configurations:** New config group **"Credit Memo Configuration"** with toggle: *"Export Positive Adjustment as Return Line Items"* — distributes the creditmemo adjustment refund across order line items in the return export.
* **New Code Method:** `Config::getAdjustmentPositiveExportEnabled()`.

---

### Split Discount Breakdown
* Three new observers capture a per-rule, per-tax-rate discount breakdown:
    * `ProcessDiscount` (`salesrule_validator_process`) — Accumulates discount amounts per tax rate per rule onto the quote as `discount_description_array`, `discount_amount_array`, and `base_discount_amount_array`.
    * `ResetDiscounts` (`sales_quote_collect_totals_before`) — Clears the above arrays before each recalculation to prevent stale data.
    * `AddDataToOrder` (`sales_order_save_before`) — Copies the arrays from the quote to the order for use by the order export.
* **Database Updates:** New nullable text columns on `quote` and `sales_order`:
    * `discount_description_array`
    * `discount_amount_array`
    * `base_discount_amount_array`

---

### Business Profile Dropdown (`shop_uuid` field)
* **New Config Source:** `LoyaltyAdminUI\Model\Config\Source\BusinessProfiles`.
* **Functionality:** Fetches all shops from the Leat API (`client->shops->all()`) and presents them as a sorted dropdown. Displays a descriptive error label if the API call fails.
* **Admin UI:** The **"Shop Uuid"** free-text input field has been replaced with a **"Connected Shop"** select field backed by this source model.

---

### Admin Status Blocks (Refactored & New)
The old monolithic `PingStatus` block has been removed and replaced with a new `AbstractStatus` base class and dedicated subclasses:
* `Status\Ping` — Live API connection check via `ConnectionTester`.
* `Status\Attribute` — Attribute sync validation via `SyncValidator`.
* `Status\Category` — Shows the last `CategoryExport` run time, result, or error from flag `leat_category_export_last_run_{uuid}`.
* `Status\Product` — Shows the last `ProductExport` run time, result, or error from flag `leat_product_export_last_run_{uuid}`.

**Dynamic Status Messaging:**
All status blocks dynamically display:
> * *"Awaiting first sync..."* before the first run.
> * *"Sync in progress..."* during active execution.
> * A timestamped success or error message after completion.

**Admin Configuration UI:** Two new fields have been added to the General group:
* Category Sync Status
* Product Sync Status

---

### Giftcard Transaction UUID Persistence
* `GiftcardTransaction` now saves the Giftcard Object UUID from the Leat API back onto the order item as `leat_giftcard_object_uuid` after a successful transaction.
* **Database Updates:** New column on `sales_order_item`: `leat_giftcard_object_uuid` (varchar 36, nullable).

---

### Applied Coupons Saved to Order
* The `ProcessRewards` observer now captures the return value of `markCouponsAsCollected()` and writes it as a `leat_loyalty_applied_coupons` JSON string onto the order (persisted via `OrderRepositoryInterface`).
* **Purpose:** Provides redemption response data to the order export for downstream discount matching.
* **Database Updates:** New column on `quote`: `leat_loyalty_applied_coupons` (text, nullable).

---

### `buildBatches()` on `AbstractCron`
* New utility method on `AbstractCron` for splitting large datasets into JSON payload batches.
* **Constraints:** Respects both an item count ceiling (default `250`) and a byte ceiling (default `972800` bytes / ~950 KB).
* **Architecture:** Generator-based; works with both arrays and Generators as input.
* **Error Handling:** Throws `InvalidArgumentException` if a single item exceeds the byte limit, and `RuntimeException` if `json_encode` fails.
* **Usage:** Used by both `CategoryExport` and `ProductExport` for batch submissions.

---

### SalesRule RewardUUID Extraction
* `RuleSavePlugin` now extracts a reward UUID from the rule's conditions via a recursive search for `Leat\LoyaltyAdminUI\Model\Rule\Condition\Reward` condition types.
* Saves the found UUID as `rewardUUID` on the `SalesRule` extension attributes.

---

### New Cron Jobs
* `leat_loyalty_sync_returns` — Runs every minute (`ReturnExport`).
* `leat_loyalty_sync_categories_and_products` — Runs every minute (`DataExport`, throttled to max once per week in code or on-demand from admin Sync).
* `leat_loyalty_sync_products` — `ProductExport` (schedule not set in diff).
* `leat_loyalty_sync_categories` — `CategoryExport` (schedule not set in diff).

***

## CHANGES / REFACTORS

### OrderExport Cron Routing
* `OrderExport` now routes to either `OrderApiBuilder` (new) or `LegacyOrderBuilder` (renamed from `OrderBuilder`) based on `Config::getIsLegacyOrderExportEnabled()`.
* `GiftcardHelper` dependency has been removed.

### AppliedCouponsManager Restructured
* `markCouponsAsCollected()` return type changed from `void` to `array` (now returns redemption response data per loyalty transaction UUID).
* Internal helper methods (`getQuote`, `getAppliedCoupons`, `getSortedCollectableRewardUUIDs`, `getRewardUUIDForLoyaltyTransactionUUID`) have been moved earlier in the class and are now `public`/`protected` at the top.
* New constructor dependencies added: `SalesRuleExtensionRepository`, `QuoteItemExtensionRepository`, `RuleRepositoryInterface`.

### ConnectionTester
* `testConnection()` parameter changed from `int` to `?int` (nullable).
* Shop UUID is no longer required for a connection test; only the PAT is checked.
* Success message now includes the account UUID alongside the company and ID.
* The missing configuration error message has been shortened accordingly.

### Sync\Data Controller
* Now triggers category and product export flags ("in progress") immediately when the admin clicks **Sync**, before running the attribute sync.
* Success message updated to reflect the async nature of category/product exports (*"refresh page to see status"*).

### AbstractLeatResource: Contact Lookup Cache
* Added an in-memory cache (`customerContacts[]`) keyed by `customerId_storeId`.
* Prevents redundant API calls when the same customer is looked up multiple times within the same request lifecycle.

### AddGiftProducts Discount Calculation
* Previously always set discount amounts to `0` for gift items.
* Now calculates the actual discount for items that belong to the rule and have a custom price using the formula:
  $$\text{discount} = (\text{originalPrice} - \text{customPrice}) \times \text{qty}$$
* Items not belonging to the rule still receive a `0` discount.

### Admin UI Adjustments
* **Section Label:** Section label changed from *"Leat Loyalty"* to *"Configuration"*.
* **PAT Help Text:** Comment updated from generic Piggy PAT docs to Leat-specific instructions: *Install the Magento integration via the Leat marketplace, then generate a PAT under the "auth" tab of the installed integration.*
* **Order Configuration Sort Order:** "Order Configuration" admin group sort order changed from `40` to `1` (moves it to the top of the config section).

### QuoteItem\ExtensionAttributesRepository
* New method: `getListByQuoteId(int $quoteId): array` — retrieves all extension attributes rows for items belonging to a given quote.
* Backed by a new ResourceModel method `getByQuoteId()` using a `JOIN` on `quote_item`.

***

## BUG FIXES

* **AppliedCouponsManager (Coupon Array Cleared on Re-apply):** When a new coupon was added for a reward UUID, previously only that UUID's entry was unset via `unset($appliedCoupons[$rewardUUID])`. This has been changed to `$appliedCoupons = []` to clear the entire applied-coupons array before adding the new entry. As a result, only one reward coupon can be active at a time; adding a new reward replaces all previously applied ones rather than appending them.
* **AddGiftProducts (Discount Data Population):** Discount amounts were previously always set to `0`, causing gift items with custom prices to misreport their discount in cart/order totals. Fixed to compute and set actual discount figures based on custom vs. original price when the item belongs to the rule.
* **AbstractLeatResource (Duplicate Contact API Calls):** Resolved an issue where no caching existed for contact lookups, causing the same contact to be fetched multiple times per request. Fixed via an in-memory cache keyed on `customerId_storeId`.
