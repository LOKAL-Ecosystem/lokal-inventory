# Context: Lokal Inventory

Dokumen ini adalah konteks proyek untuk developer dan AI coding assistant yang bekerja di repo `lokal-inventory`. Baca ini sebelum menulis kode. Sumber kebenaran produk adalah dokumen **PRD Ekosistem Lokal** (bagian _PRD Lokal Stock_); file ini merangkum bagian yang relevan untuk kode.

> Nama tabel, kolom, enum, event, endpoint, dan class di dokumen ini adalah **usulan**. Selalu cek kode yang sudah ada lebih dulu dan ikuti konvensi yang sudah dipakai di repo bila berbeda.
>
> **Kontrak integrasi dengan `lokal_pos` ada di `WEBHOOK_CONTRACT.md` (v2.2; fitur v2.1 dipakai di sini) milik repo `lokal_pos`.** Bila nama event, field payload, atau endpoint di dokumen ini berbeda dengan kontrak tersebut, **kontrak yang benar**. Jangan mengubah kontrak dari sisi repo ini.

---

## 1. Produk

**Lokal** adalah rangkaian web-app untuk **cafe** yang terdiri dari tiga aplikasi: **Lokal POS** (kasir + back office), **Lokal Menu** (pemesanan mandiri lewat QR), dan **Lokal Stock**.

Repo ini adalah **Lokal Stock** (nama repo: `lokal-inventory`). Fungsinya mencatat bahan baku, **memotong stok otomatis sesuai resep** setiap kali menu terjual di Lokal POS, lalu menghitung **HPP dan margin per menu**.

| Pengguna                      | Kebutuhan                                                    |
| ----------------------------- | ------------------------------------------------------------ |
| **Pemilik / manajer** (utama) | Bahan baku, resep, stok masuk, opname, HPP & margin, laporan |
| **Kasir**                     | Mencatat stok masuk dan bahan terbuang (waste)               |

Tujuan produk:

- Pemilik tahu sisa bahan dan HPP per menu tanpa hitung manual.
- Menu otomatis ditandai habis di POS dan Lokal Menu saat bahan tidak cukup.
- Selisih stok fisik vs sistem terlihat jelas setiap opname.

**Prinsip selalu online**: seluruh ekosistem Lokal (POS, Lokal Menu, Lokal Stock) selalu online. Tidak ada mode offline, service worker, IndexedDB, atau penyimpanan transaksi di perangkat; detail perilaku saat jaringan lambat/putus ada di §9.

Model bisnis: **instalasi khusus per pelanggan**. Setiap cafe yang membeli mendapat instalasi sendiri (lokal-pos, plus lokal-inventory ini bila diambil sebagai modul tambahan) di subdomain dan database sendiri, disiapkan dan dirawat operator. Tidak ada pendaftaran mandiri, paket langganan, trial, atau billing di dalam aplikasi; jangan membangunnya. Multi-tenant (`outlet_id`) tetap dipakai karena satu instalasi bisa berisi beberapa cabang pelanggan yang sama. Di roadmap, Lokal Stock adalah **fase 3**.

---

## 2. Hubungan dengan `lokal_pos`

`lokal_pos` adalah **sumber kebenaran** untuk outlet, katalog (produk, kategori, grup & opsi kustomisasi), harga jual, dan pesanan. `lokal-inventory` **tidak pernah** membuat atau mengubah pesanan, dan tidak mengelola katalog.

```
lokal_pos ──(webhook: pesanan lunas / void, katalog berubah)──▶ lokal-inventory
lokal_pos ◀──(REST /api/v1, header X-Api-Key: baca katalog & pesanan)── lokal-inventory
lokal_pos ◀──(PATCH /products/{id}/availability, PUT /stock-alerts)── lokal-inventory   ← kontrak v2.1
```

### Yang diterima dari `lokal_pos` (webhook)

| Event (nama di PRD)                              | Efek di lokal-inventory                                                     |
| ------------------------------------------------ | --------------------------------------------------------------------------- |
| Pesanan lunas & dikonfirmasi (`order.confirmed`) | Potong stok sesuai resep + kustomisasi (`sale`)                             |
| Pesanan di-void setelah lunas (`order.voided`)   | Kembalikan semua stok yang sudah dipotong untuk pesanan itu (`void_return`) |
| Pesanan dibatalkan sebelum lunas (`cancelled`)   | **Tidak ada efek** (stok belum pernah dipotong)                             |
| Katalog berubah (`catalog.updated`)              | Sinkronkan ulang cache produk & kustomisasi                                 |

