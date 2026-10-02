<?php
/**
 * Registrasi mandiri Siswa & Guru
 * - Pengguna mengisi data + memilih username
 * - Password dibuat otomatis (acak) lalu di-hash (bcrypt) ke tabel users
 * - Data masuk ke tabel users + siswa / guru_profil
 * - Username & password dikirim ke email pengguna
 */
session_start();

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/* ---------- Helper ---------- */
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function post($k) { return trim((string)($_POST[$k] ?? '')); }

function nullIfEmpty($v) { return $v === '' ? null : $v; }

function generatePassword(int $len = 10): string {
    // tanpa karakter membingungkan (0/O, 1/l/I)
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnpqrstuvwxyz';
    $digit = '23456789';
    $all   = $upper . $lower . $digit;
    $pw = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digit[random_int(0, strlen($digit) - 1)],
    ];
    while (count($pw) < $len) {
        $pw[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($pw) - 1; $i > 0; $i--) { // shuffle aman
        $j = random_int(0, $i);
        [$pw[$i], $pw[$j]] = [$pw[$j], $pw[$i]];
    }
    return implode('', $pw);
}

function kirimEmailAkun(array $cfg, string $tujuan, string $nama, string $role, string $username, string $password): void {
    $m = new PHPMailer(true); // true = lempar exception jika gagal
    $m->isSMTP();
    $m->Host       = $cfg['mail']['host'];
    $m->SMTPAuth   = true;
    $m->Username   = $cfg['mail']['user'];
    $m->Password   = $cfg['mail']['pass'];
    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port       = $cfg['mail']['port'];
    $m->CharSet    = 'UTF-8';

    $m->setFrom($cfg['mail']['user'], $cfg['mail']['from_name']);
    $m->addAddress($tujuan, $nama);
    $m->isHTML(true);
    $m->Subject = 'Akun ' . $cfg['app_name'] . ' Anda';

    $peran = $role === 'guru' ? 'Guru' : 'Siswa';
    $m->Body = '
    <div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #e5e7eb;border-radius:10px;padding:24px">
      <h2 style="margin-top:0;color:#1e3a8a">Pendaftaran Berhasil</h2>
      <p>Halo <b>' . e($nama) . '</b>, akun ' . e($peran) . ' Anda di <b>' . e($cfg['app_name']) . '</b> sudah aktif.</p>
      <table style="background:#f3f4f6;border-radius:8px;padding:12px;width:100%">
        <tr><td style="padding:4px 8px;color:#6b7280">Username</td><td style="padding:4px 8px"><b>' . e($username) . '</b></td></tr>
        <tr><td style="padding:4px 8px;color:#6b7280">Password</td><td style="padding:4px 8px"><b style="font-family:monospace;font-size:16px">' . e($password) . '</b></td></tr>
      </table>
      <p><a href="' . e($cfg['login_url']) . '" style="display:inline-block;background:#1d4ed8;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">Login Sekarang</a></p>
      <p style="color:#6b7280;font-size:13px">Jaga kerahasiaan password ini dan jangan dibagikan kepada siapa pun.</p>
    </div>';
    $m->AltBody = "Halo $nama,\nAkun $peran Anda sudah aktif.\nUsername: $username\nPassword: $password\nLogin: " . $cfg['login_url'];
    $m->send();
}

function maskEmail(string $email): string {
    [$u, $d] = explode('@', $email, 2);
    return substr($u, 0, 2) . str_repeat('*', max(2, strlen($u) - 2)) . '@' . $d;
}

