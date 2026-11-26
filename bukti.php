<?php
// FR-21 & FR-25: bukti pemesanan yang bisa dicetak, lengkap dengan rincian pembayaran
require __DIR__ . '/inc/bootstrap.php';
require_login();

$p = ambil_pesanan((int)($_GET['id'] ?? 0));
if (!$p) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}
// Pembeli hanya boleh mencetak bukti pesanannya sendiri
if ((user_login()['role'] ?? '') === 'pembeli') {
    $cek = db()->prepare('SELECT id FROM pelanggan WHERE user_id = ?');
    $cek->execute([(int)user_login()['id']]);
    if ((int)$p['pelanggan_id'] !== (int)$cek->fetchColumn()) {
        http_response_code(404);
        exit('Pesanan tidak ditemukan.');
    }
}
$detail = ambil_detail_pesanan((int)$p['id']);
$bayar  = ambil_pembayaran((int)$p['id']);
$dibayar = array_sum(array_column($bayar, 'jumlah'));
$sisa = max(0, (int)$p['total'] - (int)$dibayar);
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bukti <?= e($p['no_pesanan']) ?></title>
<style>
  body { font: 14px/1.5 "Segoe UI", Arial, sans-serif; color: #111; background: #fff; margin: 0; padding: 24px; }
  .bukti { max-width: 720px; margin: 0 auto; }
  h1 { font-size: 20px; margin: 0; }
  .sub { color: #555; margin: 0 0 14px; }
  hr { border: 0; border-top: 1px solid #999; margin: 14px 0; }
  table { width: 100%; border-collapse: collapse; margin: 6px 0 12px; }
  th, td { text-align: left; padding: 5px 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
  th { font-size: 12px; text-transform: none; color: #444; }
  .kanan { text-align: right; }
  .info { display: grid; grid-template-columns: 150px 1fr; gap: 2px 12px; margin: 0; }
  .info dt { color: #555; } .info dd { margin: 0; }
  .total td, .total th { font-weight: 700; border-top: 2px solid #111; }
  button { display: block; margin: 20px auto 0; padding: 8px 20px; font: inherit; cursor: pointer; }
  @media print { button { display: none; } body { padding: 0; } }
</style>
</head>
<body>
<div class="bukti">
  <h1>Toko Kasur Taciex Foam</h1>
  <p class="sub">Bukti pemesanan</p>
  <hr>
  <dl class="info">
    <dt>No. pesanan</dt><dd><strong><?= e($p['no_pesanan']) ?></strong></dd>
    <dt>Tanggal</dt><dd><?= e(tgl($p['tanggal'])) ?></dd>
    <dt>Pemesan</dt><dd><?= e($p['pelanggan_nama']) ?> (<?= e($p['pelanggan_telepon']) ?>)</dd>
    <dt>Alamat pengiriman</dt><dd><?= e($p['alamat_pengiriman']) ?></dd>
    <dt>Status pesanan</dt><dd><?= e($p['status']) ?></dd>
    <dt>Status pembayaran</dt><dd><?= e($p['status_bayar']) ?></dd>
  </dl>
  <hr>
  <table>
    <thead><tr><th>Tipe kasur</th><th>Ukuran</th><th class="kanan">Harga</th><th class="kanan">Jml</th><th class="kanan">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($detail as $d): ?>
      <tr><td><?= e($d['nama']) ?> <small>(<?= e($d['kode']) ?>)</small></td><td><?= e($d['ukuran']) ?></td><td class="kanan"><?= e(rupiah($d['harga_satuan'])) ?></td><td class="kanan"><?= (int)$d['jumlah'] ?></td><td class="kanan"><?= e(rupiah($d['subtotal'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="total"><th colspan="4" class="kanan">Total</th><th class="kanan"><?= e(rupiah($p['total'])) ?></th></tr></tfoot>
  </table>

  <strong>Rincian pembayaran</strong>
  <table>
    <thead><tr><th>Tanggal</th><th>Metode</th><th class="kanan">Jumlah</th></tr></thead>
    <tbody>
    <?php foreach ($bayar as $b): ?>
      <tr><td><?= e(tgl($b['tanggal'])) ?></td><td><?= e($b['metode']) ?></td><td class="kanan"><?= e(rupiah($b['jumlah'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$bayar): ?><tr><td colspan="3">Belum ada pembayaran.</td></tr><?php endif; ?>
    </tbody>
    <tfoot>
      <tr><th colspan="2" class="kanan">Sudah dibayar</th><th class="kanan"><?= e(rupiah($dibayar)) ?></th></tr>
      <tr><th colspan="2" class="kanan">Sisa tagihan</th><th class="kanan"><?= e(rupiah($sisa)) ?></th></tr>
    </tfoot>
  </table>
  <p class="sub">Dicatat oleh: <?= e($p['dibuat_nama'] ?? '-') ?> · Dicetak <?= date('d/m/Y H:i') ?></p>
  <button onclick="window.print()">Cetak</button>
</div>
</body>
</html>