Status pesanan di `lokal_pos`: `awaiting_payment` → `confirmed`, atau `cancelled` (sebelum bayar) / `voided` (setelah lunas). **Prinsip bayar di awal**: hanya pesanan `confirmed` yang memotong stok.

### Yang diambil dari `lokal_pos` (REST `/api/v1`, header `X-Api-Key`)

- Katalog: produk, kategori, grup & opsi kustomisasi, harga jual, `is_available`.
- Detail pesanan (cadangan bila payload webhook tidak memuat item lengkap, dan untuk rekonsiliasi).

### Yang dikirim ke `lokal_pos`

- Perubahan **ketersediaan menu** (tandai habis / tersedia lagi) saat bahan tidak cukup / cukup lagi.
- Peringatan **stok minimum** untuk lonceng notifikasi di header POS (saat ini lonceng POS hanya berisi menu bertanda habis).

Keduanya ada di kontrak v2.1: `PATCH /api/v1/products/{id}/availability` (scope `catalog:availability`, body `{is_available, source: "inventory"}`; POS menjawab `{is_available, availability_source, applied}` dan tidak pernah menimpa tanda habis `manual`) dan `PUT /api/v1/stock-alerts` (scope `stock-alerts:write`, daftar **lengkap** `{alerts: [{key, name, current, min, unit}]}`, bukan selisih). Token API butuh empat scope: `catalog:read`, `orders:read`, `catalog:availability`, `stock-alerts:write` (halaman Koneksi POS menampilkan hasil tes per scope).

### Penautan otomatis & satu login (kontrak v2.3)

Pemilik cafe **tidak pernah** melihat atau mengisi token API, URL webhook, atau secret. Rincian protokol ada di `WEBHOOK_CONTRACT.md` milik `lokal_pos`; ringkasan sisi repo ini:

- **Konfigurasi server** (diisi operator sekali, `config/lokal.php`): `LOKAL_LINK_SECRET` (**sama** di kedua app; kosong = penautan & satu login mati, app tetap jalan sendiri dengan login email/password), `LOKAL_POS_URL`, `LOKAL_LINK_VERIFY_SSL` (false hanya berlaku saat `APP_ENV=local`).
- **Tanda tangan antar-app** (`App\Support\LinkSignature`, file sama di kedua repo): header `X-Lokal-Timestamp` (unix detik) dan `X-Lokal-Signature` = hex(HMAC-SHA256(timestamp + "." + body mentah, secret)); ditolak bila selisih waktu > 300 detik atau tanda tangan salah.
- **Penautan outlet**: POS membuat API client & langganan webhook sistem untuk setiap outlet baru, lalu `POST /link/outlets` (tanpa CSRF, throttle, tanda tangan wajib) dengan `{pos_outlet_id, name, pos_base_url, api_token, webhook_secret, staff: [{pos_user_id, name, email, role, is_active}]}`. Inventory (`OutletLinkService`) meng-upsert outlet berdasarkan `pos_outlet_id` (rahasia terenkripsi, `linked_at`) dan user cermin berdasarkan (outlet, `pos_user_id`), lalu men-dispatch `SyncCatalogFromPos` + `ReconcileOrders`, dan menjawab `{inventory_outlet_id, webhook_url}` (URL webhook unik outlet). Akun lama di outlet yang sama dengan email sama dan belum tertaut diambil alih; bila email sudah dipakai outlet lain, user cermin memakai `pos{outlet}-{user}@users.lokal.invalid`.
- **Satu login**: menu "Stok" di POS → `GET {LOKAL_INVENTORY_URL}/sso?token=...`, token = base64url(json) + "." + hex(HMAC-SHA256(base64url(json), secret)), payload `{pos_outlet_id, pos_user_id, name, email, role, is_active, exp, nonce}`, berlaku 60 detik. Inventory memverifikasi, menolak nonce yang sudah dipakai (cache sampai exp), memperbarui user cermin (nama/email/peran terbaru), menolak staf nonaktif, lalu login sesi. Halaman login = tombol "Masuk lewat Lokal POS" (`{LOKAL_POS_URL}/go/stock`); form password hanya saat `APP_ENV=local` atau penautan mati. Sidebar punya "Kembali ke POS"; keluar di Inventory hanya keluar dari Inventory.
- **Staf nonaktif** (`users.is_active = false` dari POS): tidak bisa masuk, dan sesinya diputus pada request berikutnya (`EnsureUserIsActive`).

