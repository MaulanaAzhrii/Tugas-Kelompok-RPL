<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
if ((user_login()['role'] ?? '') === 'pembeli') {
    redirect('katalog.php');
}
cek_csrf_post();
$pdo = db();

// ---- Simpan / hapus: hanya Admin (FR-05, FR-06, FR-07) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    try {
        $aksi = $_POST['aksi'] ?? '';
        $id   = (int)($_POST['id'] ?? 0);
        if ($aksi === 'simpan') {
            $kode      = strtoupper(trim($_POST['kode'] ?? ''));
            $nama      = trim($_POST['nama'] ?? '');
            $ukuran    = trim($_POST['ukuran'] ?? '');
            $ketebalan = trim($_POST['ketebalan'] ?? '');
            $bahan     = trim($_POST['bahan'] ?? '');
            $harga     = $_POST['harga'] ?? '';
            $stok      = $_POST['stok'] ?? '';

            if ($kode === '' || panjang_teks($kode) > 20) throw new RuntimeException('Kode wajib diisi (maksimal 20 karakter).');
            if ($nama === '' || panjang_teks($nama) > 100) throw new RuntimeException('Nama tipe wajib diisi (maksimal 100 karakter).');
            if ($ukuran === '' || panjang_teks($ukuran) > 30) throw new RuntimeException('Ukuran wajib diisi (maksimal 30 karakter).');
            if ($ketebalan === '' || panjang_teks($ketebalan) > 30) throw new RuntimeException('Ketebalan wajib diisi (maksimal 30 karakter).');
            if ($bahan === '' || panjang_teks($bahan) > 80) throw new RuntimeException('Bahan wajib diisi (maksimal 80 karakter).');
            if (!ctype_digit((string)$harga) || (int)$harga < 1) throw new RuntimeException('Harga harus berupa angka lebih dari 0.');
            if (!ctype_digit((string)$stok)) throw new RuntimeException('Stok harus berupa angka 0 atau lebih.');

            if ($id) {
                $pdo->prepare('UPDATE tipe_kasur SET kode=?, nama=?, ukuran=?, ketebalan=?, bahan=?, harga=?, stok=? WHERE id=?')
                    ->execute([$kode, $nama, $ukuran, $ketebalan, $bahan, (int)$harga, (int)$stok, $id]);
                flash('ok', 'Tipe kasur diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO tipe_kasur (kode, nama, ukuran, ketebalan, bahan, harga, stok, created_at) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$kode, $nama, $ukuran, $ketebalan, $bahan, (int)$harga, (int)$stok, date('Y-m-d H:i:s')]);
                flash('ok', 'Tipe kasur ditambahkan.');
            }
        } elseif ($aksi === 'hapus') {
            $pdo->prepare('DELETE FROM tipe_kasur WHERE id = ?')->execute([$id]);
            flash('ok', 'Tipe kasur dihapus.');
        }
    } catch (Throwable $e) {
        $pesan = pesan_db($e);
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) $pesan = 'Kode tipe kasur sudah dipakai.';
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1451) $pesan = 'Tipe kasur ini sudah ada di pesanan, jadi tidak bisa dihapus. Ubah stoknya menjadi 0 jika tidak dijual lagi.';
        flash('error', $pesan);
    }
    redirect('tipe_kasur.php');
}

$edit = null;
if (is_admin() && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM tipe_kasur WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $st = $pdo->prepare('SELECT * FROM tipe_kasur WHERE kode LIKE ? OR nama LIKE ? OR ukuran LIKE ? OR bahan LIKE ? ORDER BY nama, ukuran');
    $like = '%' . $q . '%';
    $st->execute([$like, $like, $like, $like]);
    $rows = $st->fetchAll();
} else {
    $rows = $pdo->query('SELECT * FROM tipe_kasur ORDER BY nama, ukuran')->fetchAll();
}

layout_top('Tipe Kasur', 'tipe');
?>
<div class="judul-bar">
  <div>
    <h1>Tipe Kasur</h1>
    <p class="redup">Daftar tipe kasur Taciex Foam beserta harga dan stok.</p>
  </div>
</div>

<?php if (is_admin()): ?>
<form method="post" class="panel grid-form">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="simpan">
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <h2><?= $edit ? 'Ubah ' . e($edit['kode']) : 'Tambah tipe kasur' ?></h2>
  <label>Kode <input name="kode" required maxlength="20" value="<?= e($edit['kode'] ?? '') ?>" placeholder="KB-090"></label>
  <label>Nama tipe <input name="nama" required maxlength="100" value="<?= e($edit['nama'] ?? '') ?>" placeholder="Kasur Busa Standar"></label>
  <label>Ukuran <input name="ukuran" required maxlength="30" value="<?= e($edit['ukuran'] ?? '') ?>" placeholder="90 x 200 cm"></label>
  <label>Ketebalan <input name="ketebalan" required maxlength="30" value="<?= e($edit['ketebalan'] ?? '') ?>" placeholder="15 cm"></label>
  <label>Bahan <input name="bahan" required maxlength="80" value="<?= e($edit['bahan'] ?? '') ?>" placeholder="Busa (foam)"></label>
  <label>Harga (Rp) <input type="number" name="harga" required min="1" step="1" value="<?= e($edit['harga'] ?? '') ?>"></label>
  <label>Stok <input type="number" name="stok" required min="0" step="1" value="<?= e($edit['stok'] ?? '0') ?>"></label>
  <div class="aksi">
    <?php if ($edit): ?><a class="btn ghost" href="tipe_kasur.php">Batal</a><?php endif; ?>
    <button class="btn primer" type="submit"><?= $edit ? 'Simpan perubahan' : 'Tambah tipe kasur' ?></button>
  </div>
</form>
<?php endif; ?>

<section class="panel">
  <form method="get" class="cari">
    <label class="sr">Cari tipe kasur</label>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode, nama, ukuran, atau bahan">
    <button class="btn sekunder" type="submit">Cari</button>
    <?php if ($q !== ''): ?><a class="btn ghost" href="tipe_kasur.php">Reset</a><?php endif; ?>
  </form>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Kode</th><th>Nama tipe</th><th>Ukuran</th><th>Ketebalan</th><th>Bahan</th><th class="kanan">Harga</th><th class="kanan">Stok</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= e($r['kode']) ?></code></td>
          <td><?= e($r['nama']) ?></td>
          <td><?= e($r['ukuran']) ?></td>
          <td><?= e($r['ketebalan']) ?></td>
          <td><?= e($r['bahan']) ?></td>
          <td class="kanan"><?= e(rupiah($r['harga'])) ?></td>
          <td class="kanan">
            <?= (int)$r['stok'] ?>
            <?php if ((int)$r['stok'] === 0): ?><span class="badge b-dibatalkan">Habis</span>
            <?php elseif ((int)$r['stok'] <= STOK_MENIPIS): ?><span class="badge b-menunggu-konfirmasi">Menipis</span><?php endif; ?>
          </td>
          <?php if (is_admin()): ?>
          <td class="aksi-sel">
            <a class="btn ghost kecil" href="tipe_kasur.php?edit=<?= (int)$r['id'] ?>">Ubah</a>
            <form method="post" data-konfirmasi="Hapus tipe kasur <?= e($r['kode']) ?>?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn bahaya kecil" type="submit">Hapus</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="redup">Tidak ada tipe kasur<?= $q !== '' ? ' yang cocok dengan pencarian' : '' ?>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php layout_bottom();
