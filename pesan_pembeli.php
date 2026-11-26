<?php
require __DIR__ . '/pembeli_inc.php';
$pel = wajib_pembeli();
cek_csrf_post();
$pdo  = db();
$user = user_login();

// ====== FUNGSI ======
function baca_pesanan(PDO $pdo, array $in, array &$err): ?array {
    $d = [
        'alamat'  => trim((string)($in['alamat'] ?? '')),
        'metode'  => (string)($in['metode'] ?? ''),
        'tanggal' => date('Y-m-d'),
        'items'   => [],
    ];
    if ($d['alamat'] === '' || panjang_teks($d['alamat']) > 255) {
        $err[] = 'Alamat pengiriman wajib diisi (maksimal 255 karakter).';
    }
    if (!in_array($d['metode'], METODE_PEMBELI, true)) {
        $err[] = 'Pilih metode pembayaran.';
    }

    $diminta = 0;
    $jml = $in['jumlah'] ?? [];
    if (!is_array($jml)) $jml = [];
    $st = $pdo->prepare('SELECT nama, ukuran, stok FROM tipe_kasur WHERE id = ?');
    foreach ($jml as $tid => $j) {
        $tid = (int)$tid;
        $j   = (int)$j;
        if ($j <= 0) continue;
        $diminta++;
        if ($j > 9999) { $err[] = 'Jumlah maksimal 9999 per tipe kasur.'; continue; }
        $st->execute([$tid]);
        $t = $st->fetch();
        if (!$t) { $err[] = 'Tipe kasur tidak ditemukan.'; continue; }
        // FR-19: tolak jika stok tidak cukup
        if ((int)$t['stok'] < $j) {
            $err[] = "Stok {$t['nama']} {$t['ukuran']} tidak mencukupi (tersedia {$t['stok']}, diminta $j).";
            continue;
        }
        $d['items'][] = [$tid, $j];
    }
    if ($diminta === 0) $err[] = 'Isi jumlah minimal satu tipe kasur.';
    return $err ? null : $d;
}

function hitung_pesanan(PDO $pdo, array $d): array {
    $lines = [];
    $total = 0;
    $st = $pdo->prepare('SELECT * FROM tipe_kasur WHERE id = ?');
    foreach ($d['items'] as [$tid, $j]) {
        $st->execute([$tid]);
        $t = $st->fetch();
        if (!$t) continue;
        $sub = (int)$t['harga'] * $j;   // FR-18: total otomatis
        $total += $sub;
        $lines[] = ['t' => $t, 'jumlah' => $j, 'subtotal' => $sub];
    }
    return [$lines, $total];
}

