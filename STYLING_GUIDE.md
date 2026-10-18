# Panduan Styling Lokal (untuk Lokal Menu & Lokal Stock)

Dokumen ini merangkum cara UI **Lokal POS** di-style, supaya aplikasi **Lokal Menu** dan **Lokal Stock** terlihat dan terasa seperti satu produk. Sumber aslinya: `resources/css/app.css`, `resources/views/layouts/app.blade.php`, `resources/views/auth/login.blade.php`, dan `resources/views/components/`.

> Menu dan Stock adalah modul dari satu aplikasi Laravel (context.md), jadi **tidak perlu setup baru**: pakai `resources/css/app.css` yang sama, tema `lokal` yang sama, dan layout `layouts.app` yang sama. Bagian "Aplikasi terpisah" di bawah hanya untuk kasus bila suatu hari dipisah repo.

---

## 1. Stack (jangan ditambah tanpa diskusi)

| Kebutuhan    | Pakai                                                                              |
| ------------ | ---------------------------------------------------------------------------------- |
| CSS          | Tailwind 4 (`@import "tailwindcss"`) + DaisyUI 5                                   |
| Build        | Vite 8 + `@tailwindcss/vite` + `laravel-vite-plugin`                               |
| Ikon         | Lucide (`<i data-lucide="nama">`), **didaftarkan manual** di `resources/js/app.js` |
| Dialog/toast | SweetAlert2 (`window.Swal`)                                                        |
| JS           | Vanilla ES modules                                                                 |
| Font         | Plus Jakarta Sans (Google Fonts, bobot 300-800)                                    |

Dilarang: React/Vue/Alpine, library chart, CDN untuk JS/CSS, WebSocket.

---

## 2. Tema & warna

Tema DaisyUI bernama `lokal` (default), dipasang lewat `<html data-theme="lokal">`.

| Token          | Nilai     | Dipakai untuk                         |
| -------------- | --------- | ------------------------------------- |
| `primary`      | `#4285F4` | Aksi utama, link, fokus, toggle aktif |
| `success`      | `#0F9D58` | Berhasil, lunas, stok aman            |
| `warning`      | `#F4B400` | Peringatan, stok menipis              |
| `error`        | `#DB4437` | Gagal, hapus, stok habis              |
| `neutral`      | `#202124` | Teks gelap / elemen netral            |
| `base-100`     | `#ffffff` | Kartu, navbar, sidebar, modal         |
| `base-200`     | `#f8f9fa` | Latar halaman                         |
| `base-300`     | `#e8eaed` | Garis/border, pemisah                 |
| `base-content` | `#202124` | Teks utama                            |

Aturan:

- Pakai **token** (`bg-primary`, `text-error`, `border-base-300`), bukan hex mentah di Blade.
- Teks sekunder: `text-base-content/60`; placeholder/ikon pasif: `/40`-`/50`.
- Gunakan satu warna aksen (primary). Warna status hanya untuk makna status.

Definisi tema (salin bila perlu):

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

---

## 3. Tipografi

- Font global: Plus Jakarta Sans.
- Judul halaman: `text-3xl font-extrabold tracking-tight` (komponen `x-page-header`).
- Judul modal/kartu: `font-extrabold text-lg`.
- Label form: `text-xs font-bold text-base-content/70`.
- Hint: `text-[11px] text-base-content/50`; error field: `text-[11px] text-error font-semibold`.
- Label kecil kapital (peran, judul seksi): `text-[10px] uppercase tracking-widest font-extrabold text-base-content/60`.
- Angka/uang di tabel: tambahkan `tabular-nums`. Format uang `Rp 25.000` hanya di tampilan.
- Teks UI **bahasa Indonesia**; nama kode bahasa Inggris.

---

## 4. Bentuk, jarak, interaksi

- **Sudut**: kartu & modal `rounded-2xl`, input/tombol `rounded-xl` atau `rounded-lg`, badge/avatar `rounded-full`.
- **Border tipis** `border border-base-300` pada kartu; bayangan hanya untuk elemen melayang (dropdown, modal, kartu login).
- **Padding halaman**: `p-4 md:p-6`; jarak antarblok `space-y-4`/`gap-4`.
- **UI sentuh**: target tombol/area ketuk **minimal 44 px** (`h-11`/`min-h-11`, `btn` default atau `h-12` untuk aksi utama). Utamakan tablet 10 inci landscape.
- **Fokus**: `:focus-visible` outline 2px primary (sudah global). Jangan hilangkan.
- **Gerak**: transisi 150-300 ms; `prefers-reduced-motion` sudah dihormati secara global.
- **Aksesibilitas**: tombol ikon wajib `aria-label`; ikon dekoratif `aria-hidden="true"`; ada link "Lewati ke konten".

---

## 5. Override global di `app.css` (sudah ada, jangan diduplikasi)

