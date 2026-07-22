# Lokal Inventory

[![Laravel Framework](https://img.shields.io/badge/Laravel-10.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Tailwind CSS v4](https://img.shields.io/badge/Tailwind_CSS-v4.0-38B2AC?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![DaisyUI v5](https://img.shields.io/badge/DaisyUI-v5.0-5A0EF8?logo=daisyui&logoColor=white)](https://daisyui.github.io)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D_8.1-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**Lokal Inventory** is a specialized back-office inventory and ingredient management system. It bridges the gap between point-of-sale operations and raw material tracking by offering real-time, automated recipe deductions (Bill of Materials) triggered directly via webhooks from point-of-sale transactions.

---

## Key Features

- 📦 **Raw Material & Item Management**: Complete tracking of inventory stock items, units of measurement, categories, and supplier records.
- 🍳 **Recipe & BOM (Bill of Materials) Management**: Map POS products to multi-ingredient recipes. Supports base product recipes as well as custom modifier adjustments (e.g. extra cheese, syrup modifiers).
- 🔗 **Lokal-POS Integration Mapping**: Interactive dashboard to map incoming POS Product IDs to your internal inventory items.
- ⚡ **Real-time Webhook Receiver**: Dedicated API endpoints (`/api/webhooks/pos` & `/api/v1/webhooks/pos`) listening for order updates.
- 🛡️ **HMAC Signature Verification**: Secure webhook ingestion using HMAC SHA256 signature verification middleware to ensure incoming payloads are authentic.
- 🧩 **Idempotency Safeguard**: Tracks incoming event logs (`WebhookEvent`) via idempotency keys to prevent duplicate deductions from retried webhooks.
- ⚙️ **Queue-Based Stock Deduction**: Decoupled background processing (`ProcessPosWebhookJob`) guarantees fast webhook responses to the POS without delaying the checkout flow.
- 📈 **Detailed Stock Movement History**: Comprehensive ledger logs for every transaction (stock-in, manual adjustments, and POS deductions) with initial/final balance mapping.
- 🔔 **Low Stock & Valuation Dashboard**: Comprehensive overview displaying real-time total valuation, low-stock warnings, and negative stock alerts.

---

## Tech Stack

- **Backend**: Laravel v10 (PHP ^8.1)
- **Frontend & Styling**: Tailwind CSS v4, DaisyUI v5 (Blade Templates)
- **Database**: MySQL, PostgreSQL, or SQLite (configured via `.env`)
- **Assets Bundler**: Vite v8 + Laravel Vite Plugin
- **Queue/Asynchronous Handler**: Laravel Queue (Database, Redis, etc.)
- **Security**: HMAC SHA256 Webhook Verification
- **Testing**: Pest PHP Framework, Mockery

---

## Screenshot / Demo

![Lokal Inventory Dashboard Placeholder](https://via.placeholder.com/1200x630/1e293b/ffffff?text=Lokal+Inventory+Dashboard+Interface)
*(Replace with real dashboard screenshot showing metrics, stock levels, and webhook sync status)*

---

## Prerequisites

Ensure you have the following installed on your local environment:
- **PHP**: `^8.1`
- **Composer**: `^2.0`
- **Node.js**: `^18.x` or `^20.x` (with `npm`)
- **Database**: MySQL/MariaDB or SQLite
- **Web Server**: Local Apache/Nginx or Laravel Artisan/Laravel Sail

---

## Installation

Follow these steps to set up the project locally:

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/your-username/lokal-inventory.git
   cd lokal-inventory
   ```

2. **Install PHP Dependencies**:
   ```bash
   composer install
   ```

3. **Install Frontend Dependencies**:
   ```bash
   npm install
   ```

4. **Environment Configuration**:
   Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
   Generate the application key:
   ```bash
   php artisan key:generate
   ```

5. **Configure Environment Variables**:
   Open `.env` and configure your database and webhook secret settings (see [Environment Variables](#environment-variables) below).

6. **Run Database Migrations & Seeders**:
   ```bash
   php artisan migrate --seed
   ```

7. **Build CSS/JS Assets**:
   ```bash
   npm run build
   ```

---

## Running the Application

### Development Mode
To run both the Laravel built-in server, Vite dev bundler, and queue listener concurrently, you can use the custom composer command:
```bash
composer dev
```
*Alternatively, you can run them in separate terminals:*
```bash
# Start the local development server (accessible at http://localhost:8000)
php artisan serve

# Run Vite HMR server for styles and scripts
npm run dev

# Start queue worker to process webhook stock deductions
php artisan queue:listen
```

### Production Mode
In production, compile the assets and run the queue worker as a daemon:
```bash
# Compile and optimize production assets
npm run build

# Start the queue worker daemon
php artisan queue:work --daemon
```

---

## Folder Structure

A simplified view of the key directories in the project:

```text
lokal-inventory/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/                     # API controllers (Webhook & POS integration routes)
│   │   │   ├── DashboardController.php  # Dashboard statistics & valuation metrics
│   │   │   ├── RecipeController.php     # Bill of Materials & Modifier setup
│   │   │   └── ...
│   │   └── Middleware/
│   │       └── VerifyWebhookSignature.php # HMAC Signature Verification middleware
│   ├── Jobs/
│   │   └── ProcessPosWebhookJob.php     # Background queue job for stock deduction
│   ├── Models/
│   │   ├── Item.php                     # Raw ingredients / stock items
│   │   ├── Recipe.php                   # Mapping of products to ingredient quantities
│   │   ├── WebhookEvent.php             # Idempotency and webhook logging
│   │   └── ...
│   └── Services/
│       ├── RecipeCalculatorService.php  # Calculates exact deduction quantities
│       └── StockService.php             # General stock checking & warnings
├── database/
│   ├── migrations/                      # DB schemas (Items, Recipes, Webhooks, etc.)
│   └── seeders/                         # Initial users and categories seeding
├── resources/
│   ├── css/app.css                      # Tailwind v4 configuration imports
│   ├── js/app.js                        # Client-side bootstrap & SweetAlert integration
│   └── views/                           # Blade templates (daisyUI components)
├── routes/
│   ├── web.php                          # Backend management routes
│   └── api.php                          # Secure Webhook endpoints
└── vite.config.js                       # Vite asset building setup
```

---

## Environment Variables

The application relies on the following custom environment variables in addition to standard Laravel settings:

```ini
# Webhook signature verification secret (used to validate POS payloads)
WEBHOOK_SECRET=pos_inventory_secret_key_2026

# The endpoint where POS sends webhooks (for documentation/local reference)
INVENTORY_WEBHOOK_URL=http://your-domain.test/api/v1/webhooks/pos

# Standard Database configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lokal-inventory
DB_USERNAME=root
DB_PASSWORD=password

# Queues (Recommended: 'database' or 'redis' in production to enable async webhook processing)
QUEUE_CONNECTION=database
```

---

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

---

## Contact / Author

For questions, issues, or custom integration requests:
- **Author**: Satria (admin@lokal.id)
- **Project Link**: [https://github.com/your-username/lokal-inventory](https://github.com/your-username/lokal-inventory)
