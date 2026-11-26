<?php
require __DIR__ . '/inc/bootstrap.php';

if (user_login()) {
    redirect('index.php');
}

$error  = '';
$sukses = false;
$v = ['nama' => '', 'telepon' => '', 'alamat' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Halaman kedaluwarsa. Muat ulang lalu coba lagi.';
    } else {
        $v['nama']     = trim($_POST['nama'] ?? '');
        $v['telepon']  = trim($_POST['telepon'] ?? '');
        $v['alamat']   = trim($_POST['alamat'] ?? '');
        $v['username'] = trim($_POST['username'] ?? '');
        $password      = $_POST['password'] ?? '';
        $konfirmasi    = $_POST['konfirmasi'] ?? '';

        // NFR-04, NFR-09: validasi input sebelum disimpan
        if ($v['nama'] === '' || panjang_teks($v['nama']) > 100) {
            $error = 'Nama wajib diisi (maksimal 100 karakter).';
        } elseif (!preg_match('/^\+?[0-9]{8,15}$/', $v['telepon'])) {
            $error = 'Nomor telepon harus berupa 8 sampai 15 angka.';
        } elseif ($v['alamat'] === '' || panjang_teks($v['alamat']) > 255) {
            $error = 'Alamat wajib diisi (maksimal 255 karakter).';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $v['username'])) {
            $error = 'Username 3 sampai 30 karakter: huruf, angka, titik, strip, atau underscore.';
        } elseif (strlen($password) < 8) {
            $error = 'Password minimal 8 karakter.';
        } elseif ($password !== $konfirmasi) {
            $error = 'Konfirmasi password tidak sama.';
        } else {
            $pdo = db();
            $now = date('Y-m-d H:i:s');
            try {
                // FR-05 dan FR-14: buat akun + data pelanggan sekaligus, saling tertaut
                $pdo->beginTransaction();

                $pdo->prepare(
                    'INSERT INTO users (username, password, nama, role, aktif, created_at)
                     VALUES (?, ?, ?, ?, 1, ?)'
                )->execute([
                    $v['username'],
                    password_hash($password, PASSWORD_DEFAULT),   // NFR-06
                    $v['nama'],
                    'pembeli',
                    $now,
                ]);
                $userId = (int)$pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO pelanggan (user_id, nama, alamat, telepon, created_at)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$userId, $v['nama'], $v['alamat'], $v['telepon'], $now]);

                $pdo->commit();
                $sukses = true;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
                    $error = 'Username sudah dipakai. Coba username lain.';
                } else {
                    $error = pesan_db($e, 'Pendaftaran gagal. Coba lagi.');
                }
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Daftar - <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="halaman-login">
<main class="kotak-login">
  <p class="merek-login"><?= e(APP_NAME) ?></p>
  <h1>Daftar akun pembeli</h1>
  <p class="login-sub">Isi data kamu untuk membuat akun.</p>

  <?php if ($sukses): ?>
    <div class="notif ok" role="status">Pendaftaran berhasil. Silakan masuk dengan akun kamu.</div>
    <a class="btn primer penuh" href="login.php">Ke halaman masuk</a>
  <?php else: ?>
    <?php if ($error): ?><div class="notif error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label>Nama lengkap
        <input name="nama" required maxlength="100" autofocus value="<?= e($v['nama']) ?>">
      </label>
      <label>Nomor telepon
        <input name="telepon" type="tel" required maxlength="16" value="<?= e($v['telepon']) ?>">
      </label>
      <label>Alamat
        <input name="alamat" required maxlength="255" value="<?= e($v['alamat']) ?>">
      </label>
      <label>Username
        <input name="username" required maxlength="30" value="<?= e($v['username']) ?>">
      </label>
      <label>Password
        <input type="password" name="password" required minlength="8">
      </label>
      <label>Ulangi password
        <input type="password" name="konfirmasi" required minlength="8">
      </label>
      <button class="btn primer penuh" type="submit">Daftar</button>
    </form>
    <p class="login-daftar">Sudah punya akun? <a href="login.php">Masuk</a></p>
  <?php endif; ?>
</main>
</body>
</html>
