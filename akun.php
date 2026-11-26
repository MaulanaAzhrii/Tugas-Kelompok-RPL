<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
cek_csrf_post();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama  = $_POST['lama'] ?? '';
    $baru  = $_POST['baru'] ?? '';
    $ulang = $_POST['ulang'] ?? '';
    $pdo = db();
    $st = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $st->execute([user_login()['id']]);
    $hash = $st->fetchColumn();

    if (!$hash || !password_verify($lama, $hash)) {
        $err = 'Password lama salah.';
    } elseif (strlen($baru) < 8) {
        $err = 'Password baru minimal 8 karakter.';
    } elseif ($baru !== $ulang) {
        $err = 'Konfirmasi password tidak sama.';
    } else {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($baru, PASSWORD_DEFAULT), user_login()['id']]);
        flash('ok', 'Password berhasil diganti.');
        redirect('akun.php');
    }
}

layout_top('Akun', 'akun');
?>
<div class="sempit">
  <h1>Akun saya</h1>
  <p class="redup">Masuk sebagai <strong><?= e(user_login()['username']) ?></strong> (<?= e(user_login()['role']) ?>).</p>
  <?php if ($err): ?><div class="notif error" role="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="panel">
    <?= csrf_field() ?>
    <h2>Ganti password</h2>
    <label>Password lama <input type="password" name="lama" required></label>
    <label>Password baru (minimal 8 karakter) <input type="password" name="baru" required minlength="8"></label>
    <label>Ulangi password baru <input type="password" name="ulang" required minlength="8"></label>
    <button class="btn primer" type="submit">Simpan password</button>
  </form>
</div>
<?php layout_bottom();
