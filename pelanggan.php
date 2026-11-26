<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
if ((user_login()['role'] ?? '') === 'pembeli') {
    redirect('katalog.php');
}
cek_csrf_post();
$pdo = db();

// ---- Tambah / ubah / hapus: Admin dan Kasir (FR-09, FR-10, FR-11) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $aksi = $_POST['aksi'] ?? '';
        $id   = (int)($_POST['id'] ?? 0);
        if ($aksi === 'simpan') {
            $nama    = trim($_POST['nama'] ?? '');
            $alamat  = trim($_POST['alamat'] ?? '');
            $telepon = trim($_POST['telepon'] ?? '');
            if ($nama === '' || panjang_teks($nama) > 100) throw new RuntimeException('Nama wajib diisi (maksimal 100 karakter).');
            if ($alamat === '' || panjang_teks($alamat) > 255) throw new RuntimeException('Alamat wajib diisi (maksimal 255 karakter).');
            if (!preg_match('/^[0-9+\-\s()]{6,20}$/', $telepon)) throw new RuntimeException('Nomor telepon tidak valid (6-20 karakter, hanya angka dan tanda + - ( ) ).');

            if ($id) {
                $pdo->prepare('UPDATE pelanggan SET nama = ?, alamat = ?, telepon = ? WHERE id = ?')->execute([$nama, $alamat, $telepon, $id]);
                flash('ok', 'Data pelanggan diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO pelanggan (nama, alamat, telepon, created_at) VALUES (?, ?, ?, ?)')
                    ->execute([$nama, $alamat, $telepon, date('Y-m-d H:i:s')]);
                flash('ok', 'Pelanggan ditambahkan.');
            }
        } elseif ($aksi === 'hapus') {
            $pdo->prepare('DELETE FROM pelanggan WHERE id = ?')->execute([$id]);
            flash('ok', 'Pelanggan dihapus.');
        }
    } catch (Throwable $e) {
        $pesan = pesan_db($e);
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1451) $pesan = 'Pelanggan ini sudah punya riwayat pesanan, jadi tidak bisa dihapus.';
        flash('error', $pesan);
    }
    redirect('pelanggan.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM pelanggan WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$q = trim($_GET['q'] ?? '');
$halaman = max(1, (int)($_GET['hal'] ?? 1));
$per = 15;
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE nama LIKE ? OR telepon LIKE ? OR alamat LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$st = $pdo->prepare("SELECT COUNT(*) FROM pelanggan $where");
$st->execute($params);
$total = (int)$st->fetchColumn();
$totalHal = max(1, (int)ceil($total / $per));
$halaman = min($halaman, $totalHal);
$st = $pdo->prepare("SELECT * FROM pelanggan $where ORDER BY nama LIMIT $per OFFSET " . (($halaman - 1) * $per));
$st->execute($params);
$rows = $st->fetchAll();

layout_top('Pelanggan', 'pelanggan');
?>
<div class="judul-bar">
  <div>
    <h1>Pelanggan</h1>
    <p class="redup">Data pelanggan yang melakukan pemesanan.</p>
  </div>
</div>

<form method="post" class="panel grid-form">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="simpan">
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <h2><?= $edit ? 'Ubah ' . e($edit['nama']) : 'Tambah pelanggan' ?></h2>
  <label>Nama <input name="nama" required maxlength="100" value="<?= e($edit['nama'] ?? '') ?>"></label>
  <label>Nomor telepon <input name="telepon" required maxlength="20" inputmode="tel" value="<?= e($edit['telepon'] ?? '') ?>" placeholder="0812xxxxxxxx"></label>
  <label class="lebar">Alamat <input name="alamat" required maxlength="255" value="<?= e($edit['alamat'] ?? '') ?>"></label>
  <div class="aksi">
    <?php if ($edit): ?><a class="btn ghost" href="pelanggan.php">Batal</a><?php endif; ?>
    <button class="btn primer" type="submit"><?= $edit ? 'Simpan perubahan' : 'Tambah pelanggan' ?></button>
  </div>
</form>

<section class="panel">
  <form method="get" class="cari">
    <label class="sr">Cari pelanggan</label>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, telepon, atau alamat">
    <button class="btn sekunder" type="submit">Cari</button>
    <?php if ($q !== ''): ?><a class="btn ghost" href="pelanggan.php">Reset</a><?php endif; ?>
  </form>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Nama</th><th>Telepon</th><th>Alamat</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= e($r['nama']) ?></strong></td>
          <td><?= e($r['telepon']) ?></td>
          <td><?= e($r['alamat']) ?></td>
          <td class="aksi-sel">
            <a class="btn ghost kecil" href="pelanggan.php?edit=<?= (int)$r['id'] ?>">Ubah</a>
            <form method="post" data-konfirmasi="Hapus pelanggan <?= e($r['nama']) ?>?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn bahaya kecil" type="submit">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="redup">Belum ada pelanggan<?= $q !== '' ? ' yang cocok dengan pencarian' : '' ?>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php tampil_pager($halaman, $totalHal, ['q' => $q]); ?>
</section>
<?php layout_bottom();
