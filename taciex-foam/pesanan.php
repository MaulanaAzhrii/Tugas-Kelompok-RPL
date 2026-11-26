<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
if ((user_login()['role'] ?? '') === 'pembeli') {
    redirect('pesanan_saya.php');
}
$pdo = db();

// ---- Cari & filter (FR-26, FR-27, FR-28) ----
$q       = trim($_GET['q'] ?? '');
$status  = in_array($_GET['status'] ?? '', STATUS_PESANAN, true) ? $_GET['status'] : '';
$bayar   = in_array($_GET['status_bayar'] ?? '', STATUS_BAYAR, true) ? $_GET['status_bayar'] : '';
$dari    = tanggal_valid($_GET['dari'] ?? '') ? $_GET['dari'] : '';
$sampai  = tanggal_valid($_GET['sampai'] ?? '') ? $_GET['sampai'] : '';

$where = [];
$params = [];
if ($q !== '') { $where[] = '(p.no_pesanan LIKE ? OR pl.nama LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($status !== '') { $where[] = 'p.status = ?'; $params[] = $status; }
if ($bayar !== '')  { $where[] = 'p.status_bayar = ?'; $params[] = $bayar; }
if ($dari !== '')   { $where[] = 'p.tanggal >= ?'; $params[] = $dari; }
if ($sampai !== '') { $where[] = 'p.tanggal <= ?'; $params[] = $sampai; }
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$per = 15;
$halaman = max(1, (int)($_GET['hal'] ?? 1));
$st = $pdo->prepare("SELECT COUNT(*) FROM pemesanan p JOIN pelanggan pl ON pl.id = p.pelanggan_id $sqlWhere");
$st->execute($params);
$total = (int)$st->fetchColumn();
$totalHal = max(1, (int)ceil($total / $per));
$halaman = min($halaman, $totalHal);

$st = $pdo->prepare(
    "SELECT p.id, p.no_pesanan, p.tanggal, p.total, p.status, p.status_bayar, pl.nama AS pelanggan
     FROM pemesanan p JOIN pelanggan pl ON pl.id = p.pelanggan_id
     $sqlWhere ORDER BY p.tanggal DESC, p.id DESC LIMIT $per OFFSET " . (($halaman - 1) * $per)
);
$st->execute($params);
$rows = $st->fetchAll();

$filterAktif = $q !== '' || $status !== '' || $bayar !== '' || $dari !== '' || $sampai !== '';

layout_top('Riwayat Pesanan', 'pesanan');
?>
<div class="judul-bar">
  <div><h1>Riwayat pesanan</h1><p class="redup"><?= $total ?> pesanan<?= $filterAktif ? ' sesuai filter' : '' ?>.</p></div>
  <a class="btn primer" href="pesan.php">Pesan baru</a>
</div>

<form method="get" class="panel filter">
  <label>Cari (no. pesanan / nama pelanggan)
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="TF-2026… atau nama">
  </label>
  <label>Status pesanan
    <select name="status">
      <option value="">Semua</option>
      <?php foreach (STATUS_PESANAN as $s): ?><option<?= $s === $status ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Status pembayaran
    <select name="status_bayar">
      <option value="">Semua</option>
      <?php foreach (STATUS_BAYAR as $s): ?><option<?= $s === $bayar ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Dari tanggal <input type="date" name="dari" value="<?= e($dari) ?>"></label>
  <label>Sampai tanggal <input type="date" name="sampai" value="<?= e($sampai) ?>"></label>
  <div class="aksi kiri">
    <button class="btn sekunder" type="submit">Terapkan</button>
    <?php if ($filterAktif): ?><a class="btn ghost" href="pesanan.php">Reset</a><?php endif; ?>
  </div>
</form>

<section class="panel">
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>No. pesanan</th><th>Tanggal</th><th>Pelanggan</th><th class="kanan">Total</th><th>Status</th><th>Pembayaran</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="pesanan_detail.php?id=<?= (int)$r['id'] ?>"><?= e($r['no_pesanan']) ?></a></td>
          <td><?= e(tgl($r['tanggal'])) ?></td>
          <td><?= e($r['pelanggan']) ?></td>
          <td class="kanan"><?= e(rupiah($r['total'])) ?></td>
          <td><?= badge($r['status']) ?></td>
          <td><?= badge($r['status_bayar']) ?></td>
          <td><a class="btn ghost kecil" href="pesanan_detail.php?id=<?= (int)$r['id'] ?>">Detail</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="redup">Tidak ada pesanan<?= $filterAktif ? ' yang cocok dengan filter' : '' ?>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php tampil_pager($halaman, $totalHal, ['q' => $q, 'status' => $status, 'status_bayar' => $bayar, 'dari' => $dari, 'sampai' => $sampai]); ?>
</section>
<?php layout_bottom();
