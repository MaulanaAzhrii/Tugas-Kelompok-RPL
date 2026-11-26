# Aplikasi Pemesanan Kasur - Toko Kasur Taciex Foam

Aplikasi web (PHP + MySQL) sesuai dokumen SRS "Perancangan dan Implementasi Aplikasi Pemesanan Kasur Berbasis Web pada Toko Kasur Taciex Foam".

## Cara pasang (XAMPP, Windows)
1. Install XAMPP (PHP 7.4 atau lebih baru), lalu jalankan **Apache** dan **MySQL** di XAMPP Control Panel.
2. Salin folder `taciex-foam` ke `C:\xampp\htdocs\`.
3. Buka `http://localhost/phpmyadmin` -> tab **Import** -> pilih `database.sql` -> **Go**.
4. Kalau MySQL kamu punya password, ubah `DB_PASS` di `inc/config.php`.
5. Buka `http://localhost/taciex-foam/` lalu login:

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Kasir | `kasir` | `kasir123` |

**Ganti kedua password lewat menu Akun** sebelum dipakai sungguhan. Data tipe kasur dan pelanggan bawaan hanya contoh (harga juga contoh), ganti dengan data asli toko.

## Alur pemakaian
1. **Admin** mengisi data di menu *Tipe Kasur* (kode, nama, ukuran, ketebalan, bahan, harga, stok).
2. **Kasir/Admin** buka *Pesan Baru*: pilih pelanggan (atau isi pelanggan baru), pilih tipe kasur dan jumlah (bisa lebih dari satu baris), isi tanggal dan alamat pengiriman. Total dihitung otomatis.
3. Klik *Lihat ringkasan pesanan*, periksa, lalu *Simpan pesanan*. Status awal: **Menunggu Konfirmasi**.
4. Di *Detail pesanan*: **Konfirmasi pesanan** (stok berkurang otomatis) -> **Tandai Dikirim** -> **Tandai Selesai**. Pesanan bisa **Dibatalkan** sebelum dikirim (kalau sudah dikonfirmasi, stok dikembalikan).
5. Catat pembayaran di *Detail pesanan* (Tunai / Transfer Bank / E-Wallet/QRIS). Boleh dicicil; status jadi **Lunas** otomatis kalau sudah terbayar penuh.
6. *Cetak bukti pemesanan* dari halaman detail.
7. *Riwayat Pesanan*: cari (no. pesanan / nama pelanggan) dan filter (status pesanan, status pembayaran, tanggal).
8. **Admin**: menu *Laporan*, *Pengguna* (akun Admin/Kasir), dan *Backup*.

## Kesesuaian dengan SRS
| Fitur SRS | Requirement | File |
|---|---|---|
| Login & hak akses | FR-01 s.d. FR-04, NFR-07, NFR-08, NFR-14 | `login.php`, `inc/auth.php`, `akun.php`, `users.php` |
| Tipe kasur | FR-05 s.d. FR-08 | `tipe_kasur.php` |
| Pelanggan | FR-09 s.d. FR-11 | `pelanggan.php` |
| Pemesanan | FR-12 s.d. FR-16, NFR-15, NFR-16 | `pesan.php` |
| Konfirmasi pemesanan | FR-17 s.d. FR-21 | `pesan.php` (ringkasan), `pesanan_detail.php`, `bukti.php` |
| Metode pembayaran | FR-22 s.d. FR-25 | `pesanan_detail.php`, `bukti.php` |
| Riwayat & pencarian | FR-26 s.d. FR-28 | `pesanan.php` |
| Laporan | FR-29 s.d. FR-32 | `laporan.php` |
| Backup berkala | NFR-03 | `backup.php`, `cron_backup.php` |
| Password hash, validasi input, transaksi database | NFR-04, NFR-05, NFR-06, NFR-09 | `inc/helpers.php`, semua halaman |

Catatan implementasi:
- **PDF**: klik *Cetak / Simpan PDF* di halaman laporan, lalu pilih printer "Save as PDF" / "Simpan sebagai PDF" di browser.
- **Excel**: tombol *Unduh Excel (CSV)* menghasilkan file .csv yang langsung terbuka di Excel.
- **Keamanan**: password di-hash (`password_hash`), semua query memakai prepared statement, semua form memakai token CSRF, output di-escape (anti XSS).
- **Stok**: dicek saat pesanan dibuat (FR-16), dikurangi saat pesanan dikonfirmasi (FR-20), dan dikunci di database supaya stok tidak pernah negatif atau tidak sinkron (NFR-02, NFR-16).
- **Pesanan yang dibatalkan setelah ada pembayaran**: aplikasi hanya memberi peringatan; pengembalian uang ke pelanggan dilakukan manual di luar aplikasi.

## Backup otomatis (NFR-03)
Backup manual: menu **Backup** -> *Buat backup* (file tersimpan di folder `backups`, bisa diunduh).

Backup terjadwal di Windows (jalankan CMD sebagai Administrator, sesuaikan lokasi folder):
```
schtasks /create /sc daily /st 20:00 /tn "Backup Taciex Foam" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\taciex-foam\cron_backup.php"
```
Skrip menyimpan maksimal 30 backup terbaru. Untuk memulihkan: Import file `.sql` di phpMyAdmin.

## Pengaturan
`inc/config.php`: koneksi database, `STOK_MENIPIS` (batas tanda "Menipis", bawaan 5).

## Struktur file
```
index.php            beranda (ringkasan)
login.php logout.php akun.php
tipe_kasur.php pelanggan.php
pesan.php            form pesanan + ringkasan + simpan
pesanan.php          riwayat, cari, filter
pesanan_detail.php   status, pembayaran
bukti.php            bukti pemesanan (cetak)
laporan.php users.php backup.php   (khusus Admin)
cron_backup.php      backup terjadwal (command line)
inc/                 config, koneksi, auth, fungsi, layout
assets/              style.css, app.js
database.sql         struktur tabel + data awal
```

Tabel: `users`, `tipe_kasur`, `pelanggan`, `pemesanan`, `pemesanan_detail`, `pembayaran`.

## Kalau ada masalah
- *"Tidak bisa terhubung ke database"*: cek MySQL sudah **Start** di XAMPP dan `DB_PASS` di `inc/config.php`.
- *Halaman kosong/error*: buka `C:\xampp\apache\logs\error.log`.
- *Tampilan tanpa font khusus*: font dimuat dari Google Fonts, kalau offline tampilan tetap jalan dengan font bawaan.
