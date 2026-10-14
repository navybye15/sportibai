# HANDOFF: Sportibai (ITIM211 school project)

Written for the next agent with fresh context. Read this first, then look at the code.

## Goal
Navii (2nd-year IT student) and one partner must build a PHP/HTML/CSS/MySQL web app for their
Information Management class (prof: m3lfx, reference repos `itim211-2b-shop` and `itim211-2b-2026`).
It must APPLY what the prof taught, not copy his shop. Chosen idea: **Sportibai**, an online
sporting-goods store (single store, one admin). Proposal title: "A Web-Based Sports Equipment Retail
Management System" (a Word proposal with 27 functional requirements already exists, see below).

Navii now wants to stop the loop of "Claude makes a zip, I download, delete the old folder, unzip".
The goal for the next session is to work directly in the local project folder (Claude Code) and commit to GitHub.

## Stack and environment
- PHP 8.3 on XAMPP (Windows), MySQL via phpMyAdmin, database name `sportibai`.
- Project path on Navii's machine: `C:\xampp\htdocs\sportibai\` (folder in the zip is called `hustle-sports`, old working name).
- Update: Claude later installed PHP 8.3 + MariaDB in its sandbox, linted all 27 PHP files (no errors) and ran the main flows
  over real HTTP with curl (see "Verified by automated test"). It has NOT seen the pages in a browser, so visual/UI checks are still Navii's.

## Prof's coding style (match it)
- Procedural mysqli (`mysqli_query($conn, $sql)`, not `$conn->query()`), no OOP, no PDO.
- Prepared statements via `mysqli_execute_query($conn, $sql, array(...))` (needs PHP 8.1+; fine on 8.3).
- `$_SESSION['message']` (red) / `$_SESSION['success']` (blue) flash messages, shown once by `includes/alert.php`, then unset.
- `header('Location: ...'); exit;` after redirects. Self-submitting forms via `$_SERVER['PHP_SELF']`.
- Register validation: `trim`, `preg_match` email, `strlen` password >= 6, confirm password. `password_hash` / `password_verify`.
- Guests may add to cart; login is required only at checkout; checkout needs a `customer` profile row (else redirect to profile.php). Prof said: do not force login before add-to-cart.
- Transactions (`mysqli_begin_transaction` / `commit` / `rollback`) in checkout.

## Navii's preferences
- Code must be beginner-friendly: plain if/else blocks only, no ternary, no inline-if, no clever shortcuts.
- Explanations in Taglish, simple core idea first, everyday words, one idea at a time, conversational.
- Build ONE phase at a time (not everything at once) and explain what was added each time.
- Flag anything that is NOT something the prof used, so Navii can decide if it is okay.

## Current progress (all 12 planned phases are written; testing status below)
1. DB schema `database/sportibai.sql`: users (role user/admin, status active/inactive), customer (profile, FK user_id),
   categories (is_active), items (is_active, CHECK stock>=0 price>0, image), orders (order_type pickup/delivery,
   COD only, status, completed_at), order_items (CHECK quantity>0, price snapshot), expenses.
   (The old 01_update.sql patch was deleted: everything is already in sportibai.sql.)
2. Project Architecture (Option 1 - Clean Root Structure):
   - Root Scripts: `index.php`, `shop.php`, `view_cart.php`, `cart_update.php`, `checkout.php`, `orders.php`, `order_detail.php`, `cancel_order.php`, `receive_order.php`, `profile.php`, `login.php`, `register.php`, `forgot_password.php`, `reset_password.php`, `logout.php`.
   - `item/`: `detail.php` (product detail page).
   - `admin/`: `index.php`, `items.php`, `item_add.php`, `item_edit.php`, `categories.php`, `category_add.php`, `category_edit.php`, `orders.php`, `order_view.php`, `users.php`, `expenses.php`, `expense_add.php`, `expense_edit.php`, `sales_report.php`.
   - `docs/`: `apple-design.md`, `HANDOFF.md`.
3. Shop UI: `index.php` (home), `shop.php` (search + category filter), `item/detail.php`, `view_cart.php`, `cart_update.php`
   (session cart, capped at stock), Apple-style design in `css/style.css` (SF Pro typography, spring motions, subtle specular glass).
4. `checkout.php`: re-reads price and stock from DB, `UPDATE ... WHERE stock >= qty`, transaction, COD, pickup/delivery. `orders.php` = order history.
5. `cancel_order.php`: customer can cancel only while Pending/Prepared, ownership check, restock, `FOR UPDATE`.
6. Admin (all files start with `includes/admin_guard.php`): `index.php` dashboard, `items.php` + `item_edit.php`
   (image upload validated: 2MB, extension whitelist, real MIME via finfo, random filename, in `includes/functions.php`),
   `categories.php` (soft delete, blocked if active items remain), `orders.php` (server-enforced status flow:
   Pending > Preparing > Ready for Pickup / Out for Delivery > Completed; Cancelled allowed from non-final states and restocks;
   sets completed_at), `expenses.php` (add/delete), `sales_report.php` (date range, Completed orders by completed_at,
   expenses, net income, top 5 sellers).
7. Proposal Word file `Sportibai_Project_Proposal.docx` (title, short description, 27 functional requirements; prof wants functional requirements only, printed).