---

## 3. Tech stack (sama dengan `lokal_pos`)

### Backend

- **PHP ^8.4** (pengembangan: 8.5 di Herd): `enum` (backed enum string) dan constructor property promotion.
- **Laravel 13**, dengan struktur aplikasi Laravel 10 yang **dipertahankan** (`app/Http/Kernel.php`, `app/Console/Kernel.php`, semua provider; bukan `bootstrap/app.php` ramping). **Sanctum 4**.
- **Autentikasi**: session untuk web, masuk lewat satu login dari `lokal_pos`. Token ke `lokal_pos` memakai **API Client token** (`X-Api-Key`) per outlet, dikirim otomatis saat penautan.
- **HTTP client**: `Http::` (Guzzle) untuk memanggil `/api/v1` `lokal_pos`.
- **Queue/event**: event, listener, dan job Laravel. Default `sync` di lokal; **produksi wajib `database` atau `redis`** dengan queue worker (pemrosesan webhook dan potong stok berjalan di queue).
- **Scheduler**: sinkronisasi katalog berkala dan rekonsiliasi pesanan.
- **PDF**: `barryvdh/laravel-dompdf` untuk ekspor laporan.
- **Debug**: Tinker 3, Ignition. **Test**: Pest 5 / PHPUnit 13 (metadata test lewat attribute, bukan doc-comment). **Lokal**: Laravel Sail / Laravel Herd.

### Database

- MySQL untuk kerja/produksi (usulan nama database: `lokal_inventory`); `.env.example` boleh memakai SQLite.
- **Query harus portabel** (MySQL dan SQLite), terutama agregasi laporan.
- Perhitungan stok dan biaya **diuji di MySQL** (SQLite tidak punya DECIMAL sejati).

### Frontend

- **Blade** server-rendered + komponen Blade.
- **Node 22.12+**. **Vite 8** + `laravel-vite-plugin` 3, **Tailwind CSS 4** (`@tailwindcss/vite`), **DaisyUI 5** dengan tema kustom Lokal.
- **Lucide 1** (di-bundle, tanpa CDN), **SweetAlert2** untuk dialog dan notifikasi.
- **Vanilla ES modules.** Jangan menambahkan React, Vue, Alpine, atau framework JS lain.
- **Grafik**: SVG dan HTML buatan sendiri, tanpa library chart.
- **Font**: Plus Jakarta Sans. Identitas visual sama dengan POS: logo "L" oranye sebagai favicon, di halaman login, header, dan sidebar (usulan teks di samping logo: **Stock**).

### Catatan versi

Upgrade framework selesai bersama `lokal_pos` (Laravel 10 → 13, PHP 8.4+; versi Composer dan npm sama dengan `lokal_pos`). `VerifyCsrfToken` meng-extend `PreventRequestForgery` (selain token, request `Sec-Fetch-Site: same-origin` juga lolos; pengecualian `webhooks/lokal-pos` dan `webhooks/lokal-pos/*` tetap), `config/cache.php` `serializable_classes => false` (cache hanya menyimpan skalar), nama cookie sesi, prefix cache, dan serialisasi sesi tidak diubah. Hindari pola yang mempersulit upgrade berikutnya.

---

## 4. Arsitektur di dalam repo

| Bagian            | Implementasi                                                                                                                                   |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Penerima webhook  | Rute `POST /webhooks/lokal-pos/{token}` (token unik per outlet di `outlets.webhook_token`, URL-nya dikirim ke POS saat penautan otomatis; URL lama tanpa token tetap didukung dengan mencari lewat signature; tanpa CSRF): verifikasi signature → simpan ke `webhook_inbox` → balas 2xx cepat → proses di job               |
| Pemroses pesanan  | Job `ProcessConfirmedOrder` / `ProcessVoidedOrder` yang memanggil Service `StockDeductionService`                                              |
| Sinkron katalog   | Job `SyncCatalogFromPos` (dipicu webhook `catalog.updated` dan scheduler) → tabel cache `pos_products`, `pos_modifier_groups`, `pos_modifiers` |
| Ketersediaan menu | Service `MenuAvailabilityService` menghitung porsi tersedia, lalu job `PushAvailabilityToPos`                                                  |
| Back office       | Halaman Blade: Bahan baku, Resep, Stok masuk, Opname, Waste, Supplier, Laporan, Pengaturan koneksi POS                                         |

