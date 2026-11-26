<?php
// FR-29 sampai FR-32: laporan (khusus Admin)
require __DIR__ . '/inc/bootstrap.php';
require_admin();
$pdo = db();

$jenis  = ($_GET['jenis'] ?? 'pemesanan') === 'stok' ? 'stok' : 'pemesanan';
$hariIni = date('Y-m-d');
$dari   = tanggal_valid($_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-01');
$sampai = tanggal_valid($_GET['sampai'] ?? '') ? $_GET['sampai'] : $hariIni;
if ($sampai < $dari) { [$dari, $sampai] = [$sampai, $dari]; }
$export = ($_GET['export'] ?? '') === 'csv';

// Cegah CSV formula injection
function csv_aman($v) {
    return (is_string($v) && preg_match('/^[=+\-@\t\r]/', $v)) ? "'" . $v : $v;
}

// ---------- Data ----------
$rows = [];
$ringkas = [];
$perTipe = [];
if ($jenis === 'pemesanan') {
    $st = $pdo->prepare(
        "SELECT p.id, p.no_pesanan, p.tanggal, p.total, p.status, p.status_bayar, pl.nama AS pelanggan,
                COALESCE((SELECT SUM(b.jumlah) FROM pembayaran b WHERE b.pemesanan_id = p.id), 0) AS dibayar
         FROM pemesanan p JOIN pelanggan pl ON pl.id = p.pelanggan_id
         WHERE p.tanggal BETWEEN ? AND ?
         ORDER BY p.tanggal, p.id"
    );
    $st->execute([$dari, $sampai]);
    $rows = $st->fetchAll();

    $aktif = array_filter($rows, fn($r) => $r['status'] !== 'Dibatalkan');
    $nilai = array_sum(array_column($aktif, 'total'));
    $bayar = array_sum(array_column($aktif, 'dibayar'));
    $ringkas = [
        'Jumlah pesanan'    => count($rows),
        'Dibatalkan'        => count($rows) - count($aktif),
        'Nilai pesanan'     => rupiah($nilai),
        'Sudah dibayar'     => rupiah($bayar),
        'Belum terbayar'    => rupiah($nilai - $bayar),
    ];

    // Rekap unit kasur per tipe (pesanan yang tidak dibatalkan)
    $st = $pdo->prepare(
        "SELECT t.kode, t.nama, t.ukuran, SUM(d.jumlah) AS unit, SUM(d.subtotal) AS nilai
         FROM pemesanan_detail d
         JOIN pemesanan p ON p.id = d.pemesanan_id
         JOIN tipe_kasur t ON t.id = d.tipe_kasur_id
         WHERE p.tanggal BETWEEN ? AND ? AND p.status <> 'Dibatalkan'
         GROUP BY t.id, t.kode, t.nama, t.ukuran
         ORDER BY unit DESC"
    );
    $st->execute([$dari, $sampai]);
    $perTipe = $st->fetchAll();
} else {
    $rows = $pdo->query('SELECT * FROM tipe_kasur ORDER BY nama, ukuran')->fetchAll();
    $nilaiStok = 0;
    foreach ($rows as $r) $nilaiStok += (int)$r['harga'] * (int)$r['stok'];
    $ringkas = [
        'Jumlah tipe kasur' => count($rows),
        'Total unit stok'   => array_sum(array_column($rows, 'stok')),
        'Nilai stok'        => rupiah($nilaiStok),
        'Stok menipis/habis' => count(array_filter($rows, fn($r) => (int)$r['stok'] <= STOK_MENIPIS)),
    ];
}

// ---------- Ekspor CSV (dibuka di Excel) ----------
if ($export) {
    $nama = $jenis === 'stok' ? 'laporan-stok-' . $hariIni : "laporan-pemesanan-{$dari}_{$sampai}";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($jenis === 'pemesanan') {
        fputcsv($out, ['No. Pesanan', 'Tanggal', 'Pelanggan', 'Status', 'Status Bayar', 'Total', 'Dibayar', 'Sisa']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['no_pesanan'], $r['tanggal'], csv_aman($r['pelanggan']), $r['status'], $r['status_bayar'],
                           $r['total'], $r['dibayar'], $r['status'] === 'Dibatalkan' ? 0 : $r['total'] - $r['dibayar']]);
        }
    } else {
        fputcsv($out, ['Kode', 'Nama Tipe', 'Ukuran', 'Ketebalan', 'Bahan', 'Harga', 'Stok', 'Nilai Stok']);
        foreach ($rows as $r) {
            fputcsv($out, [csv_aman($r['kode']), csv_aman($r['nama']), csv_aman($r['ukuran']), csv_aman($r['ketebalan']),
                           csv_aman($r['bahan']), $r['harga'], $r['stok'], $r['harga'] * $r['stok']]);
        }
    }
    fclose($out);
    exit;
}

