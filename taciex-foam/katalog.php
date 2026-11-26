<?php
require __DIR__ . '/pembeli_inc.php';
wajib_pembeli();
$pdo = db();

// FR-10: pembeli melihat tipe kasur, harga, dan ketersediaan stok
$daftar = $pdo->query('SELECT * FROM tipe_kasur ORDER BY nama, ukuran')->fetchAll();

atas_pembeli('Katalog Kasur');
?>
<div class="judul-bar">
  <div>
    <h1>Halo, <?= e(user_login()['nama']) ?></h1>
    <p class="redup">Daftar tipe kasur Toko Kasur Taciex Foam beserta harga dan stok.</p>
  </div>
  <div class="aksi kiri">
    <a class="btn ghost" href="pesanan_saya.php">Pesanan saya</a>
    <a class="btn primer" href="pesan_pembeli.php">Pesan kasur</a>
  </div>
</div>

<section class="panel">
  <h2>Katalog kasur</h2>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Kode</th><th>Nama</th><th>Ukuran</th><th>Tebal</th><th>Bahan</th><th class="kanan">Harga</th><th>Stok</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($daftar as $t): ?>
        <tr>
          <td><?= e($t['kode']) ?></td>
          <td><?= e($t['nama']) ?></td>
          <td><?= e($t['ukuran']) ?></td>
          <td><?= e($t['ketebalan']) ?></td>
          <td><?= e($t['bahan']) ?></td>
          <td class="kanan"><?= e(rupiah($t['harga'])) ?></td>
          <td><?= (int)$t['stok'] > 0 ? (int)$t['stok'] . ' tersedia' : 'Habis' ?></td>
          <td><?php if ((int)$t['stok'] > 0): ?><a class="btn primer kecil" href="pesan_pembeli.php?tipe=<?= (int)$t['id'] ?>">Pesan</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$daftar): ?>
        <tr><td colspan="8" class="redup">Belum ada tipe kasur.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php bawah_pembeli();