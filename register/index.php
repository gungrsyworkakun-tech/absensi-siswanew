<?php
/**
 * Registrasi mandiri Siswa & Guru — dengan TOKEN dari admin.
 * Langkah 1: masukkan token → Langkah 2: isi data → Langkah 3: akun dikirim ke email.
 * Peran (siswa/guru) ditentukan oleh TOKEN, bukan oleh pilihan pengguna.
 */
date_default_timezone_set('Asia/Makassar');
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
function old($k, $d = '') { return e($_POST[$k] ?? $d); }
function sel($k, $v) { return (($_POST[$k] ?? '') === (string)$v) ? 'selected' : ''; }

function generatePassword(int $len = 10): string {
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnpqrstuvwxyz';
    $digit = '23456789';
    $all   = $upper . $lower . $digit;
    $pw = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digit[random_int(0, strlen($digit) - 1)],
    ];
    while (count($pw) < $len) $pw[] = $all[random_int(0, strlen($all) - 1)];
    for ($i = count($pw) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$pw[$i], $pw[$j]] = [$pw[$j], $pw[$i]];
    }
    return implode('', $pw);
}

// Link di email harus berupa alamat lengkap; jika login_url berupa path, lengkapi dengan domain yang dipakai.
function urlAbsolut(string $u): string {
    if (preg_match('#^https?://#i', $u)) return $u;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https' : 'http') . '://' . $host . '/' . ltrim($u, '/');
}

function maskEmail(string $email): string {
    [$u, $d] = explode('@', $email, 2);
    return substr($u, 0, 2) . str_repeat('*', max(2, strlen($u) - 2)) . '@' . $d;
}

