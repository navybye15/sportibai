# SPORTIBAI — Modern Sports Equipment E-Commerce Platform

> Premium athletic equipment platform built with PHP and MySQL, featuring high-performance aesthetics, Shopee/Lazada-style multi-step order fulfillment, and interactive 3D core visuals.

---

## 🚀 Features

### 1. Home Page & Experience
- **Interactive 3D Core Logo**: Three.js-powered 3D rotating emblem with mouse/touch drag controls and ambient glow.
- **Highlights (Shop by Category)**: Glassmorphism category cards with sport-specific hue accents, hover physics, and count badges.
- **Why Sportibai Stage**: BrewedOps-inspired interactive horizontal slider with step navigation (`01 GEAR`, `02 SPEED`, `03 POWER`), swipe animations, and motion-blur transitions.
- **Featured Gear**: Curated top picks with direct "Add to Cart" and stock tracking.
- **Data Privacy & Footer**: Comprehensive footer with quick navigation links and Philippine Republic Act 10173 (Data Privacy Act of 2012) compliance notice.

### 2. Shopee/Lazada-Style Order Management
- **5-Tab Status Segmentation**:
  - `ALL`: Complete order history.
  - `TO PAY`: Newly placed orders being processed (`Pending`, `Prepared`, `Ready to Ship`).
  - `TO RECEIVE`: In-transit or store pickup ready (`Shipped`, `Out for Delivery`, `Ready for Pick Up`, `Delivered`).
  - `COMPLETED`: Orders confirmed received by customer or admin.
  - `CANCELLED`: Cancelled orders.
- **Dynamic Badge Counts**: Real-time SQL counts displaying unfulfilled counts per tab.
- **Order Details (`order_detail.php`)**:
  - Reference number generator (e.g. `SB-0000007`) with one-click copy button.
  - Live progress timeline visualization.
  - Snapshot of customer name, contact number, and delivery address at order placement.
  - Printable receipt layout.
  - Safe server-side customer `Order Received` confirmation.

### 3. Streamlined Checkout (`checkout.php`)
- **Fulfillment Method Switch**: Instant segmented toggle between **Delivery** and **Store Pick Up**.
- **Delivery Address Snapshot**: Pre-fills from user profile with editable fields; changes can optionally sync back to profile.
- **Cart Integration**: Item previews with real uploaded photos or branded category tiles.

### 4. Admin Management Suite (`admin/`)
- **Orders Control**: Advanced 7-step order advancement (`Pending` → `Prepared` → `Ready to Ship` / `Ready for Pick Up` → `Shipped` → `Out for Delivery` → `Delivered` → `Completed`).
- **User Account Management (`admin/users.php`)**: Active/inactive account toggle to deactivate fraudulent users immediately.
- **Catalog Management**: Items, categories, expenses, and sales reporting.

### 5. Security & Stability Hardening
- **CSRF Protection**: Token generation and validation on state-changing forms (`csrf_field()`, `csrf_verify()`).
- **Upload Directory Hardening**: `.htaccess` strictly prohibits PHP script execution within `images/uploads/`.
- **Session Security**: HttpOnly and SameSite Lax session cookies, 30-minute inactivity timeout, and live user status validation on every authenticated request.
- **Rate Limiting**: Brute-force protection on `login.php` (max 5 failed attempts within 15 minutes).
- **Timezone Sync**: Synchronized PHP and MySQL timezones to `Asia/Manila` (`+08:00`).

---

## 🛠️ Setup & Installation

### Requirements
- PHP 8.0 or higher
- MySQL 5.7+ / MariaDB
- Web server (Apache via XAMPP)

### Installation Steps
1. Clone or copy project files into `C:/xampp/htdocs/sportibai/`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `phpMyAdmin` (`http://localhost/phpmyadmin/`).
4. Create a database named `sportibai` and import `database/sportibai.sql`.
5. Run `database/migration_v3.sql` in phpMyAdmin to apply all order tracking and profile columns.
6. Copy `config/db_config.example.php` to `config/db_config.php` and verify your database credentials:
   ```php
   return array(
       'host' => 'localhost',
       'user' => 'root',
       'pass' => '',
       'name' => 'sportibai',
   );
   ```
7. Visit `http://localhost/sportibai/` in your browser.

---

## 📂 Project Directory Structure

```
sportibai/
├── admin/                  # Admin dashboard & management pages
│   ├── index.php           # Admin overview & metrics
│   ├── items.php           # Catalog inventory management
│   ├── categories.php      # Category management
│   ├── orders.php          # 7-step status order pipeline
│   ├── users.php           # User account status management
│   ├── expenses.php        # Operating expenses tracking
│   └── sales_report.php    # Sales analytics & revenue reports
├── config/                 # Private configuration
│   ├── db_config.php       # Active DB credentials (git-ignored)
│   └── db_config.example.php # Example template
├── css/
│   └── style.css           # Design system tokens, dark aesthetics & micro-animations
├── database/               # SQL schemas and migration scripts
│   ├── sportibai.sql       # Baseline schema
│   └── migration_v3.sql    # Order tabs, snapshots, reference numbers
├── images/
│   ├── spotibai-avatar.jpg # 3D sphere texture asset
│   └── uploads/            # Uploaded catalog item images (protected by .htaccess)
├── includes/               # Reusable modular components
│   ├── db.php              # Secure database connection & session setup
│   ├── functions.php       # Helpers (CSRF, session check, tiles, currency)
│   ├── header.php          # Global navigation bar & header
│   ├── footer.php          # Global footer & privacy policy notice
│   └── alert.php           # Flash notification banners
├── js/
│   ├── app.js              # Reveal animations, anchor restoration, BrewedOps stage slider
│   ├── spotibai-3d.js      # Three.js 3D avatar controller
│   └── three.min.js        # Local Three.js library
├── logs/                   # Protected PHP error logs (git-ignored)
├── index.php               # Customer homepage (3D hero, highlights, why sportibai)
├── shop.php                # Full product catalog with filter & search
├── checkout.php            # Shopee-style fulfillment & order placement
├── orders.php              # Customer orders dashboard (5 status tabs)
├── order_detail.php        # Single order tracking & receipt view
├── profile.php             # Customer profile & password management
├── login.php               # Secure login with brute-force rate limit
└── register.php            # Customer registration
```

---

## 📝 License
Proprietary project developed for Sportibai.
