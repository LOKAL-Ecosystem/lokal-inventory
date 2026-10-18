# Design System Lokal

Aturan tampilan yang membuat semua aplikasi Lokal (**lokal-pos** termasuk Lokal Menu, dan **lokal-inventory**) terlihat dan terasa seperti satu produk. Sumber kebenarannya adalah kode di repo ini: `resources/css/app.css`, `resources/views/layouts/{app,menu}.blade.php`, `resources/views/components/`, `resources/views/auth/login.blade.php`, dan `resources/js/pages/`.

Kata **WAJIB** / **JANGAN** berarti aturan keras; sisanya adalah default yang boleh dilanggar dengan alasan jelas. `STYLING_GUIDE.md` adalah catatan lama; bila berbeda, **file ini yang menang**.

---

## 1. Prinsip

1. **Tenang dan terang.** Latar abu sangat muda, kartu putih, satu warna aksen biru. Tidak ada dekorasi yang tidak membawa makna.
2. **Dibuat untuk jari.** Utama: tablet 10 inci landscape di kasir, ponsel di Lokal Menu. Target ketuk minimal 44 px.
3. **Satu makna, satu warna.** Warna status (hijau/kuning/merah) hanya untuk status; biru hanya untuk aksi dan fokus.
4. **Cepat dibaca.** Angka besar dan tebal, label kecil dan redup, bahasa Indonesia yang singkat.
5. **Ringan.** Tanpa framework JS, tanpa CDN untuk JS/CSS, tanpa library UI atau chart baru.

---

## 2. Teknologi (tetap)

| Kebutuhan      | Pakai                                                             | Dilarang                                |
| -------------- | ----------------------------------------------------------------- | --------------------------------------- |
| CSS            | Tailwind 4 + DaisyUI 5, tema `lokal`                              | CSS framework lain, hex mentah di Blade |
| Build          | Vite 8, `@tailwindcss/vite`, `laravel-vite-plugin`                |                                         |
| Ikon           | Lucide, **didaftarkan manual** di `resources/js/app.js`           | icon font, SVG ikon sembarang, CDN      |
| Dialog & toast | SweetAlert2 (`window.Swal`), `<dialog>` DaisyUI                   | `alert()`, `confirm()` bawaan browser   |
| JS             | Vanilla ES modules, `fetch`                                       | React/Vue/Alpine, WebSocket             |
| Grafik         | SVG/HTML buatan sendiri                                           | library chart                           |
| Font           | Plus Jakarta Sans 300-800 (Google Fonts, `<link>` + `preconnect`) | font lain                               |

Setiap dokumen HTML: `<html lang="id" data-theme="lokal">`.

---

## 3. Token

### 3.1 Warna (tema DaisyUI `lokal`)

| Token                      | Hex       | Pakai untuk                                                    |
| -------------------------- | --------- | -------------------------------------------------------------- |
| `primary`                  | `#4285F4` | Aksi utama, link, fokus, item menu aktif, toggle aktif         |
| `primary-content`          | `#FFFFFF` | Teks di atas primary                                           |
| `success`                  | `#0F9D58` | Berhasil, lunas, stok aman                                     |
| `warning`                  | `#F4B400` | Menunggu, peringatan, stok menipis (teks di atasnya `#202124`) |
| `error`                    | `#DB4437` | Gagal, hapus, habis, tidak ada koneksi                         |
| `neutral` / `base-content` | `#202124` | Teks utama                                                     |
| `base-100`                 | `#FFFFFF` | Kartu, navbar, sidebar, modal, input                           |
| `base-200`                 | `#F8F9FA` | Latar halaman                                                  |
| `base-300`                 | `#E8EAED` | Border, pemisah                                                |

Aturan:

- **WAJIB** pakai kelas token (`bg-primary`, `text-error`, `border-base-300`), bukan hex di Blade/JS. Hex hanya di definisi tema dan `--viz-*`.
- Teks sekunder `text-base-content/60`; hint `/50`; ikon/placeholder pasif `/40`.
- Tint status: latar `bg-{warna}/10` atau `/5`, border `border-{warna}/20-30`, teks warna penuh. Contoh `bg-success/5 border-success/20 text-success`.
- Warna brand outlet (Lokal Menu) hanya menimpa `--color-primary` lewat `style` di `<head>` setelah divalidasi `^#[0-9a-fA-F]{6}$`; token lain tidak berubah.
- Gradasi hanya untuk panel brand halaman login: `bg-linear-to-br from-[#1a57c8] via-primary to-[#6ea8ff]`.

Definisi tema (salin apa adanya ke aplikasi lain):

```css
@import "tailwindcss";

@theme {
    --font-sans: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
}
@plugin "daisyui" {
    themes: lokal;
}
@plugin "daisyui/theme" {
    name: "lokal";
    default: true;
    --color-primary: #4285f4;
    --color-primary-content: #ffffff;
    --color-success: #0f9d58;
    --color-success-content: #ffffff;
    --color-warning: #f4b400;
    --color-warning-content: #202124;
    --color-error: #db4437;
    --color-error-content: #ffffff;
    --color-neutral: #202124;
    --color-neutral-content: #ffffff;
    --color-base-100: #ffffff;
    --color-base-200: #f8f9fa;
    --color-base-300: #e8eaed;
    --color-base-content: #202124;
}
```

### 3.2 Tipografi

| Peran                                   | Kelas                                                                       |
| --------------------------------------- | --------------------------------------------------------------------------- |
| Judul halaman                           | `text-3xl font-extrabold tracking-tight` (`<x-page-header>`)                |
| Judul kartu/modal                       | `font-extrabold text-lg`                                                    |
| Angka KPI                               | `text-2xl font-black` (+ `tabular-nums`)                                    |
| Angka besar (total bayar, kode pesanan) | `text-3xl`-`text-6xl font-black`                                            |
| Isi                                     | `text-sm` (14 px)                                                           |
| Label form                              | `text-xs font-bold text-base-content/70`                                    |
| Hint                                    | `text-[11px] text-base-content/50`                                          |
| Error field                             | `text-[11px] text-error font-semibold`                                      |
| Label kapital (judul seksi, peran)      | `text-[10px] uppercase tracking-widest font-extrabold text-base-content/60` |
| Header tabel                            | `text-[10px] uppercase tracking-wider font-extrabold text-base-content/50`  |

- Semua angka uang dan jumlah di kolom/tabel: `tabular-nums`, rata kanan. Format `Rp 25.000` (titik ribuan) **hanya di tampilan**; data tetap integer.
- Teks UI **bahasa Indonesia**, kalimat pendek, kata kerja di tombol ("Simpan", "Bayar", "Tutup shift"). Nama kode (class, kolom, enum) bahasa Inggris.
- Tanpa titik di akhir label/tombol; pesan error berupa kalimat lengkap dengan titik.

### 3.3 Bentuk, jarak, bayangan

| Elemen                     | Aturan                                                                                                                      |
| -------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Kartu, modal, panel        | `rounded-2xl`, `bg-base-100`, `border border-base-300` (atau `border-base-200/10`), **tanpa bayangan**                      |
| Input, tombol besar        | `rounded-xl` (tinggi `h-11`/`h-12`)                                                                                         |
| Tombol toolbar, chip kecil | `rounded-lg`, `btn-sm`                                                                                                      |
| Badge, avatar, pil status  | `rounded-full` atau `rounded-md` (pil status kecil)                                                                         |
| Bayangan                   | hanya elemen melayang: dropdown/modal `shadow-lg`, tombol aksi utama `shadow-lg shadow-primary/25`, kartu login `shadow-xl` |
| Padding halaman            | `p-4 md:p-6`; jarak antarblok `space-y-6` (halaman) / `space-y-4` (di dalam kartu)                                          |
| Padding kartu              | `p-5`-`p-6`                                                                                                                 |
| Lebar konten               | back office penuh; halaman form/detail `max-w-3xl`; Lokal Menu `max-w-xl` di tengah                                         |