function kirimEmailAkun(array $cfg, string $tujuan, string $nama, string $role, string $username, string $password): void {
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = $cfg['mail']['host'];
    $m->SMTPAuth   = true;
    $m->Username   = $cfg['mail']['user'];
    $m->Password   = str_replace(' ', '', (string)$cfg['mail']['pass']);
    $m->Timeout    = 20;
    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port       = $cfg['mail']['port'];
    $m->CharSet    = 'UTF-8';

    $m->setFrom($cfg['mail']['user'], $cfg['mail']['from_name']);
    $m->addAddress($tujuan, $nama);
    $m->isHTML(true);
    $m->Subject = 'Akun ' . $cfg['app_name'] . ' Anda';

    $peran = $role === 'guru' ? 'Guru' : 'Siswa';
    $link  = urlAbsolut($cfg['login_url']);
    $namaE = e($nama); $userE = e($username); $passE = e($password);
    $appE  = e($cfg['app_name']); $peranE = e($peran); $linkE = e($link);
    $m->Body = <<<HTML
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#eef1f7;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">Akun {$peranE} Anda sudah aktif. Berikut username dan password untuk login.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#eef1f7">
<tr><td align="center" style="padding:28px 12px;">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">

    <tr><td bgcolor="#131A2E" style="background:#131A2E;padding:26px 32px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
        <td width="46" height="46" align="center" valign="middle" bgcolor="#F4B740" style="background:#F4B740;border-radius:12px;font-size:24px;line-height:46px;">&#127891;</td>
        <td style="padding-left:14px;">
          <div style="color:#ffffff;font-size:20px;font-weight:800;line-height:1.1;">{$appE}</div>
          <div style="color:#9aa3b8;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;padding-top:4px;">Absensi &amp; Akademik</div>
        </td>
      </tr></table>
    </td></tr>
    <tr><td height="4" bgcolor="#F4B740" style="background:#F4B740;font-size:0;line-height:0;">&nbsp;</td></tr>

    <tr><td style="padding:34px 32px 8px 32px;">
      <div style="font-size:24px;font-weight:800;color:#131A2E;line-height:1.25;">Akun Anda sudah aktif &#127881;</div>
      <p style="margin:14px 0 0 0;font-size:15px;line-height:1.65;color:#4b5563;">
        Halo <b style="color:#131A2E;">{$namaE}</b>, selamat bergabung sebagai <b style="color:#131A2E;">{$peranE}</b> di {$appE}.
        Gunakan data berikut untuk masuk ke akun Anda.
      </p>
    </td></tr>

    <tr><td style="padding:20px 32px 6px 32px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f8fafc" style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
        <tr><td style="padding:18px 22px 6px 22px;">
          <div style="font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#6b7280;">Username</div>
          <div style="font-size:18px;font-weight:700;color:#131A2E;padding-top:4px;">{$userE}</div>
        </td></tr>
        <tr><td style="padding:12px 22px 20px 22px;">
          <div style="font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#6b7280;">Password</div>
          <div style="margin-top:6px;display:inline-block;background:#fffbeb;border:1.5px dashed #F4B740;border-radius:10px;padding:10px 16px;font-family:Consolas,'Courier New',monospace;font-size:22px;font-weight:700;letter-spacing:2px;color:#131A2E;">{$passE}</div>
        </td></tr>
      </table>
    </td></tr>

    <tr><td align="center" style="padding:26px 32px 8px 32px;">
      <a href="{$linkE}" style="display:inline-block;background:#F4B740;color:#0B0F1D;font-size:16px;font-weight:800;text-decoration:none;padding:14px 38px;border-radius:12px;">Masuk Sekarang &rarr;</a>
      <div style="font-size:12px;color:#9ca3af;padding-top:14px;line-height:1.5;">Jika tombol tidak berfungsi, salin alamat ini ke browser:<br><a href="{$linkE}" style="color:#6b7280;word-break:break-all;">{$linkE}</a></div>
    </td></tr>

    <tr><td style="padding:22px 32px 30px 32px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fff7ed" style="background:#fff7ed;border-left:4px solid #F4B740;border-radius:8px;">
        <tr><td style="padding:12px 16px;font-size:13px;line-height:1.6;color:#92400e;">
          <b>&#128274; Jaga kerahasiaan akun.</b> Jangan bagikan password ini kepada siapa pun, termasuk teman atau pihak yang mengaku dari sekolah.
        </td></tr>
      </table>
    </td></tr>

    <tr><td bgcolor="#f3f4f6" align="center" style="background:#f3f4f6;padding:18px 32px;font-size:12px;line-height:1.6;color:#9ca3af;">
      Email ini dikirim otomatis oleh sistem {$appE}.<br>Mohon tidak membalas email ini.
    </td></tr>

  </table>
</td></tr>
</table>
</body></html>
HTML;
    $m->AltBody = "Halo $nama,\nAkun $peran Anda sudah aktif.\nUsername: $username\nPassword: $password\nLogin: " . $link;
    $m->send();
}

