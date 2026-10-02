<?php
/**
 * Kelola Token Registrasi (khusus admin / superadmin)
 * Letakkan di: <project>/token/list.php
 */

// ===== BAGIAN PEMBUKA: samakan dengan baris pembuka di users/list.php =====
// (file yang menyediakan $pdo, BASE_URL, currentUser(), clean())
require_once __DIR__ . '/../includes/auth.php';
// ===========================================================================

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$me = currentUser();
if (($me['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Alamat halaman register (relatif dari root website). Sesuaikan jika folder register Anda berbeda.
$LINK_REGISTER = '/register/';

/* ---------- Helper ---------- */
function buatToken(string $role): string {
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $x = '';
    for ($i = 0; $i < 8; $i++) $x .= $abc[random_int(0, strlen($abc) - 1)];
    return ($role === 'guru' ? 'GURU' : 'SISWA') . '-' . substr($x, 0, 4) . '-' . substr($x, 4, 4);
}

function statusToken(array $t): array {
    if (!(int)$t['aktif']) return ['Nonaktif', 'secondary'];
    if ($t['kadaluarsa'] !== null && $t['kadaluarsa'] < date('Y-m-d')) return ['Kedaluwarsa', 'danger'];
    if ((int)$t['jumlah_terpakai'] >= (int)$t['maks_pakai']) return ['Habis', 'dark'];
    return ['Aktif', 'success'];
}

if (empty($_SESSION['tok_csrf'])) $_SESSION['tok_csrf'] = bin2hex(random_bytes(32));

/* ---------- Aksi (POST → redirect) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = hash_equals($_SESSION['tok_csrf'], (string)($_POST['csrf'] ?? ''));
    $aksi = $_POST['aksi'] ?? '';
    $msg = ['danger', 'Sesi tidak valid, muat ulang halaman.'];

    if ($ok && $aksi === 'buat') {
        $role   = ($_POST['role'] ?? '') === 'guru' ? 'guru' : 'siswa';
        $jumlah = max(1, min(100, (int)($_POST['jumlah'] ?? 1)));
        $maks   = max(1, min(1000, (int)($_POST['maks_pakai'] ?? 1)));
        $kdl    = trim((string)($_POST['kadaluarsa'] ?? ''));
        $kdl    = ($kdl !== '' && DateTime::createFromFormat('Y-m-d', $kdl) && $kdl >= date('Y-m-d')) ? $kdl : null;
        $catat  = mb_substr(trim((string)($_POST['catatan'] ?? '')), 0, 100);
        $kelas  = null;
        if ($role === 'siswa' && (int)($_POST['kelas_id'] ?? 0) > 0) {
            $c = $pdo->prepare('SELECT id FROM kelas WHERE id = ?');
            $c->execute([(int)$_POST['kelas_id']]);
            $kelas = $c->fetchColumn() ?: null;
        }

        $baru = [];
        $ins = $pdo->prepare('INSERT INTO token_registrasi (token, role, kelas_id, catatan, maks_pakai, kadaluarsa, dibuat_oleh)
                              VALUES (?,?,?,?,?,?,?)');
        for ($i = 0; $i < $jumlah; $i++) {
            for ($coba = 0; $coba < 5; $coba++) {
                $tk = buatToken($role);
                try {
                    $ins->execute([$tk, $role, $kelas, $catat ?: null, $maks, $kdl, $me['id'] ?? null]);
                    $baru[] = $tk;
                    break;
                } catch (PDOException $ex) {
                    if ($ex->getCode() !== '23000') throw $ex; // selain duplikat → lempar
                }
            }
        }
        $_SESSION['tok_baru'] = $baru;
        $msg = ['success', count($baru) . ' token ' . $role . ' berhasil dibuat.'];

    } elseif ($ok && $aksi === 'toggle') {
        $pdo->prepare('UPDATE token_registrasi SET aktif = 1 - aktif WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        $msg = ['success', 'Status token diperbarui.'];

    } elseif ($ok && $aksi === 'hapus') {
        $pdo->prepare('DELETE FROM token_registrasi WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        $msg = ['success', 'Token dihapus.'];
    }

    $_SESSION['tok_msg'] = $msg;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . (isset($_GET['role']) ? '?role=' . urlencode($_GET['role']) : ''));
    exit;
}

$flash  = $_SESSION['tok_msg']  ?? null; unset($_SESSION['tok_msg']);
$tokBaru = $_SESSION['tok_baru'] ?? [];  unset($_SESSION['tok_baru']);

/* ---------- Data ---------- */
$filter = in_array($_GET['role'] ?? '', ['siswa', 'guru'], true) ? $_GET['role'] : '';
$sql = 'SELECT t.*, k.nama_kelas FROM token_registrasi t LEFT JOIN kelas k ON k.id = t.kelas_id'
     . ($filter ? ' WHERE t.role = ?' : '') . ' ORDER BY t.id DESC LIMIT 200';
$st = $pdo->prepare($sql);
$st->execute($filter ? [$filter] : []);
$tokens = $st->fetchAll();

$kelasList = $pdo->query('SELECT id, nama_kelas, tahun_ajaran FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();

$q = $pdo->prepare('SELECT COUNT(*) FROM token_registrasi WHERE aktif = 1 AND jumlah_terpakai < maks_pakai AND (kadaluarsa IS NULL OR kadaluarsa >= ?)');
$q->execute([date('Y-m-d')]);
$jmlAktif = (int)$q->fetchColumn();
$pakaiSiswa = (int)$pdo->query("SELECT COUNT(*) FROM token_pemakaian WHERE role = 'siswa'")->fetchColumn();
$pakaiGuru  = (int)$pdo->query("SELECT COUNT(*) FROM token_pemakaian WHERE role = 'guru'")->fetchColumn();

$riwayat = $pdo->query('SELECT p.nama, p.role, p.created_at, t.token FROM token_pemakaian p
                        JOIN token_registrasi t ON t.id = p.token_id ORDER BY p.id DESC LIMIT 15')->fetchAll();

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$linkFull = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $LINK_REGISTER;

$pageTitle = 'Token Registrasi';
include __DIR__ . '/../includes/header.php';   // ← sesuaikan jika lokasi header berbeda
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-0 fw-bold"><i class="bi bi-key-fill me-2"></i>Token Registrasi</h4>
    <div class="text-muted small">Siswa &amp; guru wajib memasukkan token sebelum bisa mendaftar akun sendiri.</div>
  </div>
  <div class="input-group" style="max-width:420px">
    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
    <input type="text" class="form-control form-control-sm" id="linkReg" value="<?= clean($linkFull) ?>" readonly>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="salin(document.getElementById('linkReg').value, this)">Salin link</button>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= clean($flash[0]) ?> py-2"><?= clean($flash[1]) ?></div>
<?php endif; ?>

<?php if ($tokBaru): ?>
  <div class="card border-warning mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <b><i class="bi bi-stars me-1"></i>Token baru (bagikan ke yang bersangkutan)</b>
        <button class="btn btn-sm btn-warning" type="button" onclick="salin(document.getElementById('tokBaru').innerText, this)">Salin semua</button>
      </div>
      <pre id="tokBaru" class="mb-0 fs-6" style="font-family:ui-monospace,Menlo,Consolas,monospace"><?= clean(implode("\n", $tokBaru)) ?></pre>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-4"><div class="card h-100"><div class="card-body py-3"><div class="text-muted small">Token aktif</div><div class="fs-3 fw-bold"><?= $jmlAktif ?></div></div></div></div>
  <div class="col-4"><div class="card h-100"><div class="card-body py-3"><div class="text-muted small">Siswa mendaftar</div><div class="fs-3 fw-bold"><?= $pakaiSiswa ?></div></div></div></div>
  <div class="col-4"><div class="card h-100"><div class="card-body py-3"><div class="text-muted small">Guru mendaftar</div><div class="fs-3 fw-bold"><?= $pakaiGuru ?></div></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-header fw-bold"><i class="bi bi-plus-circle me-1"></i>Buat Token Baru</div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="csrf" value="<?= clean($_SESSION['tok_csrf']) ?>">
      <input type="hidden" name="aksi" value="buat">
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Untuk</label>
        <select name="role" id="selRole" class="form-select">
          <option value="siswa">Siswa</option>
          <option value="guru">Guru</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Jumlah token</label>
        <input type="number" name="jumlah" class="form-control" value="1" min="1" max="100">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Bisa dipakai berapa orang / token</label>
        <input type="number" name="maks_pakai" class="form-control" value="1" min="1" max="1000">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Berlaku sampai (opsional)</label>
        <input type="date" name="kadaluarsa" class="form-control" min="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-6" id="boxKelas">
        <label class="form-label small fw-semibold">Kunci ke kelas (opsional, khusus siswa)</label>
        <select name="kelas_id" class="form-select">
          <option value="0">Siswa bebas memilih kelas</option>
          <?php foreach ($kelasList as $k): ?>
            <option value="<?= (int)$k['id'] ?>"><?= clean($k['nama_kelas']) ?> (<?= clean($k['tahun_ajaran']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Catatan (opsional)</label>
        <input type="text" name="catatan" class="form-control" maxlength="100" placeholder="mis. Siswa baru 2026 / Guru honorer">
      </div>
      <div class="col-12 d-flex align-items-center gap-3">
        <button class="btn btn-primary" type="submit"><i class="bi bi-key me-1"></i>Buat Token</button>
        <span class="text-muted small">Tips: 1 token &times; 40 pemakai = satu token bersama untuk satu kelas. 40 token &times; 1 pemakai = token pribadi per orang.</span>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <b><i class="bi bi-list-ul me-1"></i>Daftar Token</b>
    <div class="btn-group btn-group-sm">
      <a class="btn btn-outline-secondary <?= $filter === '' ? 'active' : '' ?>" href="?">Semua</a>
      <a class="btn btn-outline-secondary <?= $filter === 'siswa' ? 'active' : '' ?>" href="?role=siswa">Siswa</a>
      <a class="btn btn-outline-secondary <?= $filter === 'guru' ? 'active' : '' ?>" href="?role=guru">Guru</a>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Token</th><th>Untuk</th><th>Terpakai</th><th>Berlaku s/d</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php if (!$tokens): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada token.</td></tr>
      <?php endif; ?>
      <?php foreach ($tokens as $t): [$lbl, $warna] = statusToken($t); ?>
        <tr>
          <td>
            <code class="fs-6 fw-bold"><?= clean($t['token']) ?></code>
            <?php if ($t['catatan']): ?><div class="small text-muted"><?= clean($t['catatan']) ?></div><?php endif; ?>
          </td>
          <td>
            <span class="badge bg-<?= $t['role'] === 'guru' ? 'info text-dark' : 'primary' ?>"><?= $t['role'] === 'guru' ? 'Guru' : 'Siswa' ?></span>
            <?php if ($t['nama_kelas']): ?><div class="small text-muted"><?= clean($t['nama_kelas']) ?></div><?php endif; ?>
          </td>
          <td><?= (int)$t['jumlah_terpakai'] ?> / <?= (int)$t['maks_pakai'] ?></td>
          <td><?= $t['kadaluarsa'] ? clean(date('d M Y', strtotime($t['kadaluarsa']))) : '<span class="text-muted">Tanpa batas</span>' ?></td>
          <td><span class="badge bg-<?= $warna ?>"><?= $lbl ?></span></td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" type="button" title="Salin token" onclick="salin('<?= clean($t['token']) ?>', this)"><i class="bi bi-clipboard"></i></button>
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf" value="<?= clean($_SESSION['tok_csrf']) ?>">
              <input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn-sm btn-outline-warning" title="<?= (int)$t['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>"><i class="bi bi-<?= (int)$t['aktif'] ? 'pause-circle' : 'play-circle' ?>"></i></button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus token ini? Riwayat pemakaiannya ikut terhapus. Akun yang sudah dibuat tidak terpengaruh.')">
              <input type="hidden" name="csrf" value="<?= clean($_SESSION['tok_csrf']) ?>">
              <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header fw-bold"><i class="bi bi-clock-history me-1"></i>Pendaftar Terakhir</div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Waktu</th><th>Nama</th><th>Peran</th><th>Token</th></tr></thead>
      <tbody>
      <?php if (!$riwayat): ?><tr><td colspan="4" class="text-center text-muted py-3">Belum ada yang mendaftar lewat token.</td></tr><?php endif; ?>
      <?php foreach ($riwayat as $r): ?>
        <tr>
          <td><?= clean(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
          <td><?= clean($r['nama']) ?></td>
          <td><?= $r['role'] === 'guru' ? 'Guru' : 'Siswa' ?></td>
          <td><code><?= clean($r['token']) ?></code></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function salin(teks, btn) {
  function ok() {
    var asal = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i> Tersalin';
    setTimeout(function () { btn.innerHTML = asal; }, 1400);
  }
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(teks).then(ok);
  } else {
    var ta = document.createElement('textarea'); ta.value = teks; document.body.appendChild(ta);
    ta.select(); document.execCommand('copy'); document.body.removeChild(ta); ok();
  }
}
var selRole = document.getElementById('selRole'), boxKelas = document.getElementById('boxKelas');
selRole.addEventListener('change', function () { boxKelas.style.display = this.value === 'siswa' ? '' : 'none'; });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>