# CLAUDE.md

Panduan untuk Claude Code di repo **lokal-inventory** (Lokal Stock). **[CONTEXT.md](CONTEXT.md) adalah sumber kebenaran** untuk produk, arsitektur, model data, enum, aturan bisnis, dan konvensi kode. Baca sepenuhnya sebelum menulis kode. File ini hanya merangkum cara kerja di repo; bila ada pertentangan, **CONTEXT.md yang menang**.

Bila sebuah keputusan menyentuh "Pertanyaan terbuka" (CONTEXT.md §12), jangan mengasumsikan jawabannya: buat implementasi yang mudah diubah atau tanyakan dulu.

## Ringkasan
Lokal Stock mencatat bahan baku, memotong stok otomatis sesuai resep saat menu terjual di **lokal-pos** (repo terpisah, database `lokal_pos`), lalu menghitung HPP dan margin. Kedua aplikasi hanya berkomunikasi lewat `WEBHOOK_CONTRACT.md` (v2.1) milik lokal-pos: webhook masuk `order.confirmed` / `order.voided` / `catalog.updated` di `POST /webhooks/lokal-pos/{token}`, dan REST `/api/v1` lokal-pos (header `X-Api-Key`) lewat `LokalPosClient`. Tanpa foreign key, JOIN, atau koneksi database lintas aplikasi.

Stack (sama dengan lokal-pos): PHP ^8.4 (pengembangan: 8.5 di Herd), Laravel 13, Sanctum 4, DomPDF 3, MySQL (kerja, `lokal_inventory`) / SQLite (test, `.env.example`), Blade + Vite 8 + Tailwind 4 + DaisyUI 5, Lucide 1, SweetAlert2, vanilla ES modules, Pest 5 / PHPUnit 13, Node 22.12+. Struktur aplikasi Laravel 10 **dipertahankan** (`app/Http/Kernel.php`, `app/Console/Kernel.php`, semua provider; bukan `bootstrap/app.php` ramping). PHPUnit 13 tidak membaca metadata doc-comment: pakai attribute (`#[DataProvider(...)]`, `#[Test]`, dst.). Dilarang: React/Vue/Alpine, library chart, library UI/JS baru tanpa diskusi, CDN.

## Perintah
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed       # outlet demo (katalog = seeder lokal-pos) + riwayat stok 3 minggu (InventoryDemoSeeder, ±3 menit di MySQL): admin@lokal.com (pemilik) / kasir@lokal.com / kasir2@lokal.com (kasir), password "password"
composer dev                      # serve + queue:listen + vite  (atau pakai Herd + `npm run dev`)
php artisan queue:work database   # worker (webhook, potong stok, push ketersediaan & peringatan stok)
php artisan schedule:work         # scheduler (sinkron katalog, rekonsiliasi, pembersihan token form)
php artisan inventory:rebuild-stock  # hitung ulang current_stock dari ledger
npm run build
php artisan test                  # SQLite in-memory (phpunit.xml), tidak menyentuh DB .env
```

## Batasan kerja
- Jangan ubah kontrak integrasi dengan lokal-pos (`WEBHOOK_CONTRACT.md` v2.1) dari sisi repo ini.
- Semua perubahan stok hanya lewat Service (`StockService` adalah satu-satunya pintu tulis ledger); `stock_movements` append-only dan idempoten.
- Data usaha selalu per outlet (`BelongsToOutlet`, `CurrentOutlet`); job dan webhook menentukan outlet secara eksplisit, bukan dari isi payload.
- Uang integer rupiah; takaran/stok `DECIMAL`, dibulatkan hanya di tampilan.
- Perubahan perilaku wajib disertai test dan `php artisan test` hijau.
- Jangan commit `.env`, file SQLite, atau `public/build`.
- Ikuti gaya kode sekitar; jangan format ulang file lama dengan Pint.
