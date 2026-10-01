# Walk & Wear — Shoe Shop (HTML/PHP + MySQL)

A simple e-commerce style shoe shop, similar in spirit to a TikTok Shop / Shopee storefront:
browse products, view details, add to cart, and check out — with a MySQL database behind it,
plus a small admin panel to manage products and view orders.

## What's Inside

```
walk-and-wear/
├── database.sql          <- Run this first to create the DB, tables, and sample products
├── index.php              <- Homepage / shop grid (pulls products from MySQL)
├── product.php             <- Single product page + "Add to Cart"
├── cart.php                <- Shopping cart (update qty, remove items)
├── checkout.php            <- Checkout form -> saves order to MySQL
├── about.php
├── contact.php
├── includes/
│   ├── config.php          <- Database connection settings (EDIT THIS)
│   ├── header.php
│   └── footer.php
├── admin/
│   ├── index.php           <- Admin dashboard: view products & orders
│   ├── add_product.php     <- Add a new product to the shop
│   └── delete_product.php
├── css/style.css
├── images/                 <- Put your shoe photos here (see Images section below)
└── js/
```

## Requirements

- PHP 7.4+ (with the `mysqli` extension enabled)
- MySQL or MariaDB
- A local server stack such as **XAMPP**, **WAMP**, **MAMP**, or `php -S` + a MySQL install

## Setup Instructions

### 1. Create the database

Open phpMyAdmin (or the MySQL command line) and import `database.sql`:

**Using phpMyAdmin:**
1. Go to phpMyAdmin → "Import" tab
2. Choose `database.sql`
3. Click "Go"

**Using the command line:**
```bash
mysql -u root -p < database.sql
```

This creates the `walk_and_wear` database with tables for `products`, `categories`,
`users`, `orders`, and `order_items`, plus 6 sample shoe products.

To import the additional products from `walk wear 2`, run `add_walk_wear_2_products.sql`
after `database.sql`. The import is safe to rerun; existing product names are skipped.

### 2. Configure the database connection

Open `includes/config.php` and update these lines to match your MySQL setup:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'walk_and_wear');
```

### Production hosting

This application requires a PHP host and a MySQL-compatible database; it cannot be deployed to Vercel as-is. Connect the GitHub repository to a PHP host (for example, Railway) and create a hosted MySQL database. Set these environment variables in the host's service settings:

- `DB_HOST`
- `DB_PORT` (usually `3306`)
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

Import `database.sql` into the hosted database and apply any migrations required for the schema you are deploying. Before making the site public, change or remove the seeded admin and seller accounts listed below. Never commit production database credentials.

### 3. Run the site

**Option A — XAMPP/WAMP/MAMP:**
Copy the whole `walk-and-wear` folder into your server's `htdocs` (XAMPP) or `www` (WAMP)
folder, start Apache + MySQL, then visit:
```
http://localhost/walk-and-wear/
```

**Option B — PHP's built-in server (quick local testing):**
```bash
cd walk-and-wear
php -S localhost:8000
```
Then visit `http://localhost:8000/`.
(Note: with the built-in server, links use root-relative paths like `/css/style.css`,
so make sure you start the server from inside the `walk-and-wear` folder.)

## Login Roles

The site has separate login pages for each role:

- Customer: `/login.php`
- Seller: `/seller/login.php`
- Admin: `/admin/login.php`

Only customers can create an account through `/register.php`. Admin and seller accounts
must be created directly in the `users` table by an administrator, with `role` set to
`admin` or `seller` and a password generated with PHP's `password_hash()`.

For an existing database, run `tracking_migration.sql` once, followed by
`fixed_delivery_map_migration.sql`. The fixed delivery migration converts legacy order statuses
and removes the old per-order GPS-sharing table. To remove the former rider feature, run
`remove_rider_migration.sql` once.

At checkout, customers enter a delivery address, search for it on the map, adjust the pin, and
confirm the selected point. The order stores the delivery latitude and longitude. Admins set the
shop's fixed location, seeded at District #3, San Manuel, Isabela, from the searchable/clickable map in the dashboard. Seller, admin, and
customer order views use Leaflet/OpenStreetMap and request a driving road route and distance from
the public OSRM routing service. Address search uses ArcGIS geocoding. Both services require an
internet connection; route and search failures are shown in the interface. The app does not collect
live GPS or move markers.

For local testing, the seeded accounts are:

- Admin: `admin@walkandwear.local` / `Admin@12345`
- Seller: `seller@walkandwear.local` / `Seller@12345`

## Admin Panel

Visit `/admin/index.php` to:

The admin panel requires an account with the `admin` role.

## Product Images

Products reference image paths like `images/shoe1.jpg`. Add your own photos into the
`images/` folder using those filenames (or update the `image` column in the `products`
table / via the admin "Add Product" form). If an image is missing, the page automatically
falls back to a 👟 icon so nothing looks broken.

## How the Cart Works

The cart is stored in the PHP session (`$_SESSION['cart']`) — no login required to shop.
When a customer checks out, their order and each line item are saved into the `orders` and
`order_items` tables, and product stock is automatically reduced.

## Extending This

Ideas if you want to grow this into a fuller store:
- Add customer accounts/login using the existing `users` table
- Add payment gateway integration (GCash, PayPal, Stripe, etc.)
- Add product reviews/ratings
- Add image upload (instead of typing a path) in the admin panel
- Add order status updates from the admin panel
