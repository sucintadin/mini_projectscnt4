# Petal & Co. — Sistem Informasi Penjualan Bunga

Aplikasi web sederhana untuk mencatat transaksi toko bunga. Dibuat menggunakan PHP, MySQL/MariaDB, HTML, CSS, dan JavaScript, tanpa framework tambahan.

## Fitur

- Menampilkan ringkasan penjualan: total pendapatan, jumlah transaksi, item/buket terjual, dan transaksi hari ini.
- Menambah, mengedit, dan menghapus data transaksi penjualan.
- Pratinjau perkiraan total pembayaran (subtotal live preview) saat mengisi jumlah dan harga satuan di modal formulir.
- Tombol pilihan cepat harga (price presets) di formulir: 75 rb (Mini), 150 rb (Standar), 250 rb (Spesial), dan 350 rb (Premium).
- Menampilkan catatan transaksi langsung di dalam tabel daftar penjualan.
- Pratinjau dan cetak struk/nota transaksi resmi siap cetak (invoice modal dengan layout printer-friendly).
- Ekspor laporan transaksi ke format CSV (kompatibel langsung dengan Microsoft Excel / spreadsheet) yang mengikuti filter dan urutan aktif.
- Mencari transaksi berdasarkan nama pelanggan, nama bunga, atau catatan.
- Memfilter transaksi berdasarkan tanggal tertentu dan metode pembayaran (Tunai, Transfer, QRIS, Kartu).
- Mengurutkan transaksi berdasarkan: Tanggal Terbaru, Tanggal Terlama, Total Tertinggi, Total Terendah, dan Nama Pelanggan (A-Z).
- Format penanggalan berbahasa Indonesia yang konsisten dan ikon bunga cerdas berdasarkan jenis bunga.
- Validasi input di sisi server, prepared statement PDO, pemisahan error koneksi/validasi, dan perlindungan CSRF.
- Tampilan responsif dan elegan dengan nuansa floral untuk desktop, tablet, dan ponsel.

## Struktur folder

```text
mini_projectscnt4/
├── assets/
│   ├── app.js
│   └── style.css
├── config/
│   └── database.php
├── database.sql
├── index.php
└── README.md
```

## Persyaratan

- PHP 8.0 atau lebih baru dengan ekstensi `pdo_mysql` dan `mbstring`.
- MySQL 5.7+ atau MariaDB 10.3+.
- XAMPP, Laragon, atau server PHP lokal lain.

## Membuat database

### Melalui phpMyAdmin

1. Jalankan layanan MySQL dari XAMPP/Laragon.
2. Buka phpMyAdmin, pilih tab **Import**, lalu pilih file `database.sql`.
3. Klik **Import** / **Go**. Database `florist_db`, tabel `sales`, dan lima transaksi contoh akan dibuat.

### Melalui terminal

```bash
mysql -u root -p < database.sql
```

Jika pengguna database tidak memiliki kata sandi, tekan Enter saat diminta. Skrip hanya memasukkan transaksi contoh ketika tabel masih kosong.

## Konfigurasi koneksi

Konfigurasi bawaan di `config/database.php` adalah:

| Pengaturan | Nilai bawaan |
| --- | --- |
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `florist_db` |
| Pengguna | `root` |
| Kata sandi | kosong |

Anda dapat mengubah nilai bawaan di file tersebut atau mengatur environment variable `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS`. Jangan mengunggah kredensial database produksi ke repositori publik.

## Menjalankan website

### XAMPP

1. Salin folder proyek ke `C:\xampp\htdocs\mini_projectscnt4`.
2. Jalankan **Apache** dan **MySQL** pada XAMPP.
3. Buat database menggunakan salah satu cara di atas.
4. Buka `http://localhost/mini_projectscnt4/` di browser.

### PHP built-in server

Dari direktori proyek, jalankan:

```bash
php -S 127.0.0.1:8000
```

Lalu buka `http://127.0.0.1:8000`. Pastikan server MySQL/MariaDB sudah berjalan.

## Menggunakan aplikasi

- Tekan **Tambah penjualan** untuk membuat transaksi.
- Tekan ikon pensil pada baris transaksi untuk mengedit data.
- Tekan ikon hapus dan konfirmasi untuk menghapus transaksi.
- Gunakan kolom pencarian atau tanggal untuk memfilter daftar.

Jumlah total dihitung otomatis sebagai jumlah item dikalikan harga per item. Untuk mengatasi kegagalan koneksi, pastikan database dan layanan MySQL berjalan, serta nilai koneksi pada `config/database.php` benar.
