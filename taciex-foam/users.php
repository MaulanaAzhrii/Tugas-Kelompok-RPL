<?php
// Pengelolaan akun pengguna (Admin dan Kasir) - khusus Admin
require __DIR__ . '/inc/bootstrap.php';
require_admin();
cek_csrf_post();
$pdo = db();
$saya = (int)user_login()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id       = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $nama     = trim($_POST['nama'] ?? '');
        $role     = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'kasir';
        if ($id) {
            $cekRole = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $cekRole->execute([$id]);
            if ($cekRole->fetchColumn() === 'pembeli') $role = 'pembeli';
        }
        $aktif    = isset($_POST['aktif']) ? 1 : 0;
        $password = $_POST['password'] ?? '';

        if ($nama === '' || panjang_teks($nama) > 100) throw new RuntimeException('Nama wajib diisi (maksimal 100 karakter).');
        if (!$id) {
            if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) throw new RuntimeException('Username 3-50 karakter, hanya huruf, angka, titik, dan garis bawah.');
            if (strlen($password) < 8) throw new RuntimeException('Password minimal 8 karakter.');
            $pdo->prepare('INSERT INTO users (username, password, nama, role, aktif, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $nama, $role, $aktif, date('Y-m-d H:i:s')]);
            flash('ok', 'Pengguna ditambahkan.');
        } else {
            if ($id === $saya && (!$aktif || $role !== 'admin')) {
                throw new RuntimeException('Kamu tidak bisa menonaktifkan akunmu sendiri atau mengubah role-mu sendiri.');
            }
            if ($password !== '' && strlen($password) < 8) throw new RuntimeException('Password baru minimal 8 karakter.');
            $pdo->prepare('UPDATE users SET nama = ?, role = ?, aktif = ? WHERE id = ?')->execute([$nama, $role, $aktif, $id]);
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            flash('ok', 'Pengguna diperbarui.');
        }
    } catch (Throwable $e) {
        $pesan = pesan_db($e);
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) $pesan = 'Username sudah dipakai.';
        flash('error', $pesan);
    }
    redirect('users.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$rows = $pdo->query('SELECT id, username, nama, role, aktif FROM users ORDER BY role, nama')->fetchAll();

layout_top('Pengguna', 'users');
?>
<div class="judul-bar">
  <div><h1>Pengguna</h1><p class="redup">Kelola akun Admin dan Kasir. Akun tidak dihapus, cukup dinonaktifkan.</p></div>
</div>

<form method="post" class="panel grid-form" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <h2><?= $edit ? 'Ubah ' . e($edit['username']) : 'Tambah pengguna' ?></h2>
  <label>Username
    <input name="username" required maxlength="50" value="<?= e($edit['username'] ?? '') ?>" <?= $edit ? 'readonly' : '' ?>>
  </label>
  <label>Nama <input name="nama" required maxlength="100" value="<?= e($edit['nama'] ?? '') ?>"></label>
  <label>Role
    <select name="role">
      <?php if (($edit['role'] ?? '') === 'pembeli'): ?><option value="pembeli" selected>Pembeli</option><?php else: ?>
      <option value="kasir"<?= ($edit['role'] ?? 'kasir') === 'kasir' ? ' selected' : '' ?>>Kasir</option>
      <option value="admin"<?= ($edit['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Admin</option>
      <?php endif; ?>
    </select>
  </label>
  <label><?= $edit ? 'Password baru (kosongkan jika tidak diganti)' : 'Password (minimal 8 karakter)' ?>
    <input type="password" name="password" minlength="8" <?= $edit ? '' : 'required' ?>>
  </label>
  <label class="centang"><input type="checkbox" name="aktif" <?= ($edit['aktif'] ?? 1) ? 'checked' : '' ?>> Akun aktif</label>
  <div class="aksi">
    <?php if ($edit): ?><a class="btn ghost" href="users.php">Batal</a><?php endif; ?>
    <button class="btn primer" type="submit"><?= $edit ? 'Simpan perubahan' : 'Tambah pengguna' ?></button>
  </div>
</form>

<section class="panel">
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Username</th><th>Nama</th><th>Role</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= $r['aktif'] ? '' : 'nonaktif' ?>">
          <td><code><?= e($r['username']) ?></code></td>
          <td><?= e($r['nama']) ?></td>
          <td><?= e(ucfirst($r['role'])) ?></td>
          <td><?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?></td>
          <td><a class="btn ghost kecil" href="users.php?edit=<?= (int)$r['id'] ?>">Ubah</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php layout_bottom();