- `.btn` border 1px.
- `.input`, `.select`: border 1px `#d1d5db`, latar putih; saat fokus border primary + ring `rgba(66,133,244,.1)`.
- `.toggle`: switch ramping 36x20 px, aktif biru primary.
- `.checkbox`, `.radio`: border `#9aa0a6` agar terlihat di kartu putih; aktif primary.
- Scrollbar tipis 6 px.
- `select option` selalu latar putih.
- Blok cetak struk `@media print` (hanya `#receipt-paper`, lebar 80 mm).
- Kelas grafik `.viz-*` dan variabel `--viz-*` untuk dashboard.

---

## 6. Layout aplikasi

Struktur `layouts/app.blade.php` (pakai ulang apa adanya untuk halaman Menu/Stock back office):

```
drawer lg:drawer-open
├─ drawer-content
│  ├─ nav.navbar        bg-base-100 border-b border-base-300  (toggle menu | lonceng | nama+peran | Ganti | Keluar)
│  └─ main#main-content flex-1 p-4 md:p-6 overflow-y-auto       (latar body: bg-base-200)
└─ drawer-side
   ├─ header logo + nama modul (h-[65px])
   ├─ ul.menu  dengan <li class="menu-title"> per grup + <x-nav-link>
   └─ footer pengguna (avatar primary + nama + peran)
```

- Sidebar `w-64` terbuka / `w-14` tertutup (mode ikon), `bg-base-100 border-r border-base-300`.
- Grup menu memakai `menu-title`, mis. Operasional, Katalog, Analitik, Pengaturan. **Menu baru** untuk Lokal Stock bisa jadi grup "Stok" (Bahan, Resep, Pergerakan stok), ditampilkan hanya untuk pemilik bila perlu.
- Setiap halaman: `@extends('layouts.app')`, `@section('title', '...')`, isi di `@section('content')`.

Halaman standar:

```blade
@extends('layouts.app')
@section('title', 'Bahan Baku')
@section('content')
<div class="space-y-6">
    <x-page-header title="Bahan Baku" subtitle="Kelola stok bahan di outlet ini.">
        <x-slot:actions>
            <button class="btn btn-primary btn-sm rounded-lg gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Tambah</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card bg-base-100 border border-base-300 rounded-2xl">
        <div class="card-body p-0 overflow-x-auto">
            <table class="table"> ... </table>
        </div>
    </div>
</div>
@endsection
```

---

## 7. Komponen Blade yang harus dipakai ulang

| Komponen                                          | Fungsi                                                               |
| ------------------------------------------------- | -------------------------------------------------------------------- |
| `<x-page-header title subtitle>` + slot `actions` | Judul halaman + tombol aksi                                          |
| `<x-modal id title size>`                         | Dialog `<dialog>` DaisyUI; buka `id.showModal()`, tutup `id.close()` |
| `<x-field label name hint>`                       | Label + input + hint + pesan error validasi                          |
| `<x-nav-link route match icon label>`             | Item sidebar dengan state aktif                                      |

Pola form di modal/halaman:

```blade
<x-field label="Nama bahan" name="name">
    <input id="name" name="name" class="input w-full rounded-xl" required>
</x-field>
<div class="modal-action">
    <button type="button" class="btn btn-ghost btn-sm rounded-lg">Batal</button>
    <button type="submit" class="btn btn-primary btn-sm rounded-lg font-bold px-6">Simpan</button>
</div>
```

Konfirmasi hapus: `<form data-confirm="Hapus bahan ini?">` (ditangani `app.js` dengan SweetAlert2), jangan `confirm()` bawaan browser.

---

## 8. Pola UI yang konsisten

**Tombol**

- Aksi utama: `btn btn-primary` (+ `rounded-lg`, `font-bold`).
- Sekunder: `btn btn-ghost` / `btn btn-outline`.
- Berbahaya: `btn btn-outline btn-error`.
- Ukuran toolbar: `btn-sm`; aksi besar di layar sentuh: `h-12 rounded-xl`.

**Status / badge** (satu makna satu warna)

- Aman/OK/lunas: `badge badge-success`; menipis/menunggu: `badge badge-warning`; habis/gagal/batal: `badge badge-error`; netral: `badge badge-ghost`.
- Untuk Stock: stok `>` minimum = success, `<=` minimum = warning, `<= 0` = error (stok boleh negatif, tampilkan jelas).

**Kartu statistik**: `card bg-base-100 border border-base-300 rounded-2xl`, judul kecil kapital `/60`, angka besar `text-2xl font-extrabold tabular-nums`.

**Tabel**: DaisyUI `table`, bungkus `overflow-x-auto`; kolom angka rata kanan; pagination sisi klien.

**Kosong/loading**: teks tengah `text-sm text-base-content/60 py-4`, tanpa spinner berlebihan.