/* ---------- Koneksi DB ---------- */
try {
    $d = $cfg['db'];
    $pdo = new PDO(
        "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}",
        $d['user'], $d['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $ex) {
    error_log('Register DB error: ' . $ex->getMessage());
    http_response_code(500);
    exit('Tidak dapat terhubung ke database. Hubungi admin sekolah.');
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

$kelasList = $pdo->query("SELECT id, nama_kelas, tahun_ajaran FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$errors  = [];
$success = null;
$role    = $_POST['role'] ?? 'siswa';
if (!in_array($role, ['siswa', 'guru'], true)) $role = 'siswa';

/* ---------- Proses form ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Anti-spam & CSRF
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    }
    if (post('website') !== '') { // honeypot
        exit;
    }
    $now = time();
    $_SESSION['reg_try'] = array_filter($_SESSION['reg_try'] ?? [], fn($t) => $t > $now - 600);
    if (count($_SESSION['reg_try']) >= 5) {
        $errors[] = 'Terlalu banyak percobaan. Silakan tunggu beberapa menit.';
    }

    if (!$errors) {
        $_SESSION['reg_try'][] = $now;

        $nama     = post('nama');
        $username = post('username');
        $email    = strtolower(post('email'));

        // Kode pendaftaran
        if ($cfg['kode_pendaftaran'] !== '' && !hash_equals($cfg['kode_pendaftaran'], post('kode'))) {
            $errors[] = 'Kode pendaftaran salah. Tanyakan kode kepada admin sekolah.';
        }

        // Validasi umum
        if ($nama === '' || mb_strlen($nama) > 100) $errors[] = 'Nama lengkap wajib diisi (maks. 100 karakter).';
        if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $username)) {
            $errors[] = 'Username 4–30 karakter, hanya huruf, angka, titik, atau underscore.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Format email tidak valid.';
        } elseif ($cfg['hanya_gmail'] && !preg_match('/@gmail\.com$/', $email)) {
            $errors[] = 'Gunakan alamat email Gmail (@gmail.com).';
        }
        $jk = post('jenis_kelamin');
        if (!in_array($jk, ['L', 'P'], true)) $errors[] = 'Pilih jenis kelamin.';
        $tglLahir = post('tanggal_lahir');
        if ($tglLahir !== '' && !DateTime::createFromFormat('Y-m-d', $tglLahir)) $errors[] = 'Tanggal lahir tidak valid.';

        // Validasi khusus peran
        $kelasId = null;
        if ($role === 'siswa') {
            $nis = post('nis');
            if (!preg_match('/^[0-9A-Za-z]{1,20}$/', $nis)) $errors[] = 'NIS wajib diisi (maks. 20 karakter).';
            $kelasId = (int)post('kelas_id');
            if (!in_array($kelasId, array_map('intval', array_column($kelasList, 'id')), true)) {
                $errors[] = 'Pilih kelas.';
            }
        } else {
            $nip   = nullIfEmpty(post('nip'));
            $nuptk = nullIfEmpty(post('nuptk'));
            $statusPeg = post('status_kepegawaian');
            if (!in_array($statusPeg, ['PNS', 'PPPK', 'GTY', 'Honorer', 'Lainnya'], true)) $statusPeg = null;
        }

        // Cek duplikat
        if (!$errors) {
            $q = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
            $q->execute([$username]);
            if ($q->fetch()) $errors[] = 'Username sudah dipakai, pilih username lain.';

            $q = $pdo->prepare('SELECT 1 FROM siswa WHERE email = ? UNION SELECT 1 FROM guru_profil WHERE email = ?');
            $q->execute([$email, $email]);
            if ($q->fetch()) $errors[] = 'Email ini sudah terdaftar.';

            if ($role === 'siswa') {
                $q = $pdo->prepare('SELECT 1 FROM siswa WHERE nis = ?');
                $q->execute([$nis]);
                if ($q->fetch()) $errors[] = 'NIS sudah terdaftar. Hubungi admin jika ini keliru.';
            } else {
                if ($nip) {
                    $q = $pdo->prepare('SELECT 1 FROM guru_profil WHERE nip = ?');
                    $q->execute([$nip]);
                    if ($q->fetch()) $errors[] = 'NIP sudah terdaftar.';
                }
            }
        }

        // Simpan + kirim email
        if (!$errors) {
            $passwordPlain = generatePassword(10);
            $hash = password_hash($passwordPlain, PASSWORD_BCRYPT, ['cost' => 12]); // sama dengan format $2y$12$ di DB Anda

            try {
                $pdo->beginTransaction();

                if ($role === 'siswa') {
                    $st = $pdo->prepare(
                        'INSERT INTO siswa (nis, nisn, nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, agama, alamat,
                                            no_hp, email, nama_ayah, nama_ibu, no_hp_ortu, kelas_id, status, tanggal_masuk)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'Aktif\',CURDATE())'
                    );
                    $st->execute([
                        $nis, nullIfEmpty(post('nisn')), $nama, $jk, nullIfEmpty(post('tempat_lahir')),
                        nullIfEmpty($tglLahir), nullIfEmpty(post('agama')), nullIfEmpty(post('alamat')),
                        nullIfEmpty(post('no_hp')), $email, nullIfEmpty(post('nama_ayah')), nullIfEmpty(post('nama_ibu')),
                        nullIfEmpty(post('no_hp_ortu')), $kelasId,
                    ]);
                    $siswaId = (int)$pdo->lastInsertId();

                    $pdo->prepare('INSERT INTO users (username, password, nama, role, siswa_id) VALUES (?,?,?,\'siswa\',?)')
                        ->execute([$username, $hash, $nama, $siswaId]);
                } else {
                    $pdo->prepare('INSERT INTO users (username, password, nama, role, siswa_id) VALUES (?,?,?,\'guru\',NULL)')
                        ->execute([$username, $hash, $nama]);
                    $userId = (int)$pdo->lastInsertId();

                    $pdo->prepare(
                        'INSERT INTO guru_profil (user_id, nip, nuptk, jenis_kelamin, tempat_lahir, tanggal_lahir, agama, alamat,
                                                  no_hp, email, pendidikan_terakhir, bidang_keahlian, jabatan, status_kepegawaian, tanggal_masuk)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE())'
                    )->execute([
                        $userId, $nip, $nuptk, $jk, nullIfEmpty(post('tempat_lahir')), nullIfEmpty($tglLahir),
                        nullIfEmpty(post('agama')), nullIfEmpty(post('alamat')), nullIfEmpty(post('no_hp')), $email,
                        nullIfEmpty(post('pendidikan_terakhir')), nullIfEmpty(post('bidang_keahlian')),
                        nullIfEmpty(post('jabatan')) ?? 'Guru', $statusPeg,
                    ]);
                }

                // Kirim email SEBELUM commit: jika gagal, data dibatalkan sehingga tidak ada akun "tanpa password"
                kirimEmailAkun($cfg, $email, $nama, $role, $username, $passwordPlain);

                $pdo->commit();
                $success = maskEmail($email);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $_POST = [];
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Register error: ' . $ex->getMessage());
                $errors[] = 'Pendaftaran gagal: email tidak dapat dikirim atau data tidak dapat disimpan. Pastikan email benar, lalu coba lagi atau hubungi admin.';
            }
        }
    }
}

function old($k, $d = '') { return e($_POST[$k] ?? $d); }
function sel($k, $v) { return (($_POST[$k] ?? '') === (string)$v) ? 'selected' : ''; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Daftar Akun - <?= e($cfg['app_name']) ?></title>
<style>
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#eef2ff;color:#111827}
  .wrap{max-width:720px;margin:32px auto;padding:0 16px}
  .card{background:#fff;border-radius:14px;padding:28px;box-shadow:0 4px 24px rgba(30,58,138,.08)}
  h1{margin:0 0 4px;font-size:22px;color:#1e3a8a}
  .sub{color:#6b7280;margin:0 0 20px;font-size:14px}
  .tabs{display:flex;gap:8px;margin-bottom:20px}
  .tabs label{flex:1;text-align:center;padding:10px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;font-weight:600}
  .tabs input{display:none}
  .tabs input:checked+span{color:#1d4ed8}
  .tabs label:has(input:checked){border-color:#1d4ed8;background:#eff6ff}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .full{grid-column:1/-1}
  label.f{display:block;font-size:13px;font-weight:600;margin-bottom:4px}
  input[type=text],input[type=email],input[type=date],select,textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font:inherit}
  input:focus,select:focus,textarea:focus{outline:2px solid #93c5fd;border-color:#3b82f6}
  .req{color:#dc2626}
  h3{margin:22px 0 10px;font-size:15px;color:#1e3a8a;border-bottom:1px solid #e5e7eb;padding-bottom:6px}
  button{width:100%;margin-top:22px;padding:12px;background:#1d4ed8;color:#fff;border:0;border-radius:10px;font-size:16px;font-weight:600;cursor:pointer}
  button:hover{background:#1e40af}
  .err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:14px}
  .err ul{margin:0;padding-left:18px}
  .ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:20px;text-align:center}
  .ok a{color:#1d4ed8;font-weight:600}
  .hint{font-size:12px;color:#6b7280;margin-top:3px}
  .hp{position:absolute;left:-9999px}
  .only-guru{display:none}
  body.is-guru .only-guru{display:block}
  body.is-guru .only-siswa{display:none}
  @media(max-width:600px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body class="<?= $role === 'guru' ? 'is-guru' : '' ?>">
<div class="wrap"><div class="card">
  <h1>Daftar Akun</h1>
  <p class="sub"><?= e($cfg['app_name']) ?> — password dibuat otomatis dan dikirim ke email Anda.</p>

<?php if ($success): ?>
  <div class="ok">
    <h2 style="margin-top:0">Pendaftaran Berhasil 🎉</h2>
    <p>Username dan password sudah dikirim ke <b><?= e($success) ?></b>.<br>Cek Inbox (atau folder Spam).</p>
    <p><a href="<?= e($cfg['login_url']) ?>">Ke halaman login</a></p>
  </div>
<?php else: ?>

  <?php if ($errors): ?>
    <div class="err"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" autocomplete="off" novalidate>
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">

    <div class="tabs">
      <label><input type="radio" name="role" value="siswa" <?= $role === 'siswa' ? 'checked' : '' ?>><span>🎓 Siswa</span></label>
      <label><input type="radio" name="role" value="guru"  <?= $role === 'guru' ? 'checked' : '' ?>><span>👩‍🏫 Guru</span></label>
    </div>

    <h3>Data Akun</h3>
    <div class="grid">
      <div class="full">
        <label class="f">Nama Lengkap <span class="req">*</span></label>
        <input type="text" name="nama" maxlength="100" value="<?= old('nama') ?>" required>
      </div>
      <div>
        <label class="f">Username <span class="req">*</span></label>
        <input type="text" name="username" maxlength="30" value="<?= old('username') ?>" required>
        <div class="hint">4–30 karakter: huruf, angka, titik, underscore</div>
      </div>
      <div>
        <label class="f">Email<?= $cfg['hanya_gmail'] ? ' Gmail' : '' ?> <span class="req">*</span></label>
        <input type="email" name="email" maxlength="100" value="<?= old('email') ?>" placeholder="nama@gmail.com" required>
        <div class="hint">Password dikirim ke email ini</div>
      </div>
      <?php if ($cfg['kode_pendaftaran'] !== ''): ?>
      <div class="full">
        <label class="f">Kode Pendaftaran <span class="req">*</span></label>
        <input type="text" name="kode" value="<?= old('kode') ?>" required>
        <div class="hint">Diberikan oleh admin sekolah</div>
      </div>
      <?php endif; ?>
    </div>

    <h3>Data Pribadi</h3>
    <div class="grid">
      <div>
        <label class="f">Jenis Kelamin <span class="req">*</span></label>
        <select name="jenis_kelamin" required>
          <option value="">-- Pilih --</option>
          <option value="L" <?= sel('jenis_kelamin', 'L') ?>>Laki-laki</option>
          <option value="P" <?= sel('jenis_kelamin', 'P') ?>>Perempuan</option>
        </select>
      </div>
      <div>
        <label class="f">Agama</label>
        <select name="agama">
          <option value="">-- Pilih --</option>
          <?php foreach (['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $ag): ?>
            <option <?= sel('agama', $ag) ?>><?= $ag ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="f">Tempat Lahir</label>
        <input type="text" name="tempat_lahir" maxlength="50" value="<?= old('tempat_lahir') ?>">
      </div>
      <div>
        <label class="f">Tanggal Lahir</label>
        <input type="date" name="tanggal_lahir" value="<?= old('tanggal_lahir') ?>">
      </div>
      <div class="full">
        <label class="f">Alamat</label>
        <textarea name="alamat" rows="2"><?= old('alamat') ?></textarea>
      </div>
      <div>
        <label class="f">No. HP</label>
        <input type="text" name="no_hp" maxlength="20" value="<?= old('no_hp') ?>">
      </div>
    </div>

    <!-- ===== Khusus Siswa ===== -->
    <div class="only-siswa">
      <h3>Data Siswa</h3>
      <div class="grid">
        <div>
          <label class="f">NIS <span class="req">*</span></label>
          <input type="text" name="nis" maxlength="20" value="<?= old('nis') ?>">
        </div>
        <div>
          <label class="f">NISN</label>
          <input type="text" name="nisn" maxlength="20" value="<?= old('nisn') ?>">
        </div>
        <div class="full">
          <label class="f">Kelas <span class="req">*</span></label>
          <select name="kelas_id">
            <option value="">-- Pilih Kelas --</option>
            <?php foreach ($kelasList as $k): ?>
              <option value="<?= (int)$k['id'] ?>" <?= sel('kelas_id', $k['id']) ?>><?= e($k['nama_kelas']) ?> (<?= e($k['tahun_ajaran']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="f">Nama Ayah</label>
          <input type="text" name="nama_ayah" maxlength="100" value="<?= old('nama_ayah') ?>">
        </div>
        <div>
          <label class="f">Nama Ibu</label>
          <input type="text" name="nama_ibu" maxlength="100" value="<?= old('nama_ibu') ?>">
        </div>
        <div>
          <label class="f">No. HP Orang Tua</label>
          <input type="text" name="no_hp_ortu" maxlength="20" value="<?= old('no_hp_ortu') ?>">
        </div>
      </div>
    </div>

    <!-- ===== Khusus Guru ===== -->
    <div class="only-guru">
      <h3>Data Guru</h3>
      <div class="grid">
        <div>
          <label class="f">NIP</label>
          <input type="text" name="nip" maxlength="30" value="<?= old('nip') ?>">
        </div>
        <div>
          <label class="f">NUPTK</label>
          <input type="text" name="nuptk" maxlength="30" value="<?= old('nuptk') ?>">
        </div>
        <div>
          <label class="f">Pendidikan Terakhir</label>
          <input type="text" name="pendidikan_terakhir" maxlength="50" value="<?= old('pendidikan_terakhir') ?>" placeholder="S1 Pendidikan">
        </div>
        <div>
          <label class="f">Bidang Keahlian</label>
          <input type="text" name="bidang_keahlian" maxlength="100" value="<?= old('bidang_keahlian') ?>">
        </div>
        <div>
          <label class="f">Jabatan</label>
          <input type="text" name="jabatan" maxlength="100" value="<?= old('jabatan', 'Guru') ?>">
        </div>
        <div>
          <label class="f">Status Kepegawaian</label>
          <select name="status_kepegawaian">
            <option value="">-- Pilih --</option>
            <?php foreach (['PNS','PPPK','GTY','Honorer','Lainnya'] as $s): ?>
              <option <?= sel('status_kepegawaian', $s) ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <button type="submit">Daftar &amp; Kirim Akun ke Email</button>
  </form>

  <script>
    document.querySelectorAll('input[name=role]').forEach(function (r) {
      r.addEventListener('change', function () {
        document.body.classList.toggle('is-guru', this.value === 'guru');
      });
    });
  </script>
<?php endif; ?>
</div></div>
</body>
</html>
