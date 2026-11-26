<?php
require __DIR__ . '/pembeli_inc.php';
$pel = wajib_pembeli();
$pdo  = db();
$user = user_login();

$id = (int)($_GET['id'] ?? 0);

// ====== DETAIL PESANAN (FR-27) ======
if ($id > 0) {
    $p = ambil_pesanan($id);
    // NFR-10: pembeli hanya boleh melihat pesanan miliknya sendiri
    if (!$p || (int)$p['pelanggan_id'] !== (int)$pel['id']) {
        pesan_info('error', 'Pesanan tidak ditemukan.');
        redirect('pesanan_saya.php');
    }
    $detail  = ambil_detail_pesanan($id);
    $bayar   = ambil_pembayaran($id);
    $dibayar = (int)array_sum(array_column($bayar, 'jumlah'));
    $sisa    = max(0, (int)$p['total'] - $dibayar);

    atas_pembeli('Pesanan ' . $p['no_pesanan']);
    ?>
    <div class="judul-bar">
      <div>
        <h1><?= e($p['no_pesanan']) ?></h1>
        <p><?= badge($p['status']) ?> <?= badge($p['status_bayar']) ?></p>
      </div>
      <div class="aksi kiri">
        <a class="btn sekunder" href="bukti.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Cetak bukti pemesanan</a>
        <a class="btn ghost" href="pesanan_saya.php">Kembali</a>
      </div>
    </div>

    <section class="panel">
      <h2>Data pesanan</h2>
      <dl class="rincian">
        <dt>Nama</dt><dd><?= e($p['pelanggan_nama']) ?></dd>
        <dt>Telepon</dt><dd><?= e($p['pelanggan_telepon']) ?></dd>
        <dt>Tanggal pemesanan</dt><dd><?= e(tgl($p['tanggal'])) ?></dd>
        <dt>Alamat pengiriman</dt><dd><?= e($p['alamat_pengiriman']) ?></dd>
        <dt>Metode pembayaran</dt><dd><?= e($p['metode_pembayaran'] ?? '-') ?></dd>
      </dl>
    </section>

    <section class="panel">
      <h2>Kasur yang dipesan</h2>
      <div class="tabel-bungkus">
        <table class="tabel">
          <thead><tr><th>Tipe kasur</th><th>Ukuran</th><th class="kanan">Harga</th><th class="kanan">Jumlah</th><th class="kanan">Subtotal</th></tr></thead>
          <tbody>
          <?php foreach ($detail as $d): ?>
            <tr>
              <td><strong><?= e($d['nama']) ?></strong><br><small class="redup"><?= e($d['kode']) ?> · <?= e($d['bahan']) ?>, <?= e($d['ketebalan']) ?></small></td>
              <td><?= e($d['ukuran']) ?></td>
              <td class="kanan"><?= e(rupiah($d['harga_satuan'])) ?></td>
              <td class="kanan"><?= (int)$d['jumlah'] ?></td>
              <td class="kanan"><?= e(rupiah($d['subtotal'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot><tr><th colspan="4" class="kanan">Total</th><th class="kanan total"><?= e(rupiah($p['total'])) ?></th></tr></tfoot>
        </table>
      </div>
    </section>

    <section class="panel">
      <h2>Pembayaran</h2>
      <div class="stat-grid kecil">
        <div class="stat"><span>Total tagihan</span><strong><?= e(rupiah($p['total'])) ?></strong></div>
        <div class="stat"><span>Sudah dibayar</span><strong><?= e(rupiah($dibayar)) ?></strong></div>
        <div class="stat"><span>Sisa</span><strong><?= e(rupiah($sisa)) ?></strong></div>
      </div>
      <?php if ($bayar): ?>
        <div class="tabel-bungkus">
          <table class="tabel">
            <thead><tr><th>Tanggal</th><th>Metode</th><th class="kanan">Jumlah</th></tr></thead>
            <tbody>
            <?php foreach ($bayar as $b): ?>
              <tr><td><?= e(tgl($b['tanggal'])) ?></td><td><?= e($b['metode']) ?></td><td class="kanan"><?= e(rupiah($b['jumlah'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php elseif ($p['status'] !== 'Dibatalkan'): ?>
        <p class="redup">Belum ada pembayaran tercatat. Pembayaran dicatat oleh pihak toko setelah kamu membayar.</p>
      <?php endif; ?>
    </section>
    <?php
    bawah_pembeli();
    exit;
}

// ====== DAFTAR PESANAN (FR-36) ======
$st = $pdo->prepare('SELECT id, no_pesanan, tanggal, total, status, status_bayar FROM pemesanan WHERE pelanggan_id = ? ORDER BY id DESC');
$st->execute([(int)$pel['id']]);
$rows = $st->fetchAll();

atas_pembeli('Pesanan Saya');
?>
<div class="judul-bar">
  <div>
    <h1>Pesanan saya</h1>
    <p class="redup">Riwayat dan status pesanan kamu.</p>
  </div>
  <div class="aksi kiri">
    <a class="btn ghost" href="katalog.php">Lihat katalog</a>
    <a class="btn primer" href="pesan_pembeli.php">Pesan kasur</a>
  </div>
</div>

<section class="panel">
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>No. pesanan</th><th>Tanggal</th><th class="kanan">Total</th><th>Status</th><th>Pembayaran</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="pesanan_saya.php?id=<?= (int)$r['id'] ?>"><?= e($r['no_pesanan']) ?></a></td>
          <td><?= e(tgl($r['tanggal'])) ?></td>
          <td class="kanan"><?= e(rupiah($r['total'])) ?></td>
          <td><?= badge($r['status']) ?></td>
          <td><?= badge($r['status_bayar']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="redup">Kamu belum punya pesanan. Mulai dari <a href="pesan_pembeli.php">Pesan Kasur</a>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php bawah_pembeli();