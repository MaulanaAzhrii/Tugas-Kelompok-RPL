<?php
// Backup otomatis lewat command line (dijadwalkan dengan Windows Task Scheduler / cron).
// Contoh: C:\xampp\php\php.exe C:\xampp\htdocs\taciex-foam\cron_backup.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya bisa dijalankan lewat command line.');
}
require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';

try {
    $nama = simpan_backup_file();
    echo "Backup dibuat: $nama\n";
    // simpan maksimal 30 file terbaru
    foreach (array_slice(daftar_backup(), 30) as $lama) {
        @unlink(BACKUP_DIR . '/' . $lama['nama']);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Backup gagal: " . $e->getMessage() . "\n");
    exit(1);
}