Border selalu **1 px** (`.btn`, `.input`, `.select` sudah dipaksa di `app.css`).

### 3.4 Gerak

Transisi 150-300 ms (`transition-colors`, `transition-all duration-300`). Tidak ada animasi dekoratif. `prefers-reduced-motion` sudah dihormati global; jangan menimpanya.

---

## 4. Layout

### 4.1 Aplikasi staf (`layouts/app.blade.php`)

```
drawer lg:drawer-open
├─ drawer-content
│  ├─ nav.navbar          bg-base-100 border-b border-base-300, px-4
│  │    kiri : toggle sidebar
│  │    kanan: [badge "Tidak ada koneksi"] [bar shift*] [lonceng] nama+peran | Ganti | Keluar
│  └─ main#main-content   flex-1 p-4 md:p-6 overflow-y-auto   (body: bg-base-200)
└─ drawer-side            w-64 (terbuka) / w-14 (mode ikon), bg-base-100 border-r border-base-300
     header logo + nama modul (tinggi 65 px) → ul.menu (grup .menu-title + <x-nav-link>) → footer pengguna
```

\* hanya layar POS dan Pesanan.

- Item menu aktif: `bg-primary text-primary-content rounded-xl`; tidak aktif `hover:bg-base-200`. Mode ikon memakai tooltip.
- Grup sidebar mengikuti pekerjaan: Operasional, Katalog, Analitik, Pengaturan. Aplikasi lain (mis. Inventory) menambah grup sendiri ("Stok") dengan pola yang sama; item hanya tampil bila perannya boleh membuka.
- Layar kerja penuh tinggi (POS) memakai `h-[calc(100dvh-66px)]` dan memberi `main` tanpa padding.
- Link "Lewati ke konten" di awal `<body>` dan `id="main-content"` **WAJIB**.

### 4.2 Publik / pelanggan (`layouts/menu.blade.php`)

Tanpa sidebar/navbar staf. Satu kolom `max-w-xl mx-auto`, `bg-base-100` di atas `bg-base-200`, header dengan sampul + logo + nama outlet, bar aksi bawah lengket (`fixed bottom-0`, tombol `h-14 rounded-2xl`), dialog gaya bottom-sheet (`modal-bottom sm:modal-middle`). Hanya `app.css` + satu entry JS kecil; `noindex`. Tidak menampilkan elemen staf.

### 4.3 Login / autentikasi

Dua panel `lg:grid-cols-2`: panel brand gradasi biru + logo + tiga butir nilai (disembunyikan di layar kecil) dan panel form. Input `h-12 rounded-xl` dengan ikon kiri, tombol utama `h-12 rounded-xl shadow-lg shadow-primary/25`, tombol lihat/sembunyikan sandi. Ikuti ini untuk halaman autentikasi atau landing aplikasi lain.

---

## 5. Komponen

### 5.1 Komponen Blade (pakai ulang, jangan tulis ulang)

| Komponen                                          | Isi                                                                                 |
| ------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `<x-page-header title subtitle>` + slot `actions` | Judul halaman, subjudul `text-sm text-base-content/60`, tombol aksi di kanan        |
| `<x-field label name hint>`                       | Label, kontrol, hint, pesan error validasi                                          |
| `<x-modal id title size>`                         | `<dialog>` DaisyUI, `rounded-2xl`, judul `font-extrabold text-lg`, backdrop menutup |
| `<x-nav-link route icon label match>`             | Item sidebar dengan state aktif dan `aria-current`                                  |

Aplikasi lain **WAJIB** menyalin keempat komponen ini byte-per-byte (lihat bagian 10) supaya perubahan satu tempat bisa dibawa ke yang lain.