### Multi-tenant

- Setiap tabel data usaha punya kolom `outlet_id` (outlet lokal-inventory), yang dipetakan ke outlet di `lokal_pos` lewat `outlets.pos_outlet_id`.
- Isolasi lewat **global scope Eloquent** (mis. trait `BelongsToOutlet`) + Policy. Job dan webhook **tidak punya sesi pengguna**, jadi outlet harus ditentukan eksplisit dari koneksi webhook/API (bukan dari isi payload bebas), lalu scope dijalankan untuk outlet itu.
- Satu outlet **tidak boleh** membaca atau mengubah data outlet lain, termasuk di laporan dan query mentah.

### Autentikasi pengguna

Satu login lewat Lokal POS (§2, kontrak v2.3): `GET /sso` (`SsoController`) membuat sesi Laravel biasa untuk user cermin staf POS, peran `owner` / `cashier` dengan arti yang sama seperti di `lokal_pos`. Form email/password (`LoginController`) hanya untuk `APP_ENV=local` atau saat penautan mati.

---

## 5. Model data (usulan)

### Koneksi & cache dari POS

- `outlets`: nama, `pos_outlet_id` (unik), `pos_base_url`, `pos_api_key` (**terenkripsi**), `webhook_secret` (**terenkripsi**), `webhook_token`, `linked_at`, `last_catalog_sync_at`. Kolom koneksi diisi oleh penautan otomatis, tidak lewat UI.
- `users`: `outlet_id`, `pos_user_id` (unik per outlet; user cermin staf POS), `role` (owner/cashier), `is_active`, nama, email, password (nullable: user cermin masuk lewat satu login).
- `pos_products`: `pos_product_id`, nama, kategori, harga jual (integer rupiah), `is_available` (nilai terakhir dari POS), `availability_source` (`null` | `manual` | `inventory`, dari POS), `synced_at`. **Hanya cache, jangan diedit dari repo ini.**
- `pos_modifier_groups`, `pos_modifiers`: `pos_modifier_id`, nama, `price_delta`, `synced_at`.
- `webhook_inbox`: `event_id` (unik), `event_type`, `payload` (JSON), `received_at`, `processed_at`, `attempts`, `last_error`.

### Bahan & resep

- `ingredient_categories` (opsional, sederhana).
- `ingredients`: nama, kategori, `base_unit` (g/ml/pcs), `purchase_unit` (mis. karton, botol, pack) + `purchase_unit_qty` (konversi ke satuan dasar, mis. 1 karton = 12.000 ml), `avg_cost` (per satuan dasar), `min_stock` (satuan dasar), `current_stock` (cache, lihat aturan ledger), `is_active`.
- `recipe_items`: `pos_product_id`, `ingredient_id`, `qty` (satuan dasar). Contoh: Caffe Latte = 18 g kopi + 150 ml susu + 1 cup + 1 tutup.
- `modifier_recipe_adjustments`: `pos_modifier_id`, `action` (`add` / `replace` / `multiply`), `ingredient_id`, `qty` (untuk add) atau `factor` (untuk multiply), `replaces_ingredient_id` (untuk replace). Contoh: extra shot = add 18 g kopi; oat milk = replace susu sapi dengan oat milk; ukuran large = multiply takaran.

### Pergerakan stok