/* ---------- Helper token ---------- */
function cariToken(PDO $pdo, string $input): ?array {
    $norm = preg_replace('/[^A-Z0-9]/', '', strtoupper($input));
    if ($norm === '') return null;
    $q = $pdo->prepare("SELECT t.*, k.nama_kelas FROM token_registrasi t
                        LEFT JOIN kelas k ON k.id = t.kelas_id
                        WHERE REPLACE(t.token, '-', '') = ? LIMIT 1");
    $q->execute([$norm]);
    return $q->fetch() ?: null;
}

function tokenById(PDO $pdo, int $id): ?array {
    $q = $pdo->prepare("SELECT t.*, k.nama_kelas FROM token_registrasi t
                        LEFT JOIN kelas k ON k.id = t.kelas_id WHERE t.id = ? LIMIT 1");
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

// null = token valid; selain itu = alasan penolakan
function alasanTokenTidakValid(array $t): ?string {
    if (!(int)$t['aktif']) return 'Token ini sudah dinonaktifkan oleh admin.';
    if ($t['kadaluarsa'] !== null && $t['kadaluarsa'] < date('Y-m-d')) return 'Token ini sudah kedaluwarsa.';
    if ((int)$t['jumlah_terpakai'] >= (int)$t['maks_pakai']) return 'Token ini sudah habis dipakai.';
    return null;
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

try {
    $pdo->query('SELECT 1 FROM token_registrasi LIMIT 1');
} catch (PDOException $ex) {
    error_log('Register: tabel token belum ada: ' . $ex->getMessage());
    http_response_code(500);
    exit('Fitur token belum aktif. Admin perlu menjalankan migrasi_token.sql terlebih dahulu.');
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

$kelasList = $pdo->query("SELECT id, nama_kelas, tahun_ajaran FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$selfUrl   = strtok($_SERVER['REQUEST_URI'], '?');

$errors   = [];
$success  = null;
$tokenRow = null;

if (isset($_GET['reset'])) { // "Ganti token"
    unset($_SESSION['reg_token_id']);
    header('Location: ' . $selfUrl);
    exit;
}

// Token yang sudah diverifikasi pada langkah 1 (disimpan di sesi)
if (!empty($_SESSION['reg_token_id'])) {
    $tokenRow = tokenById($pdo, (int)$_SESSION['reg_token_id']);
    $alasan   = $tokenRow ? alasanTokenTidakValid($tokenRow) : 'Token tidak ditemukan.';
    if ($alasan) {
        unset($_SESSION['reg_token_id']);
        $tokenRow = null;
        $errors[] = $alasan . ' Masukkan token yang masih berlaku.';
    }
}
$role = $tokenRow['role'] ?? null; // peran SELALU dari token

/* ---------- Proses form ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (post('website') !== '') exit; // honeypot

    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $now = time();
        $_SESSION['reg_try'] = array_values(array_filter($_SESSION['reg_try'] ?? [], fn($t) => $t > $now - 600));

        if (count($_SESSION['reg_try']) >= (int)($cfg['maks_percobaan'] ?? 10)) {
            $errors[] = 'Terlalu banyak percobaan. Silakan tunggu beberapa menit.';

        /* ===== LANGKAH 1: verifikasi token ===== */
        } elseif (post('aksi') === 'cek_token' && !$tokenRow) {
            $t      = cariToken($pdo, post('token'));
            $alasan = $t ? alasanTokenTidakValid($t) : 'Token tidak ditemukan. Periksa kembali penulisannya.';
            if ($alasan) {
                usleep(700000); // perlambat tebak-tebakan token
                $_SESSION['reg_try'][] = $now;
                $errors[] = $alasan;
            } else {
                $_SESSION['reg_token_id'] = (int)$t['id'];
                header('Location: ' . $selfUrl);
                exit;
            }

        /* ===== LANGKAH 2: pendaftaran ===== */
        } elseif (post('aksi') === 'daftar' && $tokenRow) {

            $nama     = post('nama');
            $username = post('username');
            $email    = strtolower(post('email'));

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

            $kelasId = null;
            if ($role === 'siswa') {
                $nis = post('nis');
                if (!preg_match('/^[0-9A-Za-z]{1,20}$/', $nis)) $errors[] = 'NIS wajib diisi (maks. 20 karakter).';
                if (!empty($tokenRow['kelas_id'])) {
                    $kelasId = (int)$tokenRow['kelas_id']; // kelas dikunci oleh token
                } else {
                    $kelasId = (int)post('kelas_id');
                    if (!in_array($kelasId, array_map('intval', array_column($kelasList, 'id')), true)) $errors[] = 'Pilih kelas.';
                }
            } else {
                $nip   = nullIfEmpty(post('nip'));
                $nuptk = nullIfEmpty(post('nuptk'));
                $statusPeg = post('status_kepegawaian');
                if (!in_array($statusPeg, ['PNS', 'PPPK', 'GTY', 'Honorer', 'Lainnya'], true)) $statusPeg = null;
            }

            if (!$errors) {
                $q = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
                $q->execute([$username]);
                if ($q->fetch()) $errors[] = 'Username sudah dipakai, pilih username lain.';

                if (!($cfg['izinkan_email_ganda'] ?? true)) {
                    $q = $pdo->prepare('SELECT 1 FROM siswa WHERE email = ? UNION SELECT 1 FROM guru_profil WHERE email = ?');
                    $q->execute([$email, $email]);
                    if ($q->fetch()) $errors[] = 'Email ini sudah terdaftar.';
                }
                if ($role === 'siswa') {
                    $q = $pdo->prepare('SELECT 1 FROM siswa WHERE nis = ?');
                    $q->execute([$nis]);
                    if ($q->fetch()) $errors[] = 'NIS sudah terdaftar. Hubungi admin jika ini keliru.';
                } elseif ($nip) {
                    $q = $pdo->prepare('SELECT 1 FROM guru_profil WHERE nip = ?');
                    $q->execute([$nip]);
                    if ($q->fetch()) $errors[] = 'NIP sudah terdaftar.';
                }
            }

            if (!$errors) {
                $_SESSION['reg_try'][] = $now;
                $passwordPlain = generatePassword(10);
                $hash = password_hash($passwordPlain, PASSWORD_BCRYPT, ['cost' => 12]);

                try {
                    $pdo->beginTransaction();

                    // Pakai 1 kuota token secara atomik (aman dari pendaftar bersamaan)
                    $u = $pdo->prepare('UPDATE token_registrasi SET jumlah_terpakai = jumlah_terpakai + 1
                                        WHERE id = ? AND aktif = 1 AND jumlah_terpakai < maks_pakai
                                          AND (kadaluarsa IS NULL OR kadaluarsa >= ?)');
                    $u->execute([$tokenRow['id'], date('Y-m-d')]);
                    if ($u->rowCount() !== 1) throw new RuntimeException('TOKEN_HABIS');

                    if ($role === 'siswa') {
                        $pdo->prepare(
                            'INSERT INTO siswa (nis, nisn, nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, agama, alamat,
                                                no_hp, email, nama_ayah, nama_ibu, no_hp_ortu, kelas_id, status, tanggal_masuk)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'Aktif\',CURDATE())'
                        )->execute([
                            $nis, nullIfEmpty(post('nisn')), $nama, $jk, nullIfEmpty(post('tempat_lahir')),
                            nullIfEmpty($tglLahir), nullIfEmpty(post('agama')), nullIfEmpty(post('alamat')),
                            nullIfEmpty(post('no_hp')), $email, nullIfEmpty(post('nama_ayah')), nullIfEmpty(post('nama_ibu')),
                            nullIfEmpty(post('no_hp_ortu')), $kelasId,
                        ]);
                        $siswaId = (int)$pdo->lastInsertId();

                        $pdo->prepare('INSERT INTO users (username, password, nama, role, siswa_id) VALUES (?,?,?,\'siswa\',?)')
                            ->execute([$username, $hash, $nama, $siswaId]);
                        $userId = (int)$pdo->lastInsertId();
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

                    $pdo->prepare('INSERT INTO token_pemakaian (token_id, user_id, nama, role) VALUES (?,?,?,?)')
                        ->execute([$tokenRow['id'], $userId, $nama, $role]);

                    // Email dikirim SEBELUM commit: jika gagal, semua dibatalkan (termasuk kuota token)
                    kirimEmailAkun($cfg, $email, $nama, $role, $username, $passwordPlain);

                    $pdo->commit();
                    $success = maskEmail($email);
                    unset($_SESSION['reg_token_id']);
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    $tokenRow = null;
                    $_POST = [];
                } catch (Throwable $ex) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Register error: ' . $ex->getMessage());
                    if ($ex->getMessage() === 'TOKEN_HABIS') {
                        unset($_SESSION['reg_token_id']);
                        $tokenRow = null;
                        $errors[] = 'Token sudah tidak berlaku (habis dipakai atau dinonaktifkan). Minta token baru kepada admin.';
                    } else {
                        $pesan = 'Pendaftaran gagal: email tidak dapat dikirim atau data tidak dapat disimpan. Pastikan email benar, lalu coba lagi atau hubungi admin.';
                        if (!empty($cfg['debug'])) $pesan .= ' [DEBUG: ' . $ex->getMessage() . ']';
                        $errors[] = $pesan;
                    }
                }
            }
        }
    }
}

$stepNow    = $success ? 3 : ($tokenRow ? 2 : 1);
$role       = $tokenRow['role'] ?? null;
$kelasKunci = $tokenRow['nama_kelas'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun - SIM Sekolah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{ --navy:#131A2E; --navy-2:#0B0F1D; --gold:#F4B740; --gold-2:#D89A1F; }
body{ font-family:'Inter',sans-serif; min-height:100vh; color:#1f2937;
  background:radial-gradient(1200px 500px at 50% -10%, rgba(244,183,64,.12), transparent 60%), linear-gradient(160deg,var(--navy),var(--navy-2)); }
.reg-wrap{ max-width:760px; margin:0 auto; padding:28px 14px 48px; }
.gv-brand{ display:flex; align-items:center; gap:10px; justify-content:center; margin-bottom:22px; }
.gv-brand-badge{ width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg,var(--gold),var(--gold-2));
  display:flex; align-items:center; justify-content:center; color:var(--navy-2); font-size:1.2rem; box-shadow:0 4px 14px -4px rgba(244,183,64,.6); }
.gv-brand-text{ font-family:'Sora',sans-serif; font-weight:800; color:#fff; font-size:1.15rem; line-height:1.1; }
.gv-brand-text small{ display:block; font-family:'Inter',sans-serif; font-size:.62rem; font-weight:600; letter-spacing:.09em; text-transform:uppercase; color:rgba(255,255,255,.5); }

.steps{ display:flex; justify-content:center; gap:8px; margin-bottom:18px; }
.step{ display:flex; align-items:center; gap:8px; color:rgba(255,255,255,.45); font-size:.8rem; font-weight:600; }
.step .dot{ width:26px; height:26px; border-radius:50%; border:2px solid rgba(255,255,255,.25); display:flex; align-items:center; justify-content:center; font-size:.75rem; }
.step.active{ color:#fff; } .step.active .dot{ background:var(--gold); border-color:var(--gold); color:var(--navy-2); }
.step.done{ color:var(--gold); } .step.done .dot{ border-color:var(--gold); color:var(--gold); }
.step + .step::before{ content:''; width:28px; height:2px; background:rgba(255,255,255,.18); margin-right:2px; }

.reg-card{ background:#fff; border-radius:18px; padding:30px; box-shadow:0 20px 50px -15px rgba(0,0,0,.55); }
.reg-card h1{ font-family:'Sora',sans-serif; font-weight:800; font-size:1.35rem; color:var(--navy); margin:0 0 4px; }
.reg-card .sub{ color:#6b7280; font-size:.9rem; margin-bottom:20px; }
.sec{ font-family:'Sora',sans-serif; font-weight:700; font-size:.85rem; color:var(--navy); text-transform:uppercase; letter-spacing:.06em;
  border-bottom:1px solid #e5e7eb; padding-bottom:7px; margin:26px 0 14px; }
.form-label{ font-size:.8rem; font-weight:600; margin-bottom:4px; }
.form-control,.form-select{ border-radius:10px; padding:.6rem .8rem; border-color:#d1d5db; }
.form-control:focus,.form-select:focus{ border-color:var(--gold-2); box-shadow:0 0 0 .2rem rgba(244,183,64,.25); }
.hint{ font-size:.74rem; color:#6b7280; margin-top:3px; }
.req{ color:#dc2626; }

.token-icon{ width:62px; height:62px; border-radius:18px; margin:0 auto 14px; background:linear-gradient(135deg,var(--gold),var(--gold-2));
  display:flex; align-items:center; justify-content:center; font-size:1.7rem; color:var(--navy-2); box-shadow:0 8px 20px -8px rgba(244,183,64,.7); }
.token-input{ text-align:center; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:1.25rem; letter-spacing:.14em; text-transform:uppercase;
  padding:.85rem; border-width:2px; }
.btn-gold{ background:linear-gradient(135deg,var(--gold),var(--gold-2)); color:var(--navy-2); font-weight:700; border:0; border-radius:12px; padding:.75rem 1rem; }
.btn-gold:hover{ filter:brightness(1.06); color:var(--navy-2); }

.token-ok{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:10px 14px; margin-bottom:6px; font-size:.85rem; }
.token-ok i{ color:var(--gold-2); font-size:1.1rem; }
.token-ok a{ margin-left:auto; font-weight:600; color:var(--navy); font-size:.8rem; }
.hp{ position:absolute; left:-9999px; }
.done-icon{ width:70px; height:70px; border-radius:50%; background:#dcfce7; color:#16a34a; font-size:2.2rem; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; }
</style>
</head>
<body>
<div class="reg-wrap">

  <div class="gv-brand">
    <span class="gv-brand-badge"><i class="bi bi-mortarboard-fill"></i></span>
    <span class="gv-brand-text">SIM Sekolah<small>Absensi &amp; Akademik</small></span>
  </div>

  <div class="steps">
    <?php foreach ([1 => 'Token', 2 => 'Data Diri', 3 => 'Selesai'] as $n => $lbl): ?>
      <div class="step <?= $stepNow === $n ? 'active' : ($stepNow > $n ? 'done' : '') ?>">
        <span class="dot"><?= $stepNow > $n ? '<i class="bi bi-check-lg"></i>' : $n ?></span><?= $lbl ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="reg-card">

  <?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
      <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

<?php if ($stepNow === 3): ?>
    <div class="text-center py-3">
      <div class="done-icon"><i class="bi bi-check-lg"></i></div>
      <h1>Pendaftaran Berhasil</h1>
      <p class="sub mb-3">Username dan password sudah dikirim ke <b><?= e($success) ?></b>.<br>Cek Inbox, atau folder Spam jika belum terlihat.</p>
      <a class="btn btn-gold px-4" href="<?= e($cfg['login_url']) ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Ke Halaman Login</a>
    </div>

<?php elseif ($stepNow === 1): ?>
    <div class="text-center">
      <div class="token-icon"><i class="bi bi-key-fill"></i></div>
      <h1>Masukkan Token Pendaftaran</h1>
      <p class="sub">Token diberikan oleh admin sekolah. Formulir pendaftaran akan terbuka setelah token dinyatakan valid.</p>
    </div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="aksi" value="cek_token">
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">
      <input type="text" name="token" class="form-control token-input" placeholder="SISWA-XXXX-XXXX" maxlength="30"
             autocapitalize="characters" spellcheck="false" required autofocus>
      <button type="submit" class="btn btn-gold w-100 mt-3"><i class="bi bi-shield-check me-1"></i>Verifikasi Token</button>
    </form>

<?php else: ?>
    <div class="token-ok">
      <i class="bi bi-patch-check-fill"></i>
      <span>Token valid &middot; <b>Pendaftaran <?= $role === 'guru' ? 'Guru' : 'Siswa' ?></b><?= $kelasKunci ? ' &middot; Kelas ' . e($kelasKunci) : '' ?></span>
      <a href="?reset=1">Ganti token</a>
    </div>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="aksi" value="daftar">
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">

      <div class="sec">Data Akun</div>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Nama Lengkap <span class="req">*</span></label>
          <input type="text" name="nama" class="form-control" maxlength="100" value="<?= old('nama') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Username <span class="req">*</span></label>
          <input type="text" name="username" class="form-control" maxlength="30" value="<?= old('username') ?>" required>
          <div class="hint">4–30 karakter: huruf, angka, titik, underscore</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email<?= $cfg['hanya_gmail'] ? ' Gmail' : '' ?> <span class="req">*</span></label>
          <input type="email" name="email" class="form-control" maxlength="100" value="<?= old('email') ?>" placeholder="nama@gmail.com" required>
          <div class="hint">Password dikirim ke email ini</div>
        </div>
      </div>

      <div class="sec">Data Pribadi</div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Jenis Kelamin <span class="req">*</span></label>
          <select name="jenis_kelamin" class="form-select" required>
            <option value="">-- Pilih --</option>
            <option value="L" <?= sel('jenis_kelamin', 'L') ?>>Laki-laki</option>
            <option value="P" <?= sel('jenis_kelamin', 'P') ?>>Perempuan</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Agama</label>
          <select name="agama" class="form-select">
            <option value="">-- Pilih --</option>
            <?php foreach (['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $ag): ?>
              <option <?= sel('agama', $ag) ?>><?= $ag ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Tempat Lahir</label>
          <input type="text" name="tempat_lahir" class="form-control" maxlength="50" value="<?= old('tempat_lahir') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Tanggal Lahir</label>
          <input type="date" name="tanggal_lahir" class="form-control" value="<?= old('tanggal_lahir') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Alamat</label>
          <textarea name="alamat" class="form-control" rows="2"><?= old('alamat') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">No. HP</label>
          <input type="text" name="no_hp" class="form-control" maxlength="20" value="<?= old('no_hp') ?>">
        </div>
      </div>

<?php if ($role === 'siswa'): ?>
      <div class="sec">Data Siswa</div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">NIS <span class="req">*</span></label>
          <input type="text" name="nis" class="form-control" maxlength="20" value="<?= old('nis') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">NISN</label>
          <input type="text" name="nisn" class="form-control" maxlength="20" value="<?= old('nisn') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Kelas <span class="req">*</span></label>
          <?php if ($kelasKunci): ?>
            <input type="text" class="form-control" value="<?= e($kelasKunci) ?>" disabled>
            <div class="hint">Kelas sudah ditentukan oleh token</div>
          <?php else: ?>
            <select name="kelas_id" class="form-select" required>
              <option value="">-- Pilih Kelas --</option>
              <?php foreach ($kelasList as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= sel('kelas_id', $k['id']) ?>><?= e($k['nama_kelas']) ?> (<?= e($k['tahun_ajaran']) ?>)</option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Nama Ayah</label>
          <input type="text" name="nama_ayah" class="form-control" maxlength="100" value="<?= old('nama_ayah') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nama Ibu</label>
          <input type="text" name="nama_ibu" class="form-control" maxlength="100" value="<?= old('nama_ibu') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">No. HP Orang Tua</label>
          <input type="text" name="no_hp_ortu" class="form-control" maxlength="20" value="<?= old('no_hp_ortu') ?>">
        </div>
      </div>
<?php else: ?>
      <div class="sec">Data Guru</div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">NIP</label>
          <input type="text" name="nip" class="form-control" maxlength="30" value="<?= old('nip') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">NUPTK</label>
          <input type="text" name="nuptk" class="form-control" maxlength="30" value="<?= old('nuptk') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Pendidikan Terakhir</label>
          <input type="text" name="pendidikan_terakhir" class="form-control" maxlength="50" value="<?= old('pendidikan_terakhir') ?>" placeholder="S1 Pendidikan">
        </div>
        <div class="col-md-6">
          <label class="form-label">Bidang Keahlian</label>
          <input type="text" name="bidang_keahlian" class="form-control" maxlength="100" value="<?= old('bidang_keahlian') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Jabatan</label>
          <input type="text" name="jabatan" class="form-control" maxlength="100" value="<?= old('jabatan', 'Guru') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Status Kepegawaian</label>
          <select name="status_kepegawaian" class="form-select">
            <option value="">-- Pilih --</option>
            <?php foreach (['PNS','PPPK','GTY','Honorer','Lainnya'] as $s): ?>
              <option <?= sel('status_kepegawaian', $s) ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
<?php endif; ?>

      <button type="submit" class="btn btn-gold w-100 mt-4"><i class="bi bi-send-fill me-1"></i>Daftar &amp; Kirim Akun ke Email</button>
    </form>
<?php endif; ?>

  </div>
  <p class="text-center mt-3 small" style="color:rgba(255,255,255,.4)">Sudah punya akun? <a href="<?= e($cfg['login_url']) ?>" style="color:var(--gold)">Masuk di sini</a></p>
</div>

<script>
  var ti = document.querySelector('.token-input');
  if (ti) ti.addEventListener('input', function () { this.value = this.value.toUpperCase(); });
</script>
</body>
</html>