### 5.2 Tombol

| Peran                 | Kelas                                                                                         |
| --------------------- | --------------------------------------------------------------------------------------------- |
| Aksi utama            | `btn btn-primary rounded-lg font-bold` (besar: `h-12 rounded-xl shadow-lg shadow-primary/25`) |
| Sekunder              | `btn btn-outline` / `btn btn-ghost`                                                           |
| Netral gelap (ekspor) | `btn btn-neutral btn-sm rounded-xl gap-2 font-bold normal-case`                               |
| Berbahaya             | `btn btn-outline btn-error` atau `text-error` + `hover:bg-error/10` (ikon hapus)              |
| Ikon saja             | `btn btn-ghost btn-square btn-sm` **dengan `aria-label`**                                     |
| Ukuran                | toolbar `btn-sm`; layar sentuh minimal `min-h-11`; aksi utama kasir `h-12`                    |

Satu aksi utama per area. Tombol dengan ikon: ikon `w-4 h-4` + `gap-2`, ikon `aria-hidden="true"`. Saat memproses: nonaktifkan dan ubah teks ("Memproses...").

### 5.3 Form

- Selalu lewat `<x-field>`. Input: `input input-bordered w-full rounded-xl` (`h-12` di layar sentuh); angka uang `text-right font-bold` + `inputmode="numeric"`; angka desimal `inputmode="decimal"`; PIN `type="password"` `text-center tracking-[0.4em] font-bold`.
- Pilihan: `select select-bordered rounded-xl`, `checkbox checkbox-sm`, `radio`, `toggle` (switch biru 36x20 dari `app.css`), segmen `join` dengan `btn`/`aria-pressed`.
- Tombol modal: `Batal` (`btn-ghost`) di kiri, aksi utama di kanan dalam `.modal-action`.
- Validasi sisi server ditampilkan di bawah field; kesalahan umum di Swal atau teks `text-sm font-semibold text-error` dengan `role="alert"`.
- Hapus/aksi merusak: `<form data-confirm="...">` (SweetAlert2), bukan `confirm()`.

### 5.4 Kartu, tabel, daftar

- **Kartu**: `card bg-base-100 border border-base-300 rounded-2xl`, isi `card-body p-5`/`p-6`.
- **Kartu statistik**: label kapital kecil `/60`, angka `text-2xl font-black text-primary` (atau warna status), perubahan dengan panah + teks.
- **Tabel**: DaisyUI `table` dalam `overflow-x-auto`; header kapital kecil di baris `bg-base-200/20`; baris `hover:bg-base-200/30`; kolom angka rata kanan `tabular-nums`; aksi di kolom terakhir rata kanan/tengah; paginasi sisi klien `join` + teks "Menampilkan x-y dari z data".
- **Daftar bergaris**: `divide-y divide-base-200`.
- **Kosong / memuat**: satu baris teks tengah `text-sm text-base-content/60 py-10` (`role="status"`); tidak ada spinner besar.

### 5.5 Status & lencana

Satu makna satu warna, selalu **disertai teks** (jangan hanya warna):

| Makna                                | Pil                                                    |
| ------------------------------------ | ------------------------------------------------------ |
| Menunggu / belum dibayar             | `bg-amber-50 text-amber-700 border-amber-200/60`       |
| Berhasil / diterima / lunas          | `bg-emerald-50 text-emerald-700 border-emerald-200/60` |
| Dibatalkan / netral                  | `bg-base-200 text-base-content/60 border-base-300`     |
| Gagal / void / habis                 | `bg-red-50 text-red-700 border-red-200/60`             |
| Info / sumber ("Menu", label produk) | `bg-primary/10 text-primary border-primary/30`         |

Bentuk pil: `inline-block px-2 py-0.5 text-[10px] font-extrabold uppercase rounded-md border whitespace-nowrap`. DaisyUI `badge badge-success|warning|error|ghost` boleh dipakai untuk badge angka/hitungan.