- `stock_movements`: **ledger append-only**. `ingredient_id`, `type`, `qty` (positif = masuk, negatif = keluar, satuan dasar), `unit_cost` (untuk `purchase`), `source_type` + `source_id` (mis. item pesanan POS, baris stok masuk, baris opname; item pesanan POS memakai `"{uuid}#item:{item_id}"` dari `item_id` kontrak v2.1, sedangkan format lama `"{uuid}#{index}"` tetap dikenali supaya data lama tidak terpotong dua kali), `pos_order_uuid` (bila dari pesanan), `reason` (untuk waste), `created_by`, `created_at`. **Tidak pernah di-update atau dihapus**; koreksi selalu lewat pergerakan baru.
- Unique constraint untuk idempotensi: (`outlet_id`, `type`, `source_type`, `source_id`, `ingredient_id`).
- `suppliers`: nama, kontak (sederhana).
- `stock_receipts` + `stock_receipt_items` (stok masuk / belanja): tanggal, supplier, item (bahan, jumlah dalam satuan beli, harga total atau per satuan beli).
- `stock_opnames` + `stock_opname_items`: tanggal, status (draft/selesai), per bahan: stok sistem saat opname, hitungan fisik, selisih.

### Tipe data

- **Uang** (harga beli, harga jual, total): rupiah sebagai **bilangan bulat** (`BIGINT`). Jangan pakai float.
- **Takaran & stok**: `DECIMAL(14,3)` dalam satuan dasar.
- **`avg_cost` per satuan dasar**: `DECIMAL(14,4)`, karena harga per gram/ml sering pecahan rupiah (mis. Rp 0,0250/ml). Bulatkan ke rupiah hanya saat menampilkan HPP atau nilai stok.

---

## 6. Enum (usulan, PHP backed enum string)

```php
enum UserRole: string { case Owner = 'owner'; case Cashier = 'cashier'; }

enum BaseUnit: string { case Gram = 'g'; case Milliliter = 'ml'; case Piece = 'pcs'; }

enum StockMovementType: string {
    case Purchase = 'purchase';      // stok masuk / belanja
    case Sale = 'sale';              // dipotong otomatis dari pesanan POS yang confirmed
    case VoidReturn = 'void_return'; // dikembalikan karena pesanan di-void setelah lunas
    case Waste = 'waste';            // bahan terbuang (dicatat manual)
    case Adjustment = 'adjustment';  // koreksi hasil opname
}

enum WasteReason: string {
    case Spilled = 'spilled';     // UI: Tumpah
    case Damaged = 'damaged';     // UI: Rusak
    case Expired = 'expired';     // UI: Kedaluwarsa
    case WrongOrder = 'wrong_order'; // UI: Salah buat
}

enum RecipeAdjustmentAction: string { case Add = 'add'; case Replace = 'replace'; case Multiply = 'multiply'; }

enum OpnameStatus: string { case Draft = 'draft'; case Completed = 'completed'; }
```

Label UI dalam Bahasa Indonesia; nilai enum di database dalam bahasa Inggris snake_case.

---

## 7. Aturan bisnis

### Potong stok (pesanan `confirmed`)

1. Terima webhook pesanan confirmed → verifikasi signature → simpan ke `webhook_inbox` (abaikan bila `event_id` sudah ada) → job.
2. Untuk setiap item pesanan: pemakaian bahan = **resep dasar produk + penyesuaian modifier yang dipilih**, lalu × qty.
    - `add`: tambah takaran bahan.
    - `replace`: ganti bahan `replaces_ingredient_id` dengan `ingredient_id` (takaran mengikuti `qty` bila diisi, kalau tidak sama dengan takaran yang diganti).
    - `multiply`: kalikan takaran (semua bahan resep, atau bahan tertentu bila `ingredient_id` diisi) dengan `factor`.
    - Urutan penerapan: `replace` → `add` → `multiply`, supaya hasilnya konsisten.
3. Catat satu `stock_movement` `sale` per bahan per item pesanan, dalam satu transaksi database.
4. Resep yang dipakai adalah resep **saat event diproses**. Perubahan resep tidak mengubah pergerakan lama.
5. Produk **tanpa resep** dilewati (tidak error), tetapi dicatat agar muncul di laporan "menu tanpa resep".

### Idempoten

- Satu pesanan/item hanya memotong stok **sekali**, meski webhook terkirim ulang, diproses ulang dari inbox, atau muncul lagi saat rekonsiliasi. Andalkan unique constraint di `stock_movements`, bukan hanya pengecekan di kode.
- Event bisa datang **tidak berurutan**. Bila void datang sebelum confirmed diproses, proses confirmed dulu (ambil detail pesanan dari API bila perlu) atau tunda void sampai sale-nya tercatat.

### Void & pembatalan

