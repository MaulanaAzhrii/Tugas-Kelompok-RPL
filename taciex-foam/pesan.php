<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
if ((user_login()['role'] ?? '') === 'pembeli') {
    redirect('pesan_pembeli.php');
}
cek_csrf_post();
$pdo  = db();
$user = user_login();

// ---------- Validasi input form (FR-12 sampai FR-16) ----------
function baca_form(PDO $pdo, array $in, array &$err): ?array {
    $mode = ($in['mode'] ?? 'lama') === 'baru' ? 'baru' : 'lama';
    $d = [
        'mode' => $mode, 'pelanggan_id' => 0,
        'baru' => ['nama' => '', 'telepon' => '', 'alamat' => ''],
        'alamat' => trim((string)($in['alamat'] ?? '')),
        'tanggal' => trim((string)($in['tanggal'] ?? '')),
        'items' => [],
    ];

    if ($mode === 'lama') {
        $pid = (int)($in['pelanggan_id'] ?? 0);
        $st = $pdo->prepare('SELECT id FROM pelanggan WHERE id = ?');
        $st->execute([$pid]);
        if (!$st->fetch()) $err[] = 'Pilih pelanggan terlebih dahulu.';
        else $d['pelanggan_id'] = $pid;
    } else {
        $nama = trim((string)($in['nama_baru'] ?? ''));
        $tel  = trim((string)($in['telepon_baru'] ?? ''));
        $alm  = trim((string)($in['alamat_baru'] ?? ''));
        if ($nama === '' || panjang_teks($nama) > 100) $err[] = 'Nama pemesan wajib diisi (maksimal 100 karakter).';
        if (!preg_match('/^[0-9+\-\s()]{6,20}$/', $tel)) $err[] = 'Nomor telepon pemesan tidak valid.';
        if ($alm === '' || panjang_teks($alm) > 255) $err[] = 'Alamat pemesan wajib diisi (maksimal 255 karakter).';
        $d['baru'] = ['nama' => $nama, 'telepon' => $tel, 'alamat' => $alm];
    }

    if (!tanggal_valid($d['tanggal'])) $err[] = 'Tanggal pemesanan tidak valid.';
    if ($d['alamat'] === '' || panjang_teks($d['alamat']) > 255) $err[] = 'Alamat pengiriman wajib diisi (maksimal 255 karakter).';

    $tipe = (array)($in['tipe_id'] ?? []);
    $jml  = (array)($in['jumlah'] ?? []);
    $perTipe = [];
    foreach ($tipe as $i => $tid) {
        $tid = (int)$tid;
        $j   = (int)($jml[$i] ?? 0);
        if ($tid === 0 && $j <= 1) continue;   // baris kosong
        if ($tid <= 0) { $err[] = 'Pilih tipe kasur pada setiap baris.'; continue; }
        if ($j < 1 || $j > 9999) { $err[] = 'Jumlah harus antara 1 dan 9999.'; continue; }
        $perTipe[$tid] = ($perTipe[$tid] ?? 0) + $j;
    }
    if (!$perTipe && !in_array('Pilih tipe kasur pada setiap baris.', $err, true)) $err[] = 'Tambahkan minimal satu tipe kasur.';

    // FR-16: tolak jika stok tidak mencukupi
    $st = $pdo->prepare('SELECT nama, ukuran, stok FROM tipe_kasur WHERE id = ?');
    foreach ($perTipe as $tid => $j) {
        $st->execute([$tid]);
        $t = $st->fetch();
        if (!$t) { $err[] = 'Tipe kasur tidak ditemukan.'; continue; }
        if ((int)$t['stok'] < $j) {
            $err[] = "Stok {$t['nama']} {$t['ukuran']} tidak mencukupi (tersedia {$t['stok']}, diminta $j).";
        }
        $d['items'][] = [$tid, $j];
    }
    return $err ? null : $d;
}

function draft_ke_input(array $d): array {
    $in = [
        'mode' => $d['mode'], 'pelanggan_id' => $d['pelanggan_id'],
        'nama_baru' => $d['baru']['nama'], 'telepon_baru' => $d['baru']['telepon'], 'alamat_baru' => $d['baru']['alamat'],
        'alamat' => $d['alamat'], 'tanggal' => $d['tanggal'], 'tipe_id' => [], 'jumlah' => [],
    ];
    foreach ($d['items'] as [$tid, $j]) { $in['tipe_id'][] = $tid; $in['jumlah'][] = $j; }
    return $in;
}

