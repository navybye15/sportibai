---
name: logic-bug-auditor
description: >-
  Audits, identifies, and verifies business logic bugs, transactional state leakage, repeat-action flaws, and UI status inconsistencies in Sportibai and e-commerce applications. Use when testing features, hunting non-syntax logic errors, verifying order/review/checkout workflows, or performing QA audits beyond simple linters.
---

# Business Logic & Behavioral Bug Auditor

This skill provides a systematic testing and auditing runbook for hunting deep logical bugs, state leakage across repeated transactions, false UI state flags, database entity mismatches, and workflow edge cases.

---

## 1. When to Use This Skill
Activate this skill whenever:
- User reports that an action on an older order/transaction leaks into or affects a newer order/transaction (e.g. reviewed state showing up prematurely).
- Investigating issues where the code has **zero PHP syntax or runtime errors**, but the business workflow behaves incorrectly.
- Verifying database constraint integrity for multi-purchase or multi-item scenarios.
- Performing end-to-end QA before releasing features involving Orders, Reviews, Stock/Inventory, and Checkout.

---

## 2. Core Bug Categories to Probe

### A. Cross-Transaction State Leakage
- **Symptom**: User completes an order for Item X and reviews it. Later, user places a new order for Item X. The new order is already marked "Reviewed", prefilled with old comments and photos.
- **Rule**: Actions and feedback must always be bound to the specific transaction (`order_id` or `order_item_id`), never keyed solely by persistent entities (`user_id`, `item_id`).

### B. Premature & Inferred UI Statuses
- **Symptom**: UI badges (e.g. `✓ REVIEWED (EDIT)`) evaluate a global table lookup (`SELECT * FROM reviews WHERE user_id = ?`) rather than checking the specific order item record.
- **Rule**: Each card/row in `orders.php` and `order_detail.php` must derive state strictly from its own `order_id`.

### C. Multi-Item Order Bypasses
- **Symptom**: When an order has multiple items, the code only checks or links to the first item (`$items[0]`), locking the user out of interacting with the other items in the order.
- **Rule**: Multi-item orders must present individual action paths for every item in that order.

### D. Destructive Overwrite vs. New Record
- **Symptom**: Using `ON DUPLICATE KEY UPDATE` on an entity-level unique key (`user_id, item_id`) obliterates previous transaction history when a user performs the action on a subsequent order.
- **Rule**: The unique constraint must incorporate the transaction identifier: `UNIQUE KEY uq_order_item_review (order_id, item_id)`.

---

## 3. Step-by-Step Audit Runbook

### Step 1: Run the Automated Logic Auditor Script
Run the automated script to check database schema, constraint integrity, and repeat-purchase invariants:
```powershell
c:\xampp\php\php.exe .agents/skills/logic-bug-auditor/scripts/audit_business_logic.php
```

### Step 2: Database Schema & Constraint Audit
1. Inspect table columns:
   - Does the child table contain the foreign key for the transaction context (e.g., `order_id`)?
2. Inspect indexes & unique constraints:
   - Does any UNIQUE key prevent repeat transactions (e.g., `UNIQUE(item_id, user_id)`)?
3. Inspect database VIEWs:
   - Verify that views (e.g., `vw_item_reviews`, `vw_order_details`) project the transaction context column.

### Step 3: Multi-Transaction Workflow Simulation
For every feature:
1. Create Transaction #1 for User A with Item X $\to$ Complete it $\to$ Perform user action (e.g., submit Review 1).
2. Create Transaction #2 for User A with Item X $\to$ Complete it.
3. Verify Transaction #2:
   - Does Transaction #2 show an unperformed/pending state?
   - Clicking the action button must present a blank/fresh form, NOT Transaction #1's data.
   - Submitting Transaction #2 must create a new row with `order_id = #2`.
   - Transaction #1's review must remain unchanged.
   - Product public page must display both verified reviews independently.

### Step 4: Multi-Item Invariant Check
1. Create an order with 2 or more distinct items (e.g. Item A and Item B).
2. Verify that reviewing Item A leaves Item B unreviewed, and that Item B can still be reviewed independently.

---

## 4. References & Cheat Sheets
- [Logic Bug Patterns Guide](./references/logic_bug_patterns.md)