**Notifikasi/toast**: `Swal` dengan `confirmButtonColor` primary (sudah di `app.js`).

**Grafik**: SVG/HTML manual, warna dari `--viz-*`, satu hue per seri, legenda untuk >= 2 seri, tooltip + tampilan tabel, teks tidak berwarna seri.

---

## 9. Lokal Menu (publik, `/m/{qr_token}`) — catatan khusus

Halaman ini dibuka pelanggan di **ponsel**, tidak memakai sidebar/navbar staf, jadi buat layout sendiri (mis. `layouts/menu.blade.php`) tetapi tetap:

- Token tema, font, dan `data-theme="lokal"` yang sama (load `resources/css/app.css`).
- **Mobile first**, satu kolom, lebar konten maks ~`max-w-md` di tengah; header lengket dengan nama outlet + nomor meja.
- Latar `bg-base-200`, kartu produk `bg-base-100 rounded-2xl border border-base-300`, harga `font-extrabold tabular-nums`.
- Label produk (`ProductLabel`) sebagai `badge`; menu habis: tampil redup (`opacity-60`) dan tombol nonaktif dengan teks "Habis".
- Keranjang: bar bawah lengket (`fixed bottom-0`) berisi jumlah item, total, tombol primary `h-12 rounded-xl`; beri `padding-bottom` aman di konten.
- Pilihan kustomisasi (modifier): `radio`/`checkbox` DaisyUI di dalam `x-modal` gaya bottom-sheet (`modal-bottom`), tampilkan aturan wajib/min/max dan `price_delta`.
- Halaman status pesanan memakai polling `fetch` (bukan WebSocket); tampilkan langkah: menunggu pembayaran -> lunas.
- Jangan tampilkan elemen staf (PIN, ganti pengguna, laporan).

## 10. Lokal Stock — catatan khusus

Back office untuk pemilik, memakai `layouts.app` utuh:

- Tambah grup sidebar "Stok" dengan ikon Lucide (mis. `package`, `boxes`, `clipboard-list`). **Daftarkan ikon baru di `resources/js/app.js`** (impor + objek `icons`), kalau tidak ikon tidak muncul.
- Daftar bahan: tabel dengan kolom nama, satuan, stok saat ini, minimum, status (badge), aksi.
- Pergerakan stok (`stock_movements`) tampil sebagai tabel kronologis; masuk = `text-success`, keluar = `text-error` (selalu sertai tanda +/- agar tidak bergantung warna).
- Lonceng header (`lib/notifications.js`) sudah memuat notifikasi menu habis; peringatan `StockBelowMinimum` ditambahkan ke sumber yang sama, bukan komponen baru.
- Form resep: baris dinamis dengan `x-field`, input angka `inputmode="decimal"`, tombol hapus baris ikon `trash-2` ber-`aria-label`.

---

## 11. Halaman login (referensi terbaru)

`auth/login.blade.php` adalah contoh paling baru untuk gaya "modern":

- Layout dua panel `lg:grid-cols-2`: panel brand gradasi biru (`bg-linear-to-br from-[#1a57c8] via-primary to-[#6ea8ff]`) disembunyikan di layar kecil; form di panel kanan.
- Input `h-12 rounded-xl` dengan ikon kiri; tombol lihat/sembunyikan password; tombol utama `h-12 rounded-xl shadow-lg shadow-primary/25`.
- Bila Menu/Stock butuh halaman autentikasi atau landing sendiri, ikuti pola ini.

---

## 12. Aplikasi terpisah (hanya bila dipisah repo)

1. Pasang dependensi yang sama (lihat `package.json`): `tailwindcss`, `@tailwindcss/vite`, `daisyui`, `lucide`, `sweetalert2`, `vite`, `laravel-vite-plugin`.
2. Salin `resources/css/app.css` (blok tema di bagian 2 + override di bagian 5), `logo.svg`, dan komponen Blade di bagian 7.
3. Muat font Plus Jakarta Sans dengan tag `<link>` Google Fonts yang sama.
4. Set `<html lang="id" data-theme="lokal">`.
5. Daftarkan ikon Lucide yang dipakai, sama seperti `resources/js/app.js`.

## 13. Checklist sebelum menggabungkan halaman baru

- [ ] Memakai token tema, bukan hex.
- [ ] Target sentuh >= 44 px dan terbaca di tablet 10 inci landscape.
- [ ] Teks UI bahasa Indonesia; tombol ikon ber-`aria-label`.
- [ ] Ikon baru sudah didaftarkan di `resources/js/app.js`; halaman baru ditambahkan ke `vite.config.js` bila punya entry JS.
- [ ] HTML dinamis di JS memakai `escapeHtml`; data server ke JS lewat `<script type="application/json">` atau `data-*`.
- [ ] Tidak ada library/CDN baru; `npm run build` dan `php artisan test` hijau.
