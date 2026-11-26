<?php
// Fungsi dan tampilan khusus halaman pembeli
require_once __DIR__ . '/inc/bootstrap.php';

const METODE_PEMBELI = ['Tunai', 'Transfer Bank', 'E-Wallet/QRIS'];

function wajib_pembeli(): array {
    require_login();
    $u = user_login();
    if (($u['role'] ?? '') !== 'pembeli') redirect('index.php');
    $st = db()->prepare('SELECT id, nama, alamat, telepon FROM pelanggan WHERE user_id = ?');
    $st->execute([(int)$u['id']]);
    $pel = $st->fetch();
    if (!$pel) exit('Data pelanggan akun ini tidak ditemukan. Hubungi admin toko. <a href="logout.php">Keluar</a>');
    return $pel;
}

function pesan_info(string $jenis, string $teks): void {
    $_SESSION['info_pembeli'] = [$jenis, $teks];
}

function atas_pembeli(string $judul): void {
    $info = $_SESSION['info_pembeli'] ?? null;
    unset($_SESSION['info_pembeli']);

    $hal = basename($_SERVER['PHP_SELF']);
    $u = user_login();
    $menu = [
        'katalog.php'         => 'Beranda',
        'pesan_pembeli.php'   => 'Pesan Kasur',
        'pesanan_saya.php'    => 'Pesanan Saya',
        'riwayat_pesanan.php' => 'Riwayat Pesanan',
        'notifikasi.php'      => 'Notifikasi',
    ];
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
  .bar-pembeli {
    display: flex; align-items: center; gap: 28px;
    padding: 12px 28px; background: #232d54; color: #fff;
  }
  .bar-pembeli .brand { display: flex; align-items: center; gap: 10px; line-height: 1.15; }
  .bar-pembeli .brand-teks { display: flex; flex-direction: column; }
  .bar-pembeli .logo-brand { height: 44px; width: auto; display: block; }
  .bar-pembeli .brand strong { font-family: Georgia, "Times New Roman", serif; font-size: 22px; }
  .bar-pembeli .brand small { font-size: 12px; opacity: .8; }

  .bar-pembeli .menu { display: flex; gap: 6px; flex: 1; flex-wrap: wrap; }
  .bar-pembeli .menu a {
    color: #fff; text-decoration: none;
    padding: 8px 12px; border-radius: 8px; font-weight: 500;
  }
  .bar-pembeli .menu a:hover { background: rgba(255,255,255,.12); }
  .bar-pembeli .menu a.aktif {
    background: rgba(255,255,255,.18);
    box-shadow: inset 0 -2px 0 #d9a441;
  }

  .bar-pembeli .user { display: flex; align-items: center; gap: 10px; }
  .bar-pembeli .user .nama { font-weight: 600; }
  .bar-pembeli .user .role { font-size: 13px; opacity: .75; }
  .bar-pembeli .user a {
    color: #fff; text-decoration: none;
    border: 1px solid rgba(255,255,255,.6);
    padding: 6px 14px; border-radius: 8px; font-weight: 600;
  }
  .bar-pembeli .user a:hover { background: rgba(255,255,255,.12); }

  .isi-pembeli { max-width: 1000px; margin: 0 auto; padding: 16px; }

  @media (max-width: 700px) {
    .bar-pembeli { flex-wrap: wrap; gap: 12px; padding: 12px 16px; }
    .bar-pembeli .menu { order: 3; flex-basis: 100%; }
  }
</style>
</head>
<body>
<nav class="bar-pembeli">
  <div class="brand">
    <img src="assets/logo.png" alt="Logo" class="logo-brand" height="44">
    <div class="brand-teks">
      <strong><?= e(APP_NAME) ?></strong>
      <small>Pemesanan Kasur</small>
    </div>
  </div>
  <div class="menu">
    <?php foreach ($menu as $file => $label): ?>
      <a href="<?= $file ?>" class="<?= $hal === $file ? 'aktif' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="user">
    <span class="nama"><?= e($u['nama'] ?? $u['username'] ?? '') ?></span>
    <span class="role">pembeli</span>
    <a href="logout.php">Keluar</a>
  </div>
</nav>
<main class="isi-pembeli">
<?php if ($info): ?><div class="notif <?= e($info[0]) ?>"><?= e($info[1]) ?></div><?php endif;
}

function bawah_pembeli(): void {
    echo "</main>\n</body>\n</html>\n";
}