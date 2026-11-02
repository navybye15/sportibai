# Logic Bug Patterns in E-Commerce & Transactional Web Applications

This document catalogs common business logic, state lifecycle, and transactional bugs that cannot be caught by syntax checkers or linters, but cause severe functional issues in production.

---

## 1. Cross-Transaction State Leakage (Entity Identifier Mismatch)
### The Pattern:
An action or record that should belong to a specific **transaction / lifecycle instance** (e.g., `order_id`, `invoice_id`, `session_id`) is keyed only by persistent entities (e.g., `user_id` + `item_id`).

### Real-World Example (The Sportibai Review Bug):
- Customer buys *Nike Stride T-Shirt* in **Order 1**.
- Customer reviews *Nike Stride T-Shirt*. Review is saved as `(user_id=1, item_id=18)`.
- Customer buys *Nike Stride T-Shirt* again 2 weeks later in **Order 2**.
- When Order 2 is completed:
  - System checks: `has user 1 reviewed item 18? -> YES!`
  - Bug 1: Order 2 immediately shows `✓ Reviewed (Edit)`.
  - Bug 2: Clicking the button loads the review from Order 1.
  - Bug 3: Submitting overwrites the review for Order 1 with `ON DUPLICATE KEY UPDATE`.

### Root Cause:
The database table and queries lacked the transaction context (`order_id`). The unique key was `(user_id, item_id)` instead of `(order_id, item_id)`.

### How to Detect:
1. Identify all user actions on items (reviews, ratings, returns, warranty claims, refunds, downloads).
2. Check if a user can legally perform this action more than once across distinct orders.
3. If yes, verify whether the schema and queries include `order_id` or `order_item_id`.

---

## 2. Multi-Item Order Aggregation Flaws
### The Pattern:
An order contains $N$ distinct items ($N \ge 2$), but the UI or action handlers assume 1 item ($N = 1$), usually picking only the first item `$items[0]`.

### Symptoms:
- Order card displays a single action button (e.g., "Review Gear") pointing strictly to `items[0]`.
- Customer cannot review items 2, 3, etc. from that order, or the order is marked "Reviewed" as soon as item 1 is reviewed, locking out remaining items.

### How to Detect:
- Place orders with 2, 3, and 5 distinct items.
- Check if each individual item has an independent lifecycle and action path.

---

## 3. Premature UI State Transitions (Phantom Status)
### The Pattern:
UI buttons and badges derive status from a loosely coupled query rather than the specific record's current state.

### Symptoms:
- A button displays "Completed" or "Reviewed" before the user touches it.
- Cancel button is visible for orders already "Shipped" or "Out for Delivery".

### How to Detect:
- For every state badge and action button, list the exact Boolean condition required.
- Test with edge-case records: multiple orders, cancelled orders, zero-quantity orders.

---

## 4. State Machine Bypasses & Illegal Transitions
### The Pattern:
Status transitions are implemented across disparate files without a centralized transition validator.

### Examples:
- Customer cancels an order after admin marked it `Shipped`.
- Customer marks order `Received` when status is still `Pending` or `Prepared` (bypassing delivery).
- Uncompleted order items can be reviewed by calling `detail.php` directly.

### How to Detect:
- Attempt status transition POST requests out of order (e.g. POST to `cancel_order.php` when status is `Shipped` or `Delivered`).
- Verify backend authorization AND status preconditions inside the transaction lock (`FOR UPDATE`).

---

## 5. Stock / Inventory Asymmetry
### The Pattern:
Stock decreases on checkout, but fails to restore on cancel/refund, or restores multiple times if cancel is clicked twice.

### How to Detect:
- Test idempotent cancellation: double submit `cancel_order.php` with identical token or parallel requests.
- Verify that `stock_log` entries match net item stock changes.