Kebijakan angka stok: di atas minimum = success, sampai minimum = warning, nol atau negatif = error (stok boleh negatif; tampilkan jelas dengan tanda `-`).

### 5.6 Dialog, toast, notifikasi

- Dialog form/konfirmasi bisnis: `<x-modal>`; di ponsel muncul dari bawah.
- Konfirmasi singkat, galat, dan pilihan ("Coba lagi" / "Tutup"): `window.Swal` (warna konfirmasi primary; hapus memakai `#DB4437`). Toast: `toast: true, position: 'top'`, hilang sendiri 1,5-3,5 dtk.
- Rahasia yang tampil sekali (token API, secret webhook) memakai Swal yang tidak bisa ditutup dari luar.
- Lonceng header: titik merah berisi jumlah, daftar dibagi per seksi ("Menu habis", "Bahan hampir habis"), kosong = "Tidak ada notifikasi".

### 5.7 Pola khusus yang sudah baku

| Pola                | Aturan singkat                                                                                                                                                    |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Dialog bayar        | Pilih metode dulu (kisi 2 kolom `h-16`), baru nominal; ringkasan dengan nada `ok/warn/error`; tombol nominal cepat `btn-sm`                                       |
| Struk               | Kertas `font-mono text-xs`, latar `#FFFDF9`, garis putus-putus; cetak hanya `#receipt-paper`; struk thermal ASCII saja                                            |
| Bar shift & koneksi | Pil kecil di navbar; "Tidak ada koneksi" = `badge badge-error`; tombol transaksi redup 50 % + pesan saat offline (`data-requires-online`)                         |
| Kode pesanan        | `font-black`, besar, prefiks `#`, dapat dibaca dari jauh                                                                                                          |
| Kartu produk (Menu) | Foto 80 px `rounded-xl` lazy, nama `font-bold`, deskripsi 2 baris, harga `font-extrabold`; **habis** = abu, `opacity-60 grayscale`, tombol nonaktif, teks "Habis" |
| Pilihan kustomisasi | Grup dalam `fieldset`, aturan ("Wajib pilih 1", "Maks. 3") di kanan judul grup, selisih harga `+Rp x` di kanan opsi                                               |
| Kuantitas           | `−` / angka / `+` dengan tombol `min-h-11 min-w-11`, `aria-label`                                                                                                 |

### 5.8 Grafik (dashboard)

SVG/HTML manual, gaya di `app.css` (`.viz-*`, variabel `--viz-*`). Satu hue per seri, warna kategori tetap per entitas (Tunai biru, QRIS oranye, Transfer aqua, Kartu kuning), legenda untuk >= 2 seri, tooltip **dan** tampilan tabel, teks tidak diwarnai warna seri, ada keadaan kosong. Panah naik/turun memakai `--viz-up` / `--viz-down` dan selalu bertanda +/−.

---

## 6. Aksesibilitas (WAJIB)

- Target ketuk >= 44 px (`min-h-11`, `h-11`, `h-12`).
- Tombol ikon: `aria-label`; ikon dekoratif: `aria-hidden="true"`.
- Fokus terlihat: `:focus-visible` outline 2 px primary (global). **JANGAN** `outline-none` tanpa pengganti.
- Status dinamis: `role="status"` / `aria-live="polite"`; galat: `role="alert"`.
- Kontras: teks `/60` hanya untuk info sekunder; jangan teks `/40` untuk hal penting.
- Jangan mengandalkan warna saja (tambahkan teks, tanda +/−, atau ikon).
- Form: setiap kontrol punya `<label for>`; `autocomplete` benar; PIN `inputmode="numeric"`.
- Tabel: `<th scope="col">`; tombol paginasi punya `aria-label` dan `aria-current="page"`.

---

## 7. Kode di balik tampilan