- `voided` (setelah lunas): buat `void_return` untuk **semua** pergerakan `sale` pesanan itu, dengan jumlah yang sama persis (berdasarkan ledger, bukan menghitung ulang dari resep terbaru).
- `cancelled` (sebelum lunas): tidak ada efek.
- Bahan yang terlanjur diolah/terbuang dicatat **manual** sebagai `waste` oleh pemilik atau kasir.

### Stok saat ini

- Stok = jumlah semua `stock_movements` untuk bahan itu (ledger).
- `ingredients.current_stock` boleh disimpan sebagai cache untuk performa, tetapi **wajib diperbarui dalam transaksi yang sama** dengan pergerakan, dan harus bisa dihitung ulang dari ledger (sediakan command, mis. `inventory:rebuild-stock`).
- Stok **boleh negatif** tetapi ditandai di UI, supaya penjualan di POS tidak terhambat saat data stok awal belum akurat.

### Stok masuk & harga rata-rata

- Jumlah satuan beli dikonversi ke satuan dasar (`purchase_unit_qty`).
- `avg_cost` diperbarui setiap stok masuk (rata-rata tertimbang):

```
avg_cost baru = (stok lama × avg_cost lama + jumlah beli × harga beli per satuan dasar)
                / (stok lama + jumlah beli)
```

- Bila stok lama ≤ 0, `avg_cost` baru = harga beli per satuan dasar.

### HPP & margin

```
HPP menu   = Σ (takaran bahan di resep dasar × avg_cost bahan)
Margin     = harga jual (dari pos_products) − HPP
Margin %   = margin / harga jual
```

- Tampilkan juga HPP untuk kombinasi kustomisasi yang umum (opsional, lanjutan).
- HPP diperbarui setiap ada stok masuk (karena `avg_cost` berubah) dan setiap resep berubah.

### Stok opname

- Saat opname dibuat, sistem menyimpan snapshot stok sistem per bahan.
- Pemilik/kasir mengisi hitungan fisik; selisih = fisik − sistem.
- Saat opname diselesaikan, setiap selisih ≠ 0 menjadi satu pergerakan `adjustment`. Opname yang sudah selesai tidak bisa diubah.

### Waste

- Dicatat manual: bahan, jumlah (satuan dasar atau satuan beli), alasan (`WasteReason`), catatan opsional. Bisa oleh kasir.

### Ketersediaan menu

- Sebuah produk dianggap **habis** bila salah satu bahan di resep dasarnya tidak cukup untuk **satu porsi** (stok < takaran resep dasar).
- Hitung ulang setelah setiap pergerakan stok yang menyentuh bahan resep, lalu kirim perubahan (habis ↔ tersedia) ke `lokal_pos`. Kirim **hanya saat status berubah**, bukan setiap pergerakan.
- Tanda habis manual dari pemilik di POS tetap berlaku. Lokal-inventory tidak boleh menimpa produk yang ditandai habis manual menjadi tersedia (penanda `availability_source` dari kontrak v2.1). Bila POS menjawab `manual`, nilai itu disimpan di `pos_products`, produk tidak dihitung "habis karena stok", dan Inventory tidak mencoba menandai tersedia lagi; Resep menampilkannya sebagai "Habis (manual di POS)".

### Peringatan stok minimum

- Bahan dengan stok ≤ `min_stock` muncul di daftar "hampir habis" di dashboard lokal-inventory dan dikirim ke lonceng notifikasi POS lewat `PUT /stock-alerts` (job `PushStockAlerts`, ber-queue dengan retry). Dikirim setiap pergerakan stok menyentuh bahan yang ada di daftar (sebelum atau sesudah), atau saat bahan diubah/dinonaktifkan; job menghitung daftar lengkap dan melewati kiriman bila hash-nya sama dengan yang terakhir berhasil. Rekonsiliasi berkala mengirim ulang paksa.

### Hak akses

| Aksi                                       | Pemilik | Kasir |
| ------------------------------------------ | ------- | ----- |
| Kelola bahan, resep, supplier, koneksi POS | ✓       | –     |
| Catat stok masuk                           | ✓       | ✓     |
| Catat waste                                | ✓       | ✓     |
| Isi hitungan opname                        | ✓       | ✓     |
| Selesaikan opname (membuat adjustment)     | ✓       | –     |
| Lihat HPP, margin, nilai stok              | ✓       | –     |