function hitung_draft(PDO $pdo, array $d): array {
    $lines = [];
    $total = 0;
    $st = $pdo->prepare('SELECT * FROM tipe_kasur WHERE id = ?');
    foreach ($d['items'] as [$tid, $j]) {
        $st->execute([$tid]);
        $t = $st->fetch();
        if (!$t) continue;
        $sub = (int)$t['harga'] * $j;   // FR-15: total dihitung otomatis
        $total += $sub;
        $lines[] = ['t' => $t, 'jumlah' => $j, 'subtotal' => $sub];
    }
    return [$lines, $total];
}

// ---------- Simpan pesanan (satu transaksi database, NFR-05) ----------
function simpan_pesanan(PDO $pdo, array $d, int $userId): int {
    $pdo->beginTransaction();
    try {
        $pid = $d['pelanggan_id'];
        if ($d['mode'] === 'baru') {
            $pdo->prepare('INSERT INTO pelanggan (nama, alamat, telepon, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$d['baru']['nama'], $d['baru']['alamat'], $d['baru']['telepon'], date('Y-m-d H:i:s')]);
            $pid = (int)$pdo->lastInsertId();
        }

        $items = $d['items'];
        usort($items, fn($a, $b) => $a[0] <=> $b[0]);   // urutan tetap supaya tidak saling mengunci
        $total = 0;
        $baris = [];
        $st = $pdo->prepare('SELECT id, nama, ukuran, harga, stok FROM tipe_kasur WHERE id = ? FOR UPDATE');
        foreach ($items as [$tid, $j]) {
            $st->execute([$tid]);
            $t = $st->fetch();
            if (!$t) throw new RuntimeException('Tipe kasur tidak ditemukan.');
            if ((int)$t['stok'] < $j) {
                throw new RuntimeException("Stok {$t['nama']} {$t['ukuran']} tidak mencukupi (tersedia {$t['stok']}, diminta $j).");
            }
            $sub = (int)$t['harga'] * $j;
            $total += $sub;
            $baris[] = [$tid, (int)$t['harga'], $j, $sub];
        }

        $pdo->prepare("INSERT INTO pemesanan (no_pesanan, pelanggan_id, tanggal, alamat_pengiriman, total, dibuat_oleh, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([uniqid('T', true), $pid, $d['tanggal'], $d['alamat'], $total, $userId, date('Y-m-d H:i:s')]);
        $id = (int)$pdo->lastInsertId();
        $no = 'TF-' . date('Ymd', strtotime($d['tanggal'])) . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE pemesanan SET no_pesanan = ? WHERE id = ?')->execute([$no, $id]);

        $ins = $pdo->prepare('INSERT INTO pemesanan_detail (pemesanan_id, tipe_kasur_id, harga_satuan, jumlah, subtotal) VALUES (?, ?, ?, ?, ?)');
        foreach ($baris as [$tid, $harga, $j, $sub]) {
            $ins->execute([$id, $tid, $harga, $j, $sub]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// ---------- Alur halaman ----------
$err = [];
$isi = null;
$langkah = ($_GET['langkah'] ?? '') === 'ringkasan' ? 'ringkasan' : 'form';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'preview') {
        $draft = baca_form($pdo, $_POST, $err);
        if ($draft) {
            $_SESSION['draft_pesanan'] = $draft;
            redirect('pesan.php?langkah=ringkasan');
        }
        $isi = $_POST;
        $langkah = 'form';
    } elseif ($aksi === 'simpan') {
        $draft = $_SESSION['draft_pesanan'] ?? null;
        if (!$draft) {
            flash('error', 'Data pesanan tidak ditemukan. Silakan isi form lagi.');
            redirect('pesan.php');
        }
        try {
            $id = simpan_pesanan($pdo, $draft, (int)$user['id']);
            unset($_SESSION['draft_pesanan']);
            flash('ok', 'Pesanan berhasil disimpan dengan status Menunggu Konfirmasi.');
            redirect('pesanan_detail.php?id=' . $id);
        } catch (Throwable $e) {
            flash('error', pesan_db($e, 'Pesanan gagal disimpan. Coba lagi.'));
            redirect('pesan.php?ubah=1');
        }
    }
} elseif (isset($_GET['ubah']) && !empty($_SESSION['draft_pesanan'])) {
    $isi = draft_ke_input($_SESSION['draft_pesanan']);
}

// ---------- Halaman ringkasan (FR-17) ----------
if ($langkah === 'ringkasan' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $draft = $_SESSION['draft_pesanan'] ?? null;
    if (!$draft) redirect('pesan.php');
    [$lines, $total] = hitung_draft($pdo, $draft);
    if ($draft['mode'] === 'lama') {
        $st = $pdo->prepare('SELECT nama, telepon, alamat FROM pelanggan WHERE id = ?');
        $st->execute([$draft['pelanggan_id']]);
        $pel = $st->fetch() ?: ['nama' => '-', 'telepon' => '-', 'alamat' => '-'];
        $baruLabel = '';
    } else {
        $pel = $draft['baru'];
        $baruLabel = ' <span class="badge b-belum-bayar">Pelanggan baru</span>';
    }
    layout_top('Ringkasan Pesanan', 'pesan');
    ?>
    <div class="judul-bar">
      <div><h1>Ringkasan pesanan</h1><p class="redup">Periksa dulu. Pesanan baru tersimpan setelah kamu menekan “Simpan pesanan”.</p></div>
    </div>
    <section class="panel">
      <h2>Pemesan</h2>
      <dl class="rincian">
        <dt>Nama</dt><dd><?= e($pel['nama']) ?><?= $baruLabel ?></dd>
        <dt>Telepon</dt><dd><?= e($pel['telepon']) ?></dd>
        <dt>Tanggal pemesanan</dt><dd><?= e(tgl($draft['tanggal'])) ?></dd>
        <dt>Alamat pengiriman</dt><dd><?= e($draft['alamat']) ?></dd>
      </dl>
    </section>
    <section class="panel">
      <h2>Kasur yang dipesan</h2>
      <div class="tabel-bungkus">
        <table class="tabel">
          <thead><tr><th>Tipe kasur</th><th>Ukuran</th><th class="kanan">Harga</th><th class="kanan">Jumlah</th><th class="kanan">Subtotal</th></tr></thead>
          <tbody>
          <?php foreach ($lines as $l): ?>
            <tr>
              <td><strong><?= e($l['t']['nama']) ?></strong><br><small class="redup"><?= e($l['t']['kode']) ?> · <?= e($l['t']['bahan']) ?>, <?= e($l['t']['ketebalan']) ?></small></td>
              <td><?= e($l['t']['ukuran']) ?></td>
              <td class="kanan"><?= e(rupiah($l['t']['harga'])) ?></td>
              <td class="kanan"><?= (int)$l['jumlah'] ?></td>
              <td class="kanan"><?= e(rupiah($l['subtotal'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot><tr><th colspan="4" class="kanan">Total</th><th class="kanan total"><?= e(rupiah($total)) ?></th></tr></tfoot>
        </table>
      </div>
    </section>
    <div class="aksi">
      <a class="btn ghost" href="pesan.php?ubah=1">Ubah pesanan</a>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <button class="btn primer" type="submit">Simpan pesanan</button>
      </form>
    </div>
    <?php
    layout_bottom();
    exit;
}

// ---------- Halaman form ----------
$pelangganList = $pdo->query('SELECT id, nama, telepon, alamat FROM pelanggan ORDER BY nama')->fetchAll();
$tipeList = $pdo->query('SELECT id, kode, nama, ukuran, harga, stok FROM tipe_kasur WHERE stok > 0 ORDER BY nama, ukuran')->fetchAll();   // NFR-15

$mode = $isi['mode'] ?? ($pelangganList ? 'lama' : 'baru');
$v = fn(string $k, string $d = '') => e($isi[$k] ?? $d);
$tanggal = $isi['tanggal'] ?? date('Y-m-d');
$barisTipe = $isi['tipe_id'] ?? [''];
$barisJml  = $isi['jumlah'] ?? [1];
if (!$barisTipe) { $barisTipe = ['']; $barisJml = [1]; }

function opsi_tipe(array $tipeList, $terpilih): string {
    $h = '<option value="">Pilih tipe kasur</option>';
    foreach ($tipeList as $t) {
        $h .= '<option value="' . (int)$t['id'] . '" data-harga="' . (int)$t['harga'] . '" data-stok="' . (int)$t['stok'] . '"'
            . ((string)$terpilih === (string)$t['id'] ? ' selected' : '') . '>'
            . e($t['kode'] . ' · ' . $t['nama'] . ' ' . $t['ukuran'] . ' (' . rupiah($t['harga']) . ', stok ' . $t['stok'] . ')') . '</option>';
    }
    return $h;
}

layout_top('Pesan Baru', 'pesan');
?>
<div class="judul-bar">
  <div><h1>Pesan kasur</h1><p class="redup">Catat pesanan pelanggan. Total dihitung otomatis dan ringkasan ditampilkan sebelum disimpan.</p></div>
</div>

<?php if ($err): ?>
  <div class="notif error" role="alert">
    <strong>Pesanan belum bisa dilanjutkan:</strong>
    <ul><?php foreach (array_unique($err) as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" id="form-pesan" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="preview">

  <section class="panel">
    <h2>1. Pemesan</h2>
    <div class="pilih-mode">
      <label class="radio"><input type="radio" name="mode" value="lama" <?= $mode === 'lama' ? 'checked' : '' ?>> Pelanggan terdaftar</label>
      <label class="radio"><input type="radio" name="mode" value="baru" <?= $mode === 'baru' ? 'checked' : '' ?>> Pelanggan baru</label>
    </div>

    <div id="blok-lama" <?= $mode === 'lama' ? '' : 'hidden' ?>>
      <label>Pilih pelanggan
        <select name="pelanggan_id" id="pelanggan-id">
          <option value="">Pilih pelanggan</option>
          <?php foreach ($pelangganList as $p): ?>
            <option value="<?= (int)$p['id'] ?>" data-alamat="<?= e($p['alamat']) ?>" <?= (string)($isi['pelanggan_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>>
              <?= e($p['nama'] . ' · ' . $p['telepon']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div id="blok-baru" class="grid-form" <?= $mode === 'baru' ? '' : 'hidden' ?>>
      <label>Nama pemesan <input name="nama_baru" id="nama-baru" maxlength="100" value="<?= $v('nama_baru') ?>"></label>
      <label>Nomor telepon <input name="telepon_baru" maxlength="20" inputmode="tel" value="<?= $v('telepon_baru') ?>"></label>
      <label class="lebar">Alamat pemesan <input name="alamat_baru" id="alamat-baru" maxlength="255" value="<?= $v('alamat_baru') ?>"></label>
    </div>
  </section>

  <section class="panel">
    <h2>2. Kasur yang dipesan</h2>
    <?php if (!$tipeList): ?>
      <div class="notif error">Belum ada tipe kasur dengan stok tersedia. Minta Admin menambah stok di menu Tipe Kasur.</div>
    <?php endif; ?>
    <div class="item-kepala" aria-hidden="true"><span>Tipe kasur</span><span>Jumlah</span><span>Subtotal</span><span></span></div>
    <div id="daftar-item">
      <?php foreach ($barisTipe as $i => $tid): ?>
        <div class="baris-item">
          <select name="tipe_id[]" aria-label="Tipe kasur"><?= opsi_tipe($tipeList, $tid) ?></select>
          <input type="number" name="jumlah[]" min="1" max="9999" value="<?= (int)($barisJml[$i] ?? 1) ?>" aria-label="Jumlah">
          <output class="sub">Rp 0</output>
          <button type="button" class="btn ghost kecil hapus-baris">Hapus</button>
        </div>
      <?php endforeach; ?>
    </div>
    <template id="tpl-baris">
      <div class="baris-item">
        <select name="tipe_id[]" aria-label="Tipe kasur"><?= opsi_tipe($tipeList, '') ?></select>
        <input type="number" name="jumlah[]" min="1" max="9999" value="1" aria-label="Jumlah">
        <output class="sub">Rp 0</output>
        <button type="button" class="btn ghost kecil hapus-baris">Hapus</button>
      </div>
    </template>
    <div class="aksi kiri"><button type="button" class="btn sekunder" id="tambah-baris">+ Tambah tipe kasur</button></div>
    <p class="total-live">Total sementara <strong id="total-live">Rp 0</strong></p>
  </section>

  <section class="panel grid-form">
    <h2>3. Pengiriman</h2>
    <label>Tanggal pemesanan <input type="date" name="tanggal" value="<?= e($tanggal) ?>" required></label>
    <label class="lebar">Alamat pengiriman <input name="alamat" id="alamat-kirim" maxlength="255" value="<?= $v('alamat') ?>" required></label>
  </section>

  <div class="aksi">
    <button class="btn primer" type="submit">Lihat ringkasan pesanan</button>
  </div>
</form>
<?php layout_bottom();
