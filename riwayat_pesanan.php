<?php
// Riwayat pesanan milik pembeli yang sedang login
require_once __DIR__ . '/pembeli_inc.php';
$pel = wajib_pembeli();

$st = db()->prepare('SELECT * FROM pemesanan WHERE pelanggan_id = ? ORDER BY id DESC');
$st->execute([(int)$pel['id']]);
$rows = array_values(array_filter($st->fetchAll(), function ($r) {
    $s = strtolower((string)($r['status'] ?? ''));
    return str_contains($s, 'selesai') || str_contains($s, 'batal');
}));

function rp_riwayat($n): string {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

atas_pembeli('Riwayat Pesanan');
?>
<h1>Riwayat Pesanan</h1>
<p>Semua pesanan yang pernah kamu buat di Toko Kasur Taciex Foam.</p>

<div class="card" style="overflow-x:auto;">
  <table style="width:100%; border-collapse:collapse;">
    <thead>
      <tr style="text-align:left; border-bottom:2px solid #e5dfd3;">
        <th style="padding:10px;">No. pesanan</th>
        <th style="padding:10px;">Tanggal</th>
        <th style="padding:10px; text-align:right;">Total</th>
        <th style="padding:10px;">Status</th>
        <th style="padding:10px;">Pembayaran</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr>
        <td colspan="5" style="padding:14px;">
          Belum ada pesanan. Mulai dari menu <a href="pesan_pembeli.php">Pesan Kasur</a>.
        </td>
      </tr>
    <?php else: foreach ($rows as $r):
        $no      = $r['no_pesanan'] ?? $r['kode'] ?? ('#' . $r['id']);
        $tanggal = $r['tanggal'] ?? $r['created_at'] ?? '-';
        $total   = $r['total'] ?? $r['total_harga'] ?? 0;
        $status  = $r['status'] ?? '-';
        $bayar   = $r['status_bayar'] ?? $r['pembayaran'] ?? $r['status_pembayaran'] ?? '-';
    ?>
      <tr style="border-bottom:1px solid #eee;">
        <td style="padding:10px;"><?= e($no) ?></td>
        <td style="padding:10px;"><?= e($tanggal) ?></td>
        <td style="padding:10px; text-align:right;"><?= e(rp_riwayat($total)) ?></td>
        <td style="padding:10px;"><?= e($status) ?></td>
        <td style="padding:10px;"><?= e($bayar) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php bawah_pembeli(); ?>