Semua tindakan yang mengubah stok mencatat `created_by`.

---

## 8. Halaman (MVP)

| Halaman     | Isi                                                                                                                                  |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Dashboard   | Bahan hampir habis, stok negatif, menu tanpa resep, nilai stok, status sinkron dengan POS                                            |
| Bahan baku  | CRUD bahan, satuan dasar, satuan beli & konversi, stok minimum, stok saat ini, riwayat pergerakan per bahan                          |
| Resep       | Daftar produk dari cache POS; atur bahan & takaran per produk; atur penyesuaian per opsi kustomisasi; HPP & margin langsung terlihat |
| Stok masuk  | Catat belanja (supplier, item, jumlah, harga)                                                                                        |
| Waste       | Catat bahan terbuang dengan alasan                                                                                                   |
| Stok opname | Buat opname, isi hitungan fisik, lihat selisih, selesaikan                                                                           |
| Supplier    | CRUD sederhana                                                                                                                       |
| Laporan     | Pergerakan stok, pemakaian bahan per periode, nilai stok, HPP & margin per menu, selisih opname. Ekspor CSV dan PDF                  |
| Pengaturan  | Status koneksi ke `lokal_pos` (tersambung otomatis; tanpa field URL/API key/secret), tes koneksi, sinkron katalog, event gagal + Coba lagi |

UI: target tombol minimal 44 px (dipakai di tablet juga). Pencarian bahan cepat. Angka stok selalu ditampilkan dengan satuannya.

---

## 9. Kebutuhan non-fungsional

- **Ledger append-only**: tidak ada update/delete pada `stock_movements`.
- **Idempoten** di semua jalur masuk (webhook, retry job, rekonsiliasi).
- **Webhook**: verifikasi signature dengan `webhook_secret` outlet, tolak yang tidak valid, balas cepat (proses di queue), dan retry otomatis untuk kegagalan sementara. Simpan `last_error` agar bisa ditelusuri.
- **Rekonsiliasi**: job terjadwal mengambil daftar pesanan `confirmed`/`voided` dari `lokal_pos` untuk periode terakhir dan memproses yang terlewat (aman karena idempoten).
- **Presisi**: hitung dalam satuan dasar dengan DECIMAL; bulatkan hanya di tampilan.
- **Rahasia**: API key dan webhook secret dienkripsi di database, tidak pernah ditampilkan utuh di UI atau log.
- **Selalu online**: seluruh ekosistem Lokal (POS, Lokal Menu, Lokal Stock) selalu online. Tidak ada mode offline, service worker, IndexedDB, atau penyimpanan transaksi di perangkat pengguna. Yang dijaga adalah perilaku saat jaringan pengguna lambat atau putus (lihat "Kiriman ganda" dan "Koneksi putus di browser").
- **Kiriman ganda**: semua aksi yang mengubah stok (stok masuk, waste, simpan/selesaikan opname, simpan resep dan penyesuaian) membawa token idempotensi sekali pakai (`_idempotency_token`, UUID di hidden input, middleware `idempotent`). Token disimpan per outlet di `form_tokens` dengan unique constraint (`outlet_id`, `token`) dan dibersihkan scheduler setelah 2 hari. Kiriman kedua dengan token yang sama tidak membuat pergerakan baru; pengguna diarahkan ke hasil pertama. Token dilepas bila kiriman gagal (validasi/galat) supaya bisa dicoba lagi. Tombol kirim dinonaktifkan ("Menyimpan...") setelah ditekan. "Selesaikan opname" mengunci baris opname di dalam transaksi, sehingga request ganda yang bersamaan hanya membuat adjustment sekali.
- **Koneksi putus di browser**: layout menampilkan badge "Tidak ada koneksi" (event online/offline) dan tombol simpan tidak bisa dipakai selama offline. Form bertoken dikirim lewat `fetch`; bila gagal karena jaringan, halaman dan isian tetap utuh dan muncul pesan coba lagi (token yang sama membuat percobaan ulang aman).
- **Ketahanan** (sisi server): bila server `lokal_pos` tidak bisa dihubungi, lokal-inventory tetap bisa dipakai (stok masuk, waste, opname); sinkron katalog, push ketersediaan, dan rekonsiliasi pesanan menyusul lewat job. Ini **bukan** mode offline di perangkat pengguna.

