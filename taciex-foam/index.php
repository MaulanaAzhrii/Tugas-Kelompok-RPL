<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
if ((user_login()['role'] ?? '') === 'pembeli') {
    redirect('katalog.php');
}
$pdo = db();

$menunggu  = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status = 'Menunggu Konfirmasi'")->fetchColumn();
$belumBayar = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_bayar = 'Belum Bayar' AND status <> 'Dibatalkan'")->fetchColumn();
$hariIni   = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE tanggal = CURDATE()")->fetchColumn();
$menipisSt = $pdo->prepare('SELECT COUNT(*) FROM tipe_kasur WHERE stok <= ?');
$menipisSt->execute([STOK_MENIPIS]);
$menipis = (int)$menipisSt->fetchColumn();

$terbaru = $pdo->query(
    "SELECT p.id, p.no_pesanan, p.tanggal, p.total, p.status, p.status_bayar, pl.nama AS pelanggan
     FROM pemesanan p JOIN pelanggan pl ON pl.id = p.pelanggan_id
     ORDER BY p.id DESC LIMIT 6"
)->fetchAll();

layout_top('Beranda', 'index');
?>
<div class="judul-bar">
  <div>
    <h1>Halo, <?= e(user_login()['nama']) ?></h1>
    <p class="redup">Ringkasan pemesanan Toko Kasur Taciex Foam.</p>
  </div>
  <a class="btn primer" href="pesan.php">Buat pesanan baru</a>
</div>

<section class="stat-grid" aria-label="Ringkasan">
  <a class="stat" href="pesanan.php?status=Menunggu+Konfirmasi"><span>Menunggu konfirmasi</span><strong><?= $menunggu ?></strong></a>
  <a class="stat" href="pesanan.php?status_bayar=Belum+Bayar"><span>Belum lunas</span><strong><?= $belumBayar ?></strong></a>
  <a class="stat" href="pesanan.php?dari=<?= date('Y-m-d') ?>&amp;sampai=<?= date('Y-m-d') ?>"><span>Pesanan hari ini</span><strong><?= $hariIni ?></strong></a>
  <a class="stat<?= $menipis ? ' waspada' : '' ?>" href="tipe_kasur.php"><span>Tipe kasur stok menipis</span><strong><?= $menipis ?></strong></a>
</section>

<section class="panel">
  <h2>Pesanan terbaru</h2>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>No. pesanan</th><th>Tanggal</th><th>Pelanggan</th><th class="kanan">Total</th><th>Status</th><th>Pembayaran</th></tr></thead>
      <tbody>
      <?php foreach ($terbaru as $r): ?>
        <tr>
          <td><a href="pesanan_detail.php?id=<?= (int)$r['id'] ?>"><?= e($r['no_pesanan']) ?></a></td>
          <td><?= e(tgl($r['tanggal'])) ?></td>
          <td><?= e($r['pelanggan']) ?></td>
          <td class="kanan"><?= e(rupiah($r['total'])) ?></td>
          <td><?= badge($r['status']) ?></td>
          <td><?= badge($r['status_bayar']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$terbaru): ?>
        <tr><td colspan="6" class="redup">Belum ada pesanan. Mulai dari menu <a href="pesan.php">Pesan Baru</a>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php layout_bottom();
