<?php
// NFR-03: backup database (khusus Admin)
require __DIR__ . '/inc/bootstrap.php';
require_admin();
cek_csrf_post();

// Unduh file backup yang sudah tersimpan (hanya nama file yang cocok pola)
if (isset($_GET['unduh'])) {
    $nama = basename((string)$_GET['unduh']);
    $path = BACKUP_DIR . '/' . $nama;
    if (!preg_match('/^backup_[A-Za-z0-9_]+\.sql$/', $nama) || !is_file($path)) {
        http_response_code(404);
        exit('File tidak ditemukan.');
    }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nama = simpan_backup_file();
        flash('ok', "Backup dibuat: $nama");
    } catch (Throwable $e) {
        flash('error', 'Backup gagal dibuat. Pastikan folder backups bisa ditulis.');
    }
    redirect('backup.php');
}

$daftar = daftar_backup();
$terakhir = $daftar[0]['waktu'] ?? null;
$lama = $terakhir ? (int)floor((time() - $terakhir) / 86400) : null;

layout_top('Backup', 'backup');
?>
<div class="judul-bar">
  <div><h1>Backup database</h1><p class="redup">Simpan salinan data pemesanan, pelanggan, dan stok supaya aman kalau komputer bermasalah.</p></div>
</div>

<?php if ($lama === null || $lama >= 7): ?>
  <div class="notif error">
    <?= $lama === null ? 'Belum pernah ada backup.' : "Backup terakhir sudah $lama hari yang lalu." ?> Sebaiknya buat backup sekarang.
  </div>
<?php endif; ?>

<section class="panel">
  <h2>Buat backup sekarang</h2>
  <form method="post">
    <?= csrf_field() ?>
    <button class="btn primer" type="submit">Buat backup</button>
  </form>
  <p class="redup kecil-teks">File disimpan di folder <code>backups</code> pada server. Unduh dan simpan juga di flashdisk atau penyimpanan lain.</p>
  <p class="redup kecil-teks">Backup otomatis berkala: jadwalkan <code>php cron_backup.php</code> lewat Windows Task Scheduler (langkahnya ada di README).</p>
</section>

<section class="panel">
  <h2>Daftar backup</h2>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>File</th><th>Waktu</th><th class="kanan">Ukuran</th><th></th></tr></thead>
      <tbody>
      <?php foreach (array_slice($daftar, 0, 30) as $b): ?>
        <tr>
          <td><code><?= e($b['nama']) ?></code></td>
          <td><?= e(date('d/m/Y H:i', $b['waktu'])) ?></td>
          <td class="kanan"><?= e(number_format($b['ukuran'] / 1024, 1, ',', '.')) ?> KB</td>
          <td><a class="btn ghost kecil" href="backup.php?unduh=<?= e(urlencode($b['nama'])) ?>">Unduh</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$daftar): ?><tr><td colspan="4" class="redup">Belum ada file backup.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php layout_bottom();