- Data server ke JS lewat `<script type="application/json">` atau `data-*`; HTML dinamis di JS **WAJIB** `escapeHtml`.
- Halaman baru dengan JS: entry di `vite.config.js`; ikon baru didaftarkan di `resources/js/app.js` (impor + objek `icons`).
- Elemen yang butuh server: tambah `data-requires-online`. Aksi yang bisa diulang aman: kirim `Idempotency-Key`.
- Uang = integer rupiah; format hanya di tampilan.
- Tidak ada library UI/JS/chart baru, tidak ada CDN untuk JS/CSS tanpa diskusi.

---

## 8. Yang JANGAN dilakukan

- Hex warna mentah di Blade/JS, atau warna aksen kedua.
- Bayangan di kartu biasa, sudut lancip, border tebal.
- Tombol/target < 44 px di layar sentuh.
- `alert()`/`confirm()`/`prompt()` bawaan browser.
- Spinner layar penuh, animasi dekoratif, skeleton berlebihan.
- Menyalin gaya komponen ke tempat lain alih-alih memakai `<x-...>` yang ada.
- Teks Inggris di UI (kecuali nama merek/istilah umum seperti QRIS, PIN, POS).

---

## 9. Checklist halaman baru

- [ ] Memakai `layouts.app` (staf) atau `layouts.menu` (publik); `@section('title')` diisi.
- [ ] Judul lewat `<x-page-header>`; form lewat `<x-field>` / `<x-modal>`.
- [ ] Hanya token tema; kartu `rounded-2xl border-base-300` tanpa bayangan.
- [ ] Tombol utama satu per area; aksi berbahaya berwarna error dan dikonfirmasi.
- [ ] Status memakai pil/badge baku dan selalu ada teksnya.
- [ ] Target sentuh >= 44 px; diuji di lebar tablet 10 inci dan ponsel.
- [ ] Teks bahasa Indonesia; `aria-label` pada tombol ikon.
- [ ] Ikon baru terdaftar; entry Vite terdaftar; `escapeHtml` untuk HTML dinamis.
- [ ] Keadaan kosong, memuat, dan galat tampil jelas.
- [ ] `npm run build` dan test hijau.

---

## 10. Menyamakan aplikasi lain (mis. lokal-inventory)

Aplikasi terpisah **WAJIB** memakai tampilan yang sama. Langkah:

1. Pasang dependensi yang sama (`tailwindcss`, `@tailwindcss/vite`, `daisyui`, `lucide`, `sweetalert2`, `vite`, `laravel-vite-plugin`) dengan versi mayor yang sama dengan `package.json` repo ini.
2. Salin **utuh** `resources/css/app.css` (tema + override global + `.viz-*`) dan `public/logo.svg`; jangan mengubah token.
3. Salin komponen Blade bagian 5.1 dan pola layout `layouts/app.blade.php` (ganti teks "POS" di header sidebar dengan nama modul, mis. "STOK", dan isi menunya).
4. Salin `resources/js/app.js` (daftar ikon, `window.Swal`, `data-confirm`, `data-copy`, sidebar) dan tambahkan hanya ikon yang dipakai.
5. Muat font Plus Jakarta Sans lewat `<link>` yang sama; set `<html lang="id" data-theme="lokal">`.
6. Halaman login mengikuti bagian 4.3 dengan teks nilai yang sesuai modul.
7. Perubahan token atau komponen baku dilakukan **di repo ini dulu**, lalu disalin ke repo lain secepatnya, supaya tidak menyimpang.

Override yang sudah ada di `app.css` (jangan diduplikasi): `.btn` border 1 px; `.input`/`.select` border `#d1d5db` + ring fokus; `.toggle` switch ramping; `.checkbox`/`.radio` border `#9aa0a6`; scrollbar tipis 6 px; `select option` putih; `@media print` struk 80 mm; `.is-offline [data-requires-online]`.
