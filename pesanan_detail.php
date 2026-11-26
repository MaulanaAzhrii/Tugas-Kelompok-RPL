<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
if ((user_login()['role'] ?? '') === 'pembeli') {
    redirect('pesanan_saya.php');
}
cek_csrf_post();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

// ---- Aksi: ubah status / catat pembayaran ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $aksi = $_POST['aksi'] ?? '';
        if ($aksi === 'status') {
            $baru = $_POST['ke'] ?? '';
            ubah_status_pesanan($id, $baru);
            flash('ok', "Status pesanan diubah menjadi $baru.");
        } elseif ($aksi === 'bayar') {
            catat_pembayaran($id, $_POST['metode'] ?? '', (int)($_POST['jumlah'] ?? 0), trim($_POST['tanggal'] ?? ''), (int)user_login()['id']);
            flash('ok', 'Pembayaran dicatat.');
        }
    } catch (Throwable $e) {
        flash('error', pesan_db($e, 'Aksi gagal diproses. Coba lagi.'));
    }
    redirect('pesanan_detail.php?id=' . $id);
}

$p = ambil_pesanan($id);
if (!$p) {
    http_response_code(404);
    flash('error', 'Pesanan tidak ditemukan.');
    redirect('pesanan.php');
}
$detail = ambil_detail_pesanan($id);
$bayar  = ambil_pembayaran($id);
$dibayar = array_sum(array_column($bayar, 'jumlah'));
$sisa = max(0, (int)$p['total'] - (int)$dibayar);
$transisi = TRANSISI[$p['status']] ?? [];

layout_top('Pesanan ' . $p['no_pesanan'], 'pesanan');
?>
<div class="judul-bar">
  <div>
    <h1><?= e($p['no_pesanan']) ?></h1>
    <p><?= badge($p['status']) ?> <?= badge($p['status_bayar']) ?></p>
  </div>
  <div class="aksi kiri">
    <a class="btn sekunder" href="bukti.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Cetak bukti pemesanan</a>
    <a class="btn ghost" href="pesanan.php">Kembali</a>
  </div>
</div>

<div class="dua-kolom">
  <section class="panel">
    <h2>Pemesan</h2>
    <dl class="rincian">
      <dt>Nama</dt><dd><?= e($p['pelanggan_nama']) ?></dd>
      <dt>Telepon</dt><dd><?= e($p['pelanggan_telepon']) ?></dd>
      <dt>Tanggal pemesanan</dt><dd><?= e(tgl($p['tanggal'])) ?></dd>
      <dt>Alamat pengiriman</dt><dd><?= e($p['alamat_pengiriman']) ?></dd>
      <dt>Metode pilihan</dt><dd><?= e($p['metode_pembayaran'] ?? '-') ?></dd>
      <dt>Dicatat oleh</dt><dd><?= e($p['dibuat_nama'] ?? '-') ?></dd>
    </dl>
  </section>

  <section class="panel">
    <h2>Status pesanan</h2>
    <?php if ($transisi): ?>
      <p class="redup">Ubah status pesanan:</p>
      <div class="aksi kiri">
        <?php foreach ($transisi as $ke): ?>
          <?php
            $pesanBatal = '';
            if ($ke === 'Dibatalkan') {
                $pesanBatal = 'Batalkan pesanan ini?'
                    . ($p['status'] === 'Dikonfirmasi' ? ' Stok akan dikembalikan.' : '')
                    . ($dibayar > 0 ? ' Pembayaran ' . rupiah($dibayar) . ' sudah tercatat dan perlu dikembalikan ke pelanggan.' : '');
            }
          ?>
          <form method="post" <?= $pesanBatal !== '' ? 'data-konfirmasi="' . e($pesanBatal) . '"' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="status">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="ke" value="<?= e($ke) ?>">
            <button class="btn <?= $ke === 'Dibatalkan' ? 'bahaya' : 'primer' ?>" type="submit"><?= $ke === 'Dikonfirmasi' ? 'Konfirmasi pesanan' : e($ke === 'Dibatalkan' ? 'Batalkan pesanan' : 'Tandai ' . $ke) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
      <?php if ($p['status'] === 'Menunggu Konfirmasi'): ?>
        <p class="redup kecil-teks">Stok kasur berkurang otomatis saat pesanan dikonfirmasi.</p>
      <?php endif; ?>
    <?php else: ?>
      <p class="redup">Pesanan berstatus <?= e($p['status']) ?> dan tidak bisa diubah lagi.</p>
    <?php endif; ?>
  </section>
</div>

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
    <div class="stat<?= $sisa > 0 && $p['status'] !== 'Dibatalkan' ? ' waspada' : '' ?>"><span>Sisa</span><strong><?= e(rupiah($sisa)) ?></strong></div>
  </div>

  <?php if ($bayar): ?>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Tanggal</th><th>Metode</th><th class="kanan">Jumlah</th><th>Dicatat oleh</th></tr></thead>
      <tbody>
      <?php foreach ($bayar as $b): ?>
        <tr><td><?= e(tgl($b['tanggal'])) ?></td><td><?= e($b['metode']) ?></td><td class="kanan"><?= e(rupiah($b['jumlah'])) ?></td><td><?= e($b['pencatat'] ?? '-') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="redup">Belum ada pembayaran tercatat.</p>
  <?php endif; ?>

  <?php if ($p['status'] !== 'Dibatalkan' && $sisa > 0): ?>
  <form method="post" class="grid-form form-bayar">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="bayar">
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <h3>Catat pembayaran</h3>
    <label>Metode pembayaran
      <select name="metode" required>
        <?php foreach (METODE_BAYAR as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Jumlah dibayar (Rp) <input type="number" name="jumlah" min="1" max="<?= (int)$sisa ?>" step="1" value="<?= (int)$sisa ?>" required></label>
    <label>Tanggal pembayaran <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></label>
    <div class="aksi"><button class="btn primer" type="submit">Simpan pembayaran</button></div>
  </form>
  <?php endif; ?>
</section>
<?php layout_bottom();