Confirmed working by Navii: register, login, profile, image upload and display (after re-upload), and everything up to Phase 9 categories.
**Verified by automated test (sandbox, real MariaDB):** register validation, login (wrong/right), guest cart, checkout guards
(login, profile), delivery needs address, correct totals and stock deduction, cancel restores stock and a second cancel is refused,
checkout rolls back when stock is no longer enough, another user cannot cancel someone else's order, non-admins are redirected from
/admin/*, seed_admin works once only, forged status jump is rejected, Pending>Preparing>Ready>Completed sets completed_at,
image upload accepts a real PNG and rejects a PHP file renamed .jpg (stored under a random name), category delete blocked while it
has items, soft-deleted item disappears from the shop, sales report math (sales - expenses = net). Zero PHP warnings.
**Still needs a human in a browser:** how every page looks, the images showing, mobile layout, dark/light mode, and XAMPP-specific issues.

## What worked
- Doing phases one at a time, with explanations. Short `step_card` style instructions for XAMPP problems.
- Server-side rules in one place (e.g. `allowed_next_statuses()`), prepared statements everywhere, soft delete.

## What did not work / gotchas (do not repeat)
- `mysqli_execute_query()` threw "undefined function" on Navii's old PHP; fixed by upgrading to PHP 8.3.
- After upgrading, `mysqli_report()` was undefined: php.ini had `extension_dir="C:\php\ext"` and was a mixed-up file with PHP 4/5 directives.
  Fix was replacing it with the stock `php.ini-production` (renamed to php.ini) and enabling mysqli and pdo_mysql. Now working.
- SQL import failed when the file contained `DROP/CREATE DATABASE`; the file now expects an existing empty `sportibai` database.
- Uploaded images did not display at first because tiles were gradient placeholders; fixed with `item_tile()` in functions.php.
- Image 404 happened because the `images/uploads/` folder was missing locally (zip had only a hidden `.gitkeep` in it). Always extract the FULL folder.
- Could not read the prof's `itim211-2b-2026` repo listing (GitHub blocked automated access). His recent PHPMailer addition is UNVERIFIED;
  likely used for email verification, password reset or order emails. Ask Navii to show that code before copying the idea.

## Open items / things NOT in the prof's code (Navii should approve)
`htmlspecialchars` output escaping, soft delete (`is_active`), CHECK constraints, `completed_at`, `expenses` table, `FOR UPDATE` locks,
`session_regenerate_id`, finfo image validation, `UPDATE ... WHERE stock >= ?` stock guard.

## Next steps
1. Move the project into a git repo (`C:\xampp\htdocs\sportibai`), add a `CLAUDE.md` (copy the "Prof's coding style" and "Navii's preferences" sections above), commit to GitHub.
2. SECURITY CLEANUP before submitting: DELETE `admin/seed_admin.php` (after creating the first admin: admin@sportibai.com / admin123, then change the password) and DELETE `checkphp.php` if it is in the project.
3. Test every one of the 27 requirements in the proposal, one by one (customer + admin), and fix bugs found.
4. Decide wording: "Sports Equipment" vs "Sporting Goods" in the proposal (store also sells apparel/accessories). Not decided yet.
5. Optional, only if time: change password, pagination, CSRF tokens, login attempt limit, edit expenses, low-stock alert. Do NOT add non-functional requirements to the proposal (prof said functional only).
6. Prepare demo script / defense notes; the prof's quiz is on basic PHP (arrays, forms, sessions).
7. Optional rename: folder `hustle-sports` to `sportibai` in the repo; DB and site name are already Sportibai.

## Validator audit (2026-10-10) - status
Audit used the 27 FRs, real HTTP probes (PHP 8.3 + MariaDB) and the user's screenshots. Everything below was FIXED and re-tested, then the old regression suite was re-run (all passed):
- Nav is role-aware (admin: Dashboard/Items/Categories/Orders/Expenses/Sales Report/Log out). Admin accounts are blocked from cart/checkout/orders/profile (includes/customer_guard.php).
- R3 brand dropdown + min/max price filter on index.php (works together with search and category chips). R4 description shown (find_item now queries one row with a prepared statement).
- R22 admin orders list each order's items (customer orders page too). R24 expenses can be edited (admin/expense_edit.php). R25 Today / This week (Mon-Sun) / This month shortcuts.
- Security: htmlspecialchars on PHP_SELF (login/register/profile) and on sales_report dates (dates also validated as YYYY-MM-DD); raw SQL errors no longer shown to users.
- Duplicate email now says "That email is already registered." Email pattern is still preg_match but now accepts .edu.ph, hyphen and plus.
- Out-of-stock items cannot be added to the cart; shop shows "Out of stock" instead of the Add button; checkout rejects qty < 1; totals are rounded to 2 decimals.
- item_tile() falls back to the gradient when the image file is missing. CSS: heading overlap fixed (h1 line-height 1.05, hero span inline-block) - NOT visually verified, ask the user to look.
- Starter category names now match the proposal wording ("Fitness and Gym", "Apparel and Footwear"); an existing DB can be renamed from admin/categories.php.
R17 DECIDED (option A): the proposal wording was changed to "role-based login" (one shared login page, admin role checked by includes/admin_guard.php), so no admin/login.php is needed.
Proposal also now says categories "for example: Ball Sports, Fitness, Apparel, and Accessories" so it matches whatever names the admin uses. 27 FRs, all met. MIKASA V200W at 45,000 is intentional (user confirmed).
Still undecided: "Sports Equipment" vs "Sporting Goods" in the proposal title/description (store also sells apparel/accessories).
Other leftovers: delete admin/seed_admin.php and checkphp.php before submitting; optional CSRF tokens, change password, pagination. Prof's PHPMailer usage is still unverified.
