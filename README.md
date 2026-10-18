# Lokal Inventory

[![Laravel Framework](https://img.shields.io/badge/Laravel-10.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Tailwind CSS v4](https://img.shields.io/badge/Tailwind_CSS-v4.0-38B2AC?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![DaisyUI v5](https://img.shields.io/badge/DaisyUI-v5.0-5A0EF8?logo=daisyui&logoColor=white)](https://daisyui.github.io)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D_8.1-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**Lokal Inventory** is a specialized back-office inventory and ingredient management system. It bridges the gap between point-of-sale operations and raw material tracking by offering real-time, automated recipe deductions (Bill of Materials) triggered directly via webhooks from point-of-sale transactions.

---

## Key Features

> Spesifikasi lengkap ada di [CONTEXT.md](CONTEXT.md); kontrak webhook dengan Lokal POS ada di `WEBHOOK_CONTRACT.md` (v2) milik repo `lokal_pos`.

- 📦 **Bahan baku**: satuan dasar (g/ml/pcs), satuan beli + konversi, stok minimum, riwayat pergerakan per bahan.
- 🍳 **Resep + kustomisasi**: resep dasar per menu POS, penyesuaian per opsi (`add` / `replace` / `multiply`), simulasi pemakaian dan HPP.
- 🧾 **Potong stok otomatis**: webhook `order.confirmed` memotong stok, `order.voided` mengembalikannya (`void_return`). `order.created`/`payment.paid` disimpan tanpa efek.
- 🛡️ **Webhook aman**: signature HMAC-SHA256 (`X-Webhook-Signature`) diverifikasi dengan secret per outlet; outlet ditentukan dari signature, bukan dari isi payload.
- 🧩 **Idempoten**: `webhook_inbox` (kunci unik per outlet) + unique constraint di ledger; aman untuk retry, event ganda, dan event tidak berurutan.
- 📒 **Ledger append-only** (`stock_movements`): cache `current_stock` diperbarui dalam transaksi yang sama, bisa dihitung ulang dengan `php artisan inventory:rebuild-stock`.
- 💰 **Biaya**: `avg_cost` rata-rata tertimbang saat stok masuk, HPP & margin per menu, nilai stok.
- 🔁 **Ketersediaan menu**: menu ditandai habis saat bahan tidak cukup (hanya dikirim saat status berubah, tanda habis manual di POS tidak ditimpa).
- 📋 **Stok masuk, waste, opname**, supplier, laporan (CSV & PDF), peran pemilik/kasir, multi-outlet (global scope `BelongsToOutlet`).
- ⏱️ **Scheduler**: sinkron katalog tiap jam dan rekonsiliasi pesanan tiap 15 menit (`php artisan schedule:work`).

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
│   ├── Enums/                           # UserRole, BaseUnit, StockMovementType, WasteReason, ...
│   ├── Http/Controllers/
│   │   ├── Webhooks/LokalPosWebhookController.php  # verifikasi signature -> inbox -> job
│   │   └── ...                          # Ingredient, Recipe, StockReceipt, Waste, Opname, Report, Settings
│   ├── Jobs/                            # ProcessConfirmedOrder, ProcessVoidedOrder, SyncCatalogFromPos,
│   │                                    # PushAvailabilityToPos, ReconcileOrders
│   ├── Models/Concerns/BelongsToOutlet.php  # isolasi data per outlet (global scope)
│   ├── Services/
│   │   ├── StockService.php             # satu-satunya pintu tulis ledger
│   │   ├── StockDeductionService.php    # sale / void_return, idempoten
│   │   ├── RecipeUsageCalculator.php    # resep dasar + penyesuaian opsi
│   │   ├── StockReceiptService.php, OpnameService.php, CostingService.php
│   │   ├── MenuAvailabilityService.php, CatalogSyncService.php, ReportService.php
│   │   └── LokalPosClient.php           # satu-satunya klien REST /api/v1 lokal_pos
│   └── Support/CurrentOutlet.php        # outlet aktif (user web atau konteks eksplisit job)
├── database/migrations/                 # skema (outlets, ingredients, stock_movements, ...)
├── tests/Feature/                       # potong stok, webhook, ledger, opname, isolasi outlet, back office
└── routes/                              # web.php (UI + POST /webhooks/lokal-pos), api.php (alias webhook lama)
```

---

## Environment Variables

The application relies on the following custom environment variables in addition to standard Laravel settings:

```ini
# Database (MySQL untuk kerja/produksi; SQLite boleh untuk lokal)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lokal_inventory
DB_USERNAME=root
DB_PASSWORD=

# Queue: produksi wajib 'database' atau 'redis' + worker (php artisan queue:work)
QUEUE_CONNECTION=database

# Opsional
LOKAL_POS_TIMEOUT=10
LOKAL_POS_AVAILABILITY_ENDPOINT=   # belum ada di kontrak v2; kosong = perubahan ketersediaan hanya dicatat di log
```

Koneksi ke Lokal POS (URL, API key, webhook secret) **tidak** diatur lewat `.env`, melainkan per outlet di halaman **Koneksi POS** (disimpan terenkripsi).
Isi URL webhook yang tampil di halaman itu pada Integrasi → Webhook keluar di Lokal POS, dengan event `order.confirmed` dan `order.voided`.

Akun demo setelah `php artisan migrate --seed`: `admin@lokal.com` (pemilik) dan `kasir@lokal.com` (kasir), kata sandi `password`.

---

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

---

## Contact / Author

For questions, issues, or custom integration requests:
- **Author**: Satria (admin@lokal.id)
- **Project Link**: [https://github.com/your-username/lokal-inventory](https://github.com/your-username/lokal-inventory)
