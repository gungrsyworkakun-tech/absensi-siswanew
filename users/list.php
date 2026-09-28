<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
$pageTitle = 'Kelola Akun';

// Tambah akun
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $nama     = trim($_POST['nama']);
    $role     = $_POST['role'];
    $siswa_id = $_POST['siswa_id'] ?: null;

    $cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $cek->execute([$username]);

    if ($cek->fetch()) {
        setFlash('error', 'Username sudah digunakan.');
    } elseif (strlen($password) < 6) {
        setFlash('error', 'Password minimal 6 karakter.');
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, nama, role, siswa_id) VALUES (?,?,?,?,?)");
        $stmt->execute([$username, $hash, $nama, $role, $role === 'siswa' ? $siswa_id : null]);
        setFlash('success', 'Akun berhasil dibuat.');
    }
    redirect('users/list.php');
}

// Hapus akun
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    if ($idHapus === (int)currentUser()['id']) {
        setFlash('error', 'Anda tidak dapat menghapus akun sendiri.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$idHapus]);
        setFlash('success', 'Akun berhasil dihapus.');
    }
    redirect('users/list.php');
}

$data = $pdo->query("
    SELECT u.*, s.nama_lengkap AS nama_siswa, k.nama_kelas AS kelas_diampu
    FROM users u
    LEFT JOIN siswa s ON u.siswa_id = s.id
    LEFT JOIN kelas k ON k.wali_kelas_user_id = u.id
    ORDER BY FIELD(u.role,'admin','wali_kelas','guru','siswa'), u.nama
")->fetchAll();

$siswaBelumPunyaAkun = $pdo->query("
    SELECT s.id, s.nis, s.nama_lengkap FROM siswa s
    WHERE s.id NOT IN (SELECT siswa_id FROM users WHERE siswa_id IS NOT NULL)
    ORDER BY s.nama_lengkap
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><i class="bi bi-person-gear me-2"></i>Kelola Akun</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-plus-lg"></i> Tambah Akun</button>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>#</th><th>Username</th><th>Nama</th><th>Role</th><th>Keterangan</th><th class="text-end">Aksi</th></tr>
      </thead>
      <tbody>
        <?php
        $roleColor = ['admin'=>'bad','wali_kelas'=>'gps','guru'=>'brand','siswa'=>'info'];
        $roleLabel = ['admin'=>'Admin','wali_kelas'=>'Wali Kelas','guru'=>'Guru','siswa'=>'Siswa'];
        ?>
        <?php foreach ($data as $i => $u): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= clean($u['username']) ?></td>
          <td class="fw-semibold"><?= clean($u['nama']) ?></td>
          <td><span class="badge-soft <?= $roleColor[$u['role']] ?? 'brand' ?>"><?= $roleLabel[$u['role']] ?? clean($u['role']) ?></span></td>
          <td>
            <?php if ($u['role'] === 'siswa'): ?>
              <?= clean($u['nama_siswa'] ?? '-') ?>
            <?php elseif ($u['role'] === 'wali_kelas'): ?>
              <?= $u['kelas_diampu'] ? 'Kelas ' . clean($u['kelas_diampu']) : '<span class="text-muted">Belum dihubungkan ke kelas</span>' ?>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
          <td class="text-end">
            <?php if ((int)$u['id'] !== (int)currentUser()['id']): ?>
              <a href="?hapus=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus akun ini?');"><i class="bi bi-trash"></i></a>
            <?php else: ?>
              <span class="badge bg-light text-dark border">Akun Anda</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tambah Akun -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="aksi" value="tambah">
        <div class="modal-header"><h6 class="modal-title">Tambah Akun</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Nama Lengkap</label><input type="text" name="nama" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required></div>
          <div class="mb-2">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" id="roleSelect">
              <option value="guru">Guru</option>
              <option value="wali_kelas">Wali Kelas</option>
              <option value="admin">Admin</option>
              <option value="siswa">Siswa</option>
            </select>
          </div>
          <div class="mb-2" id="siswaField" style="display:none;">
            <label class="form-label">Hubungkan ke Data Siswa</label>
            <select name="siswa_id" class="form-select">
              <option value="">- Pilih Siswa -</option>
              <?php foreach ($siswaBelumPunyaAkun as $s): ?>
                <option value="<?= $s['id'] ?>"><?= clean($s['nama_lengkap']) ?> (<?= clean($s['nis']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Hanya siswa yang belum memiliki akun yang ditampilkan.</small>
          </div>
          <div class="alert alert-light border small mb-0" id="waliNote" style="display:none;">
            Setelah akun Wali Kelas dibuat, hubungkan ke kelasnya lewat menu <strong>Data Kelas</strong> &rarr; Edit Kelas.
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('roleSelect').addEventListener('change', function () {
  document.getElementById('siswaField').style.display = this.value === 'siswa' ? 'block' : 'none';
  document.getElementById('waliNote').style.display = this.value === 'wali_kelas' ? 'block' : 'none';
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