layout_top('Laporan', 'laporan');
$qs = ['jenis' => $jenis, 'dari' => $dari, 'sampai' => $sampai];
?>
<div class="judul-bar">
  <div>
    <h1>Laporan</h1>
    <p class="redup">
      <?= $jenis === 'stok' ? 'Stok tipe kasur per ' . e(tgl($hariIni)) : 'Pemesanan ' . e(tgl($dari)) . ' sampai ' . e(tgl($sampai)) ?>
    </p>
  </div>
  <div class="aksi kiri no-print">
    <button class="btn sekunder" type="button" data-cetak>Cetak / Simpan PDF</button>
    <a class="btn ghost" href="?<?= e(http_build_query($qs + ['export' => 'csv'])) ?>">Unduh Excel (CSV)</a>
  </div>
</div>

<form method="get" class="panel filter no-print">
  <label>Jenis laporan
    <select name="jenis" id="jenis-laporan">
      <option value="pemesanan"<?= $jenis === 'pemesanan' ? ' selected' : '' ?>>Pemesanan / penjualan</option>
      <option value="stok"<?= $jenis === 'stok' ? ' selected' : '' ?>>Stok tipe kasur</option>
    </select>
  </label>
  <label class="tgl-laporan">Dari tanggal <input type="date" name="dari" value="<?= e($dari) ?>"></label>
  <label class="tgl-laporan">Sampai tanggal <input type="date" name="sampai" value="<?= e($sampai) ?>"></label>
  <div class="aksi kiri"><button class="btn primer" type="submit">Tampilkan</button></div>
</form>

<section class="stat-grid kecil">
  <?php foreach ($ringkas as $label => $nilai): ?>
    <div class="stat"><span><?= e($label) ?></span><strong><?= e($nilai) ?></strong></div>
  <?php endforeach; ?>
</section>

<?php if ($jenis === 'pemesanan'): ?>
<section class="panel">
  <h2>Rincian pemesanan</h2>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>No. pesanan</th><th>Tanggal</th><th>Pelanggan</th><th>Status</th><th>Pembayaran</th><th class="kanan">Total</th><th class="kanan">Dibayar</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['no_pesanan']) ?></td><td><?= e(tgl($r['tanggal'])) ?></td><td><?= e($r['pelanggan']) ?></td>
          <td><?= badge($r['status']) ?></td><td><?= badge($r['status_bayar']) ?></td>
          <td class="kanan"><?= e(rupiah($r['total'])) ?></td><td class="kanan"><?= e(rupiah($r['dibayar'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="redup">Tidak ada pemesanan pada periode ini.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php if ($perTipe): ?>
<section class="panel">
  <h2>Rekap per tipe kasur</h2>
  <p class="redup kecil-teks">Tidak termasuk pesanan yang dibatalkan.</p>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Kode</th><th>Tipe kasur</th><th>Ukuran</th><th class="kanan">Unit dipesan</th><th class="kanan">Nilai</th></tr></thead>
      <tbody>
      <?php foreach ($perTipe as $r): ?>
        <tr><td><code><?= e($r['kode']) ?></code></td><td><?= e($r['nama']) ?></td><td><?= e($r['ukuran']) ?></td><td class="kanan"><?= (int)$r['unit'] ?></td><td class="kanan"><?= e(rupiah($r['nilai'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
<?php else: ?>
<section class="panel">
  <h2>Stok tipe kasur</h2>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Kode</th><th>Tipe kasur</th><th>Ukuran</th><th>Bahan</th><th class="kanan">Harga</th><th class="kanan">Stok</th><th class="kanan">Nilai stok</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= e($r['kode']) ?></code></td><td><?= e($r['nama']) ?></td><td><?= e($r['ukuran']) ?></td><td><?= e($r['bahan']) ?></td>
          <td class="kanan"><?= e(rupiah($r['harga'])) ?></td>
          <td class="kanan"><?= (int)$r['stok'] ?><?= (int)$r['stok'] <= STOK_MENIPIS ? ' <span class="badge b-menunggu-konfirmasi">Menipis</span>' : '' ?></td>
          <td class="kanan"><?= e(rupiah($r['harga'] * $r['stok'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
<?php layout_bottom();