---

## 10. Ruang lingkup

### MVP (fase 3 roadmap)

Bahan baku, resep + penyesuaian kustomisasi, potong stok otomatis dari POS, void return, stok masuk, opname, waste, peringatan stok minimum, ketersediaan menu otomatis, HPP & margin, laporan dasar.

### Fitur lanjutan

- Bahan setengah jadi (cold brew per batch, sirup homemade) dengan resep produksi dan stok sendiri.
- Purchase order ke supplier.
- Transfer stok antar-outlet (setelah multi-outlet ada).
- Pelacakan tanggal kedaluwarsa per batch.
- Saran belanja berdasarkan pemakaian rata-rata.
- HPP per kombinasi kustomisasi.

### Di luar cakupan

- Membuat atau mengubah pesanan dan katalog (milik `lokal_pos`).
- Akuntansi lengkap (jurnal, neraca).
- Aplikasi native.

---

## 11. Konvensi kode

- Konvensi Laravel standar: Form Request untuk validasi, Policy untuk otorisasi, Service/Action class untuk logika bisnis (`StockDeductionService`, `StockReceiptService`, `OpnameService`, `MenuAvailabilityService`, `CostingService`).
- **Semua perubahan stok hanya lewat Service**, tidak pernah `StockMovement::create()` langsung dari controller, supaya cache stok, avg_cost, ketersediaan menu, dan audit selalu ikut diperbarui.
- Logika bisnis ada di backend; JS hanya untuk interaksi dan tampilan.
- Client ke `lokal_pos` dibungkus dalam satu class (mis. `LokalPosClient`) supaya perubahan kontrak cukup diubah di satu tempat.
- Uang integer rupiah; format tampilan (`Rp 25.000`) hanya di view.
- Teks UI dalam Bahasa Indonesia; nama kode dalam bahasa Inggris.
- Jangan menambahkan library UI atau JS framework baru tanpa diskusi.
- Data demo: seeder sebaiknya memakai katalog yang sama dengan seeder `lokal_pos` (outlet Lokal Coffee), lengkap dengan bahan, resep, dan stok awal, dan dibuat lewat Service (bukan insert langsung).
- Tulis Feature test untuk: potong stok resep + modifier (add/replace/multiply), idempotensi webhook ganda, event tidak berurutan, void return, cancelled tanpa efek, avg_cost tertimbang (termasuk stok lama ≤ 0), opname → adjustment, ketersediaan menu berubah hanya saat status berubah, isolasi antar-outlet, verifikasi signature webhook.

---

## 12. Pertanyaan terbuka

- ~~**Kontrak `lokal_pos` untuk ketersediaan menu**~~ **Terjawab (v2.1)**: `PATCH /products/{id}/availability` dengan penanda `availability_source`.
- ~~**Kontrak untuk peringatan stok minimum**~~ **Terjawab (v2.1)**: Inventory mendorong daftar lengkap lewat `PUT /stock-alerts`; POS hanya menyimpan dan menampilkan.
- ~~**Isi payload webhook pesanan**~~ **Terjawab (v2/v2.1)**: payload memuat `items[]` lengkap (`item_id`, `product_id`, `quantity`, `modifiers[].modifier_id`); REST `GET /orders` memuat `modifier_id` juga. Pencocokan opsi lewat nama tinggal cadangan untuk POS lama. `catalog.updated` membawa `product_ids` terdampak.
- ~~**Autentikasi pengguna**~~ **Terjawab (v2.3)**: satu login lewat Lokal POS (token sekali pakai 60 detik, user cermin per staf POS); tidak ada akun terpisah. Lihat §2 "Penautan otomatis & satu login".
- **HPP di laporan POS**: apakah POS menampilkan HPP/margin dari inventory, atau cukup di lokal-inventory.
- Hosting produksi: **belum diputuskan**. Driver queue: ~~database atau redis~~ **diputuskan: database** (worker via Supervisor + cron `schedule:run`, lihat README).
- ~~Kapan upgrade ke versi Laravel dan PHP~~ **Selesai**: Laravel 13, PHP ^8.4 (bersama `lokal_pos`).

Bila menemui keputusan yang menyentuh pertanyaan di atas, jangan mengasumsikan jawabannya; buat implementasi yang mudah diubah atau tanyakan dulu.
