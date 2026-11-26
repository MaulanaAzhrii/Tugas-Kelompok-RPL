-- Database Aplikasi Pemesanan Kasur - Toko Kasur Taciex Foam
-- Import lewat phpMyAdmin (tab Import), atau: mysql -u root < database.sql

CREATE DATABASE IF NOT EXISTS db_taciex_foam CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_taciex_foam;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama VARCHAR(100) NOT NULL,
  role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tipe_kasur (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  ukuran VARCHAR(30) NOT NULL,
  ketebalan VARCHAR(30) NOT NULL,
  bahan VARCHAR(80) NOT NULL,
  harga INT NOT NULL,
  stok INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT chk_harga CHECK (harga >= 0),
  CONSTRAINT chk_stok CHECK (stok >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pelanggan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  alamat VARCHAR(255) NOT NULL,
  telepon VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_pel_nama (nama)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pemesanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_pesanan VARCHAR(30) NOT NULL UNIQUE,
  pelanggan_id INT NOT NULL,
  tanggal DATE NOT NULL,
  alamat_pengiriman VARCHAR(255) NOT NULL,
  total INT NOT NULL,
  status ENUM('Menunggu Konfirmasi','Dikonfirmasi','Dikirim','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu Konfirmasi',
  status_bayar ENUM('Belum Bayar','Lunas') NOT NULL DEFAULT 'Belum Bayar',
  dibuat_oleh INT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_tanggal (tanggal),
  INDEX idx_status (status),
  INDEX idx_bayar (status_bayar),
  CONSTRAINT fk_pesan_pelanggan FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pesan_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pemesanan_detail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pemesanan_id INT NOT NULL,
  tipe_kasur_id INT NOT NULL,
  harga_satuan INT NOT NULL,          -- harga saat pesanan dibuat
  jumlah INT NOT NULL,
  subtotal INT NOT NULL,
  CONSTRAINT fk_det_pesan FOREIGN KEY (pemesanan_id) REFERENCES pemesanan(id) ON DELETE CASCADE,
  CONSTRAINT fk_det_tipe FOREIGN KEY (tipe_kasur_id) REFERENCES tipe_kasur(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pembayaran (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pemesanan_id INT NOT NULL,
  metode ENUM('Tunai','Transfer Bank','E-Wallet/QRIS') NOT NULL,
  jumlah INT NOT NULL,
  tanggal DATE NOT NULL,
  dicatat_oleh INT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_bayar_pesan FOREIGN KEY (pemesanan_id) REFERENCES pemesanan(id) ON DELETE CASCADE,
  CONSTRAINT fk_bayar_user FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===== Data awal =====
-- Login awal: admin / admin123  dan  kasir / kasir123  (SEGERA ganti lewat menu Akun)
INSERT INTO users (username, password, nama, role, created_at) VALUES
('admin', '$2y$10$9RtJPSq3Hz/Nkx8Dm5BoUeTlTfV7dZQnsPvkkufgfhwC2VK0cV.Ne', 'Administrator', 'admin', NOW()),
('kasir', '$2y$10$AmmXjEjF1MGVhNxKD7jBBOhAFRxEASoOVjWn6ZOK05J7yTRntntAK', 'Kasir 1', 'kasir', NOW());

-- CONTOH data tipe kasur dan pelanggan (harga hanya contoh, ganti dengan data asli toko)
INSERT INTO tipe_kasur (kode, nama, ukuran, ketebalan, bahan, harga, stok, created_at) VALUES
('KB-090', 'Kasur Busa Standar', '90 x 200 cm', '15 cm', 'Busa (foam)', 850000, 10, NOW()),
('KB-120', 'Kasur Busa Standar', '120 x 200 cm', '15 cm', 'Busa (foam)', 1150000, 8, NOW()),
('KL-100', 'Kasur Lipat', '100 x 200 cm', '10 cm', 'Busa (foam)', 650000, 12, NOW()),
('SB-160', 'Kasur Springbed', '160 x 200 cm', '25 cm', 'Per (spring) dan busa', 3200000, 4, NOW());

INSERT INTO pelanggan (nama, alamat, telepon, created_at) VALUES
('Budi Santoso', 'Jl. Contoh No. 12, Cipayung, Jakarta Timur', '081234567890', NOW()),
('Siti Rahma', 'Jl. Melati No. 5, Ciracas, Jakarta Timur', '081298765432', NOW());
