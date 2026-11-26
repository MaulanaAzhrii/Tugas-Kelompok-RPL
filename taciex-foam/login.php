<?php
require __DIR__ . '/inc/bootstrap.php';

if (user_login()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Halaman kedaluwarsa. Muat ulang lalu coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        try {
            // FR-02: verifikasi data login
            $st = db()->prepare('SELECT id, username, password, nama, role FROM users WHERE username = ? AND aktif = 1');
            $st->execute([$username]);
            $u = $st->fetch();
            if ($u && password_verify($password, $u['password'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'nama' => $u['nama'], 'role' => $u['role']];
                redirect('index.php');
            }
            usleep(400000);   // perlambat tebak-tebakan password
            $error = 'Username atau password salah.';   // FR-03
        } catch (Throwable $e) {
            $error = 'Tidak bisa terhubung ke database. Cek inc/config.php dan pastikan MySQL menyala.';
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk - <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<style>
  body.login-baru {
    margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 32px 24px; box-sizing: border-box;
    background: linear-gradient(135deg, #f8f3e8 0%, #efe6d2 100%);
    font-family: "Figtree", system-ui, sans-serif; color: #26221c;
  }
  .login-baru .lb-wrap {
    width: 100%; max-width: 1040px;
    display: grid; grid-template-columns: 1.15fr 1fr; gap: 56px; align-items: center;
  }

  /* ---- kolom kiri ---- */
  .login-baru .lb-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
  .login-baru .lb-brand img { height: 40px; width: auto; display: block; }
  .login-baru .lb-brand strong { font-family: "Fraunces", Georgia, serif; font-size: 18px; color: #2d3a6b; }
  .login-baru .lb-pill {
    display: inline-block; background: #fff; color: #c98a1d; border: 1px solid #efe3c8;
    font-size: 11px; font-weight: 600; letter-spacing: .06em;
    padding: 6px 14px; border-radius: 999px; margin-bottom: 20px;
  }
  .login-baru h1.lb-judul {
    font-family: "Fraunces", Georgia, serif; font-weight: 700;
    font-size: clamp(32px, 4.4vw, 46px); line-height: 1.12; margin: 0 0 16px; color: #26221c;
  }
  .login-baru h1.lb-judul span { color: #2d3a6b; display: block; }
  .login-baru .lb-sub { color: #5d564a; font-size: 15px; line-height: 1.55; margin: 0 0 24px; max-width: 440px; }

  .login-baru .lb-ukuran { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; max-width: 440px; margin-bottom: 26px; }
  .login-baru .lb-ukuran div {
    background: rgba(255,255,255,.85); border: 1px solid #efe6d2; border-radius: 12px;
    padding: 14px 10px; text-align: center;
  }
  .login-baru .lb-ukuran svg { display: block; margin: 0 auto 8px; }
  .login-baru .lb-ukuran b { display: block; font-size: 14px; }
  .login-baru .lb-ukuran small { color: #7a7263; font-size: 12px; }

  .login-baru .lb-langkah { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; }
  .login-baru .lb-langkah li { display: flex; gap: 12px; align-items: flex-start; }
  .login-baru .lb-langkah .no {
    flex: none; width: 24px; height: 24px; border-radius: 50%;
    background: #2d3a6b; color: #fff; font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
  }
  .login-baru .lb-langkah b { display: block; font-size: 14px; }
  .login-baru .lb-langkah small { color: #6b6456; font-size: 13px; }

  /* ---- kartu login ---- */
  .login-baru .lb-kartu {
    background: #fffdf8; border: 1px solid #efe6d2; border-radius: 20px;
    padding: 32px 30px; box-shadow: 0 12px 40px rgba(45,58,107,.10);
    width: 100%; max-width: 400px; justify-self: center; box-sizing: border-box;
  }
  .login-baru .lb-kartu .lb-merek { font-family: "Fraunces", Georgia, serif; font-size: 14px; font-weight: 600; color: #2d3a6b; margin: 0 0 10px; }
  .login-baru .lb-kartu h2 { font-family: "Fraunces", Georgia, serif; font-size: 26px; line-height: 1.15; margin: 0 0 8px; }
  .login-baru .lb-kartu .lb-ket { color: #6b6456; font-size: 13px; margin: 0 0 20px; }
  .login-baru .lb-kartu label { display: block; font-size: 12px; color: #5d564a; margin-bottom: 14px; }
  .login-baru .lb-kartu input {
    display: block; width: 100%; box-sizing: border-box; margin-top: 6px;
    padding: 12px 14px; border: 1px solid #e6dcc6; border-radius: 10px; background: #fff;
    font: inherit; font-size: 14px; color: #26221c;
  }
  .login-baru .lb-kartu input:focus { outline: 2px solid #2d3a6b33; border-color: #2d3a6b; }
  .login-baru .lb-pass { position: relative; }
  .login-baru .lb-pass input { padding-right: 64px; }
  .login-baru .lb-pass button {
    position: absolute; right: 12px; bottom: 12px; background: none; border: 0; cursor: pointer;
    color: #2d3a6b; font: inherit; font-size: 12px; font-weight: 600;
  }
  .login-baru .lb-masuk {
    width: 100%; margin-top: 6px; padding: 13px; border: 0; border-radius: 10px; cursor: pointer;
    background: #2d3a6b; color: #fff; font: inherit; font-size: 14px; font-weight: 600;
  }
  .login-baru .lb-masuk:hover { background: #243059; }
  .login-baru .lb-daftar { font-size: 12px; color: #5d564a; margin: 18px 0 0; }
  .login-baru .lb-daftar a { color: #2d3a6b; }
  .login-baru .lb-kartu .notif { margin-bottom: 16px; font-size: 13px; }

  @media (max-width: 860px) {
    .login-baru .lb-wrap { grid-template-columns: 1fr; gap: 32px; }
    .login-baru .lb-kartu { order: -1; }
  }
</style>
</head>
<body class="login-baru">
<div class="lb-wrap">

  <section class="lb-kiri">
    <div class="lb-brand">
      <img src="assets/logo.png" alt="Logo" height="40">
      <strong><?= e(APP_NAME) ?></strong>
    </div>
    <span class="lb-pill">PEMESANAN KASUR ONLINE</span>
    <h1 class="lb-judul">Tidur nyenyak dimulai <span>dari kasur yang pas.</span></h1>
    <p class="lb-sub">Pilih ukuran kasur, pesan dari rumah, dan pantau pesananmu langsung di aplikasi Toko Kasur <?= e(APP_NAME) ?>.</p>

    <div class="lb-ukuran">
      <div>
        <svg width="30" height="20" viewBox="0 0 30 20" fill="none" stroke="#2d3a6b" stroke-width="1.5" stroke-linejoin="round"><rect x="5" y="2" width="20" height="8" rx="2"/><rect x="2" y="10" width="26" height="6" rx="1.5"/><path d="M4 16v3M26 16v3"/></svg>
        <b>Single</b><small>1 orang</small>
      </div>
      <div>
        <svg width="30" height="20" viewBox="0 0 30 20" fill="none" stroke="#2d3a6b" stroke-width="1.5" stroke-linejoin="round"><rect x="3" y="2" width="11" height="8" rx="2"/><rect x="16" y="2" width="11" height="8" rx="2"/><rect x="1.5" y="10" width="27" height="6" rx="1.5"/><path d="M3 16v3M27 16v3"/></svg>
        <b>Queen</b><small>2 orang</small>
      </div>
      <div>
        <svg width="30" height="20" viewBox="0 0 30 20" fill="none" stroke="#2d3a6b" stroke-width="1.5" stroke-linejoin="round"><rect x="2" y="2" width="12" height="8" rx="2"/><rect x="16" y="2" width="12" height="8" rx="2"/><rect x="0.8" y="10" width="28.4" height="6" rx="1.5"/><path d="M2 16v3M28 16v3"/></svg>
        <b>King</b><small>Lebih luas</small>
      </div>
    </div>

    <ol class="lb-langkah">
      <li><span class="no">1</span><div><b>Pilih tipe kasur</b><small>Lihat pilihan yang tersedia di toko.</small></div></li>
      <li><span class="no">2</span><div><b>Buat pesanan</b><small>Isi data dan tunggu konfirmasi.</small></div></li>
      <li><span class="no">3</span><div><b>Bayar</b><small>Pilih metode pembayaran yang kamu mau.</small></div></li>
    </ol>
  </section>

  <main class="lb-kartu">
    <p class="lb-merek"><?= e(APP_NAME) ?></p>
    <h2>Masuk ke aplikasi pemesanan kasur</h2>
    <p class="lb-ket">Masuk dengan akun kamu untuk melanjutkan.</p>

    <?php if ($error): ?><div class="notif error" role="alert"><?= e($error) ?></div><?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label>Username
        <input name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
      </label>
      <label>Password
        <span class="lb-pass">
          <input type="password" name="password" id="lb-password" required>
          <button type="button" id="lb-lihat">Lihat</button>
        </span>
      </label>
      <button class="lb-masuk" type="submit">Masuk</button>
    </form>
    <p class="lb-daftar">Belum punya akun? <a href="register.php">Daftar sebagai pembeli</a></p>
  </main>

</div>
<script>
  (function () {
    var tombol = document.getElementById('lb-lihat');
    var isi = document.getElementById('lb-password');
    tombol.addEventListener('click', function () {
      var tampil = isi.type === 'password';
      isi.type = tampil ? 'text' : 'password';
      tombol.textContent = tampil ? 'Sembunyi' : 'Lihat';
    });
  })();
</script>
</body>
</html>