function simpan_pesanan_pembeli(PDO $pdo, array $d, int $pelangganId, int $userId): int {
    $pdo->beginTransaction();
    try {
        $items = $d['items'];
        usort($items, fn($a, $b) => $a[0] <=> $b[0]);
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

        $pdo->prepare('INSERT INTO pemesanan (no_pesanan, pelanggan_id, tanggal, alamat_pengiriman, total, metode_pembayaran, dibuat_oleh, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([uniqid('T', true), $pelangganId, $d['tanggal'], $d['alamat'], $total, $d['metode'], $userId, date('Y-m-d H:i:s')]);
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

// ====== ALUR ======
$err = [];
$isi = null;
$langkah = ($_GET['langkah'] ?? '') === 'ringkasan' ? 'ringkasan' : 'form';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'preview') {
        $draft = baca_pesanan($pdo, $_POST, $err);
        if ($draft) {
            $_SESSION['draft_pembeli'] = $draft;
            redirect('pesan_pembeli.php?langkah=ringkasan');
        }
        $isi = $_POST;
        $langkah = 'form';
    } elseif ($aksi === 'simpan') {
        $draft = $_SESSION['draft_pembeli'] ?? null;
        if (!$draft) {
            pesan_info('error', 'Data pesanan tidak ditemukan. Silakan isi form lagi.');
            redirect('pesan_pembeli.php');
        }
        try {
            $id = simpan_pesanan_pembeli($pdo, $draft, (int)$pel['id'], (int)$user['id']);
            unset($_SESSION['draft_pembeli']);
            pesan_info('ok', 'Pesanan berhasil dibuat dengan status Menunggu Konfirmasi.');
            redirect('pesanan_saya.php?id=' . $id);
        } catch (Throwable $e) {
            pesan_info('error', pesan_db($e, 'Pesanan gagal disimpan. Coba lagi.'));
            redirect('pesan_pembeli.php?ubah=1');
        }
    }
} elseif (isset($_GET['ubah']) && !empty($_SESSION['draft_pembeli'])) {
    $d0  = $_SESSION['draft_pembeli'];
    $isi = ['alamat' => $d0['alamat'], 'metode' => $d0['metode'], 'jumlah' => []];
    foreach ($d0['items'] as [$tid, $j]) $isi['jumlah'][$tid] = $j;
}

// ====== HALAMAN RINGKASAN (FR-22) ======
if ($langkah === 'ringkasan' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $draft = $_SESSION['draft_pembeli'] ?? null;
    if (!$draft) redirect('pesan_pembeli.php');
    [$lines, $total] = hitung_pesanan($pdo, $draft);

    atas_pembeli('Ringkasan Pesanan');
    ?>
    <div class="judul-bar">
      <div><h1>Ringkasan pesanan</h1><p class="redup">Periksa dulu. Pesanan baru tersimpan setelah kamu menekan “Simpan pesanan”.</p></div>
    </div>
    <section class="panel">
      <h2>Pemesan</h2>
      <dl class="rincian">
        <dt>Nama</dt><dd><?= e($pel['nama']) ?></dd>
        <dt>Telepon</dt><dd><?= e($pel['telepon']) ?></dd>
        <dt>Tanggal pemesanan</dt><dd><?= e(tgl($draft['tanggal'])) ?></dd>
        <dt>Alamat pengiriman</dt><dd><?= e($draft['alamat']) ?></dd>
        <dt>Metode pembayaran</dt><dd><?= e($draft['metode']) ?></dd>
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
      <a class="btn ghost" href="pesan_pembeli.php?ubah=1">Ubah pesanan</a>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <button class="btn primer" type="submit">Simpan pesanan</button>
      </form>
    </div>
    <?php
    bawah_pembeli();
    exit;
}

// ====== HALAMAN FORM ======
// NFR-16: hanya tipe kasur yang stoknya tersedia
$tipeList = $pdo->query('SELECT id, kode, nama, ukuran, ketebalan, bahan, harga, stok FROM tipe_kasur WHERE stok > 0 ORDER BY nama, ukuran')->fetchAll();

$alamat = $isi['alamat'] ?? $pel['alamat'];
$metode = $isi['metode'] ?? '';
$jml    = $isi['jumlah'] ?? [];
if (!is_array($jml)) $jml = [];
// Datang dari tombol "Pesan" di katalog: isi jumlah 1 pada kasur tersebut
if ($isi === null && (int)($_GET['tipe'] ?? 0) > 0) $jml[(int)$_GET['tipe']] = 1;

atas_pembeli('Pesan Kasur');
?>
<div class="judul-bar">
  <div><h1>Pesan kasur</h1><p class="redup">Isi jumlah pada kasur yang ingin dipesan. Total dihitung otomatis, dan ringkasan ditampilkan sebelum pesanan disimpan.</p></div>
</div>

<?php if ($err): ?>
  <div class="notif error" role="alert">
    <strong>Pesanan belum bisa dilanjutkan:</strong>
    <ul><?php foreach (array_unique($err) as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" id="form-pembeli" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="preview">

  <section class="panel">
    <h2>1. Pilih kasur</h2>
    <?php if (!$tipeList): ?>
      <div class="notif error">Saat ini belum ada kasur dengan stok tersedia. Silakan coba lagi nanti.</div>
    <?php else: ?>
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead><tr><th>Tipe kasur</th><th>Ukuran</th><th class="kanan">Harga</th><th class="kanan">Stok</th><th>Jumlah</th><th class="kanan">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($tipeList as $t): ?>
          <tr>
            <td><strong><?= e($t['nama']) ?></strong><br><small class="redup"><?= e($t['kode']) ?> · <?= e($t['bahan']) ?>, <?= e($t['ketebalan']) ?></small></td>
            <td><?= e($t['ukuran']) ?></td>
            <td class="kanan"><?= e(rupiah($t['harga'])) ?></td>
            <td class="kanan"><?= (int)$t['stok'] ?></td>
            <td>
              <input type="number" class="qty" name="jumlah[<?= (int)$t['id'] ?>]" min="0" max="<?= (int)$t['stok'] ?>"
                     value="<?= (int)($jml[$t['id']] ?? 0) ?>" data-harga="<?= (int)$t['harga'] ?>"
                     aria-label="Jumlah <?= e($t['nama'] . ' ' . $t['ukuran']) ?>">
            </td>
            <td class="kanan sub">Rp 0</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="total-live">Total sementara <strong id="total-sementara">Rp 0</strong></p>
    <?php endif; ?>
  </section>

  <section class="panel grid-form">
    <h2>2. Pengiriman dan pembayaran</h2>
    <label>Pemesan <input value="<?= e($pel['nama'] . ' · ' . $pel['telepon']) ?>" disabled></label>
    <label>Metode pembayaran
      <select name="metode" required>
        <option value="">Pilih metode pembayaran</option>
        <?php foreach (METODE_PEMBELI as $m): ?>
          <option value="<?= e($m) ?>" <?= $metode === $m ? 'selected' : '' ?>><?= e($m) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="lebar">Alamat pengiriman <input name="alamat" maxlength="255" value="<?= e($alamat) ?>" required></label>
  </section>

  <div class="aksi">
    <a class="btn ghost" href="katalog.php">Kembali ke katalog</a>
    <a class="btn ghost" href="pesanan_saya.php">Pesanan saya</a>
    <button class="btn primer" type="submit"<?= $tipeList ? '' : ' disabled' ?>>Lihat ringkasan pesanan</button>
  </div>
</form>

<script>
(function () {
  var form = document.getElementById('form-pembeli');
  if (!form) return;
  function rp(n) { return 'Rp ' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
  function hitung() {
    var total = 0;
    form.querySelectorAll('.qty').forEach(function (q) {
      var j = Math.max(0, parseInt(q.value, 10) || 0);
      var sub = j * (parseInt(q.dataset.harga, 10) || 0);
      total += sub;
      q.closest('tr').querySelector('.sub').textContent = rp(sub);
    });
    var t = document.getElementById('total-sementara');
    if (t) t.textContent = rp(total);
  }
  form.addEventListener('input', hitung);
  hitung();
})();
</script>
<?php bawah_pembeli();