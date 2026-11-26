<?php
// Notifikasi untuk pembeli: dibuat otomatis dari status pesanan miliknya
require_once __DIR__ . '/pembeli_inc.php';
$pel = wajib_pembeli();

$st = db()->prepare('SELECT * FROM pemesanan WHERE pelanggan_id = ? ORDER BY id DESC');
$st->execute([(int)$pel['id']]);
$rows = $st->fetchAll();

$notif = [];
foreach ($rows as $r) {
    $no      = $r['no_pesanan'] ?? ('#' . $r['id']);
    $tanggal = $r['updated_at'] ?? $r['created_at'] ?? $r['tanggal'] ?? '';
    $status  = strtolower((string)($r['status'] ?? ''));
    $bayar   = strtolower((string)($r['status_bayar'] ?? $r['pembayaran'] ?? $r['status_pembayaran'] ?? ''));

    if (str_contains($status, 'menunggu')) {
        $notif[] = ['info', $tanggal, "Pesanan $no sudah diterima dan menunggu konfirmasi dari toko."];
    } elseif (str_contains($status, 'proses')) {
        $notif[] = ['info', $tanggal, "Pesanan $no sedang diproses oleh toko."];
    } elseif (str_contains($status, 'kirim')) {
        $notif[] = ['info', $tanggal, "Pesanan $no sedang dalam pengiriman."];
    } elseif (str_contains($status, 'selesai')) {
        $notif[] = ['ok', $tanggal, "Pesanan $no sudah selesai. Terima kasih sudah berbelanja!"];
    } elseif (str_contains($status, 'batal')) {
        $notif[] = ['error', $tanggal, "Pesanan $no dibatalkan."];
    }

    if (str_contains($bayar, 'belum')) {
        $notif[] = ['error', $tanggal, "Pembayaran pesanan $no belum lunas. Segera selesaikan pembayaran ke toko."];
    } elseif (str_contains($bayar, 'lunas')) {
        $notif[] = ['ok', $tanggal, "Pembayaran pesanan $no sudah lunas."];
    }
}

atas_pembeli('Notifikasi');
?>
<h1>Notifikasi</h1>
<p>Info terbaru tentang pesanan kamu.</p>

<?php if (!$notif): ?>
  <div class="notif info">Belum ada notifikasi.</div>
<?php else: foreach ($notif as [$jenis, $tgl, $teks]): ?>
  <div class="notif <?= e($jenis) ?>" style="margin-bottom:10px;">
    <?= e($teks) ?>
    <?php if ($tgl): ?><br><small style="opacity:.7;"><?= e($tgl) ?></small><?php endif; ?>
  </div>
<?php endforeach; endif; ?>
<?php bawah_pembeli(); ?>