<?php
/**
 * includes/mailer.php — email bersama untuk reset kata sandi & notifikasi keamanan.
 * Memakai PHPMailer + pengaturan Gmail dari folder register/ (tidak perlu isi ulang).
 */

// Lokasi folder register (berisi config.php dan lib/PHPMailer). Sesuaikan bila berbeda.
if (!defined('REGISTER_DIR')) define('REGISTER_DIR', __DIR__ . '/../register');

// Masa berlaku tautan reset kata sandi (menit)
if (!defined('RESET_MENIT')) define('RESET_MENIT', 15);

function mh($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function emailConfig(): array {
    static $c = null;
    if ($c === null) $c = require REGISTER_DIR . '/config.php';
    return $c;
}

/**
 * Ubah path menjadi alamat lengkap untuk link di email.
 * SANGAT disarankan mengisi 'site_url' di register/config.php (mis. 'https://sekolahanda.sch.id'),
 * agar link reset tidak bisa dibelokkan lewat header Host palsu.
 */
function urlLengkap(string $path): string {
    if (preg_match('#^https?://#i', $path)) return $path;
    $cfg = emailConfig();
    if (!empty($cfg['site_url'])) return rtrim($cfg['site_url'], '/') . '/' . ltrim($path, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https' : 'http') . '://' . $host . '/' . ltrim($path, '/');
}

function kirimEmail(string $to, string $toName, string $subject, string $html, string $alt): void {
    require_once REGISTER_DIR . '/lib/PHPMailer/Exception.php';
    require_once REGISTER_DIR . '/lib/PHPMailer/PHPMailer.php';
    require_once REGISTER_DIR . '/lib/PHPMailer/SMTP.php';
    $cfg = emailConfig();

    $m = new \PHPMailer\PHPMailer\PHPMailer(true);
    $m->isSMTP();
    $m->Host       = $cfg['mail']['host'];
    $m->SMTPAuth   = true;
    $m->Username   = $cfg['mail']['user'];
    $m->Password   = str_replace(' ', '', (string)$cfg['mail']['pass']);
    $m->Timeout    = 20;
    $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port       = $cfg['mail']['port'];
    $m->CharSet    = 'UTF-8';
    $m->setFrom($cfg['mail']['user'], $cfg['mail']['from_name']);
    $m->addAddress($to, $toName);
    $m->isHTML(true);
    $m->Subject = $subject;
    $m->Body    = $html;
    $m->AltBody = $alt;
    $m->send();
}

/** Kerangka email bergaya dashboard (navy + emas). $isiHtml harus sudah di-escape oleh pemanggil. */
function templateEmail(string $judul, string $isiHtml, ?string $btnUrl = null, string $btnLabel = '', string $catatanHtml = ''): string {
    $cfg  = emailConfig();
    $app  = mh($cfg['app_name']);
    $jdl  = mh($judul);
    $btn  = '';
    if ($btnUrl) {
        $u = mh($btnUrl);
        $btn = '<tr><td align="center" style="padding:24px 32px 6px 32px;">
          <a href="' . $u . '" style="display:inline-block;background:#F4B740;color:#0B0F1D;font-size:16px;font-weight:800;text-decoration:none;padding:14px 36px;border-radius:12px;">' . mh($btnLabel) . '</a>
          <div style="font-size:12px;color:#9ca3af;padding-top:14px;line-height:1.5;">Jika tombol tidak berfungsi, salin alamat ini ke browser:<br><a href="' . $u . '" style="color:#6b7280;word-break:break-all;">' . $u . '</a></div>
        </td></tr>';
    }
    $note = $catatanHtml === '' ? '' : '<tr><td style="padding:22px 32px 6px 32px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fff7ed" style="background:#fff7ed;border-left:4px solid #F4B740;border-radius:8px;">
        <tr><td style="padding:12px 16px;font-size:13px;line-height:1.6;color:#92400e;">' . $catatanHtml . '</td></tr></table></td></tr>';

    return <<<HTML
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#eef1f7;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#eef1f7">
<tr><td align="center" style="padding:28px 12px;">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
    <tr><td bgcolor="#131A2E" style="background:#131A2E;padding:26px 32px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
        <td width="46" height="46" align="center" valign="middle" bgcolor="#F4B740" style="background:#F4B740;border-radius:12px;font-size:24px;line-height:46px;">&#127891;</td>
        <td style="padding-left:14px;">
          <div style="color:#ffffff;font-size:20px;font-weight:800;line-height:1.1;">{$app}</div>
          <div style="color:#9aa3b8;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;padding-top:4px;">Absensi &amp; Akademik</div>
        </td>
      </tr></table>
    </td></tr>
    <tr><td height="4" bgcolor="#F4B740" style="background:#F4B740;font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr><td style="padding:32px 32px 4px 32px;">
      <div style="font-size:23px;font-weight:800;color:#131A2E;line-height:1.25;">{$jdl}</div>
      <div style="margin-top:14px;font-size:15px;line-height:1.7;color:#4b5563;">{$isiHtml}</div>
    </td></tr>
    {$btn}
    {$note}
    <tr><td style="height:26px;font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr><td bgcolor="#f3f4f6" align="center" style="background:#f3f4f6;padding:18px 32px;font-size:12px;line-height:1.6;color:#9ca3af;">
      Email ini dikirim otomatis oleh sistem {$app}.<br>Mohon tidak membalas email ini.
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>
HTML;
}

/** Ambil nama & email akun (siswa → siswa.email, guru/wali kelas → guru_profil.email). */
function emailUser(PDO $pdo, int $userId): ?array {
    $q = $pdo->prepare("SELECT u.nama, u.username,
                               COALESCE(NULLIF(s.email, ''), NULLIF(g.email, '')) AS email
                        FROM users u
                        LEFT JOIN siswa s ON s.id = u.siswa_id
                        LEFT JOIN guru_profil g ON g.user_id = u.id
                        WHERE u.id = ? LIMIT 1");
    $q->execute([$userId]);
    $r = $q->fetch();
    return $r ?: null;
}

function ringkasPerangkat(string $ua): string {
    $os = preg_match('/Windows/i', $ua) ? 'Windows'
        : (preg_match('/Android/i', $ua) ? 'Android'
        : (preg_match('/iPhone|iPad|iPod/i', $ua) ? 'iOS'
        : (preg_match('/Mac OS/i', $ua) ? 'macOS'
        : (preg_match('/Linux/i', $ua) ? 'Linux' : 'perangkat tidak dikenal'))));
    $br = preg_match('/Edg/i', $ua) ? 'Edge'
        : (preg_match('/OPR|Opera/i', $ua) ? 'Opera'
        : (preg_match('/Chrome|CriOS/i', $ua) ? 'Chrome'
        : (preg_match('/Firefox|FxiOS/i', $ua) ? 'Firefox'
        : (preg_match('/Safari/i', $ua) ? 'Safari' : 'Browser'))));
    return "$br di $os";
}

function samarkanEmail(string $email): string {
    [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
    return substr($u, 0, 2) . str_repeat('*', max(2, strlen($u) - 2)) . '@' . $d;
}

/**
 * Minta tautan reset. Mengembalikan:
 *   ['status' => 'terkirim', 'email' => 'ab***@gmail.com']
 *   ['status' => 'tidak_ditemukan']  username tidak ada → tidak ada email yang dikirim
 *   ['status' => 'tanpa_email']      akun ada tetapi tidak punya email (mis. admin)
 *   ['status' => 'dibatasi']         terlalu sering meminta reset
 */
function kirimTautanReset(PDO $pdo, string $username): array {
    $pdo->exec("DELETE FROM password_resets WHERE expires_at < (NOW() - INTERVAL 7 DAY)");

    $q = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $q->execute([$username]);
    $uid = (int)$q->fetchColumn();
    if (!$uid) return ['status' => 'tidak_ditemukan'];

    $info = emailUser($pdo, $uid);
    if (!$info || empty($info['email'])) return ['status' => 'tanpa_email'];

    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    // Batas: 3 permintaan/jam per akun, 10 permintaan/jam per IP
    $c = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $c->execute([$uid]);
    if ((int)$c->fetchColumn() >= 3) return ['status' => 'dibatasi'];
    $c = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $c->execute([$ip]);
    if ((int)$c->fetchColumn() >= 10) return ['status' => 'dibatasi'];

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$uid]);
    $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, ip)
                   VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . (int)RESET_MENIT . ' MINUTE), ?)')
        ->execute([$uid, hash('sha256', $token), $ip]);

    $link = urlLengkap(BASE_URL . '/reset_password.php?token=' . $token);
    $isi  = 'Halo <b style="color:#131A2E;">' . mh($info['nama']) . '</b>, kami menerima permintaan untuk mengatur ulang kata sandi akun
             <b style="color:#131A2E;">' . mh($info['username']) . '</b>. Klik tombol di bawah untuk membuat kata sandi baru.
             Tautan berlaku <b>' . (int)RESET_MENIT . ' menit</b> dan hanya bisa dipakai <b>sekali</b>.';
    $note = '<b>&#128274; Bukan Anda yang meminta?</b> Abaikan email ini. Kata sandi Anda tidak akan berubah selama tautan tidak dibuka.';

    kirimEmail(
        $info['email'], $info['nama'], 'Atur ulang kata sandi akun Anda',
        templateEmail('Atur ulang kata sandi', $isi, $link, 'Atur Ulang Kata Sandi', $note),
        "Halo {$info['nama']},\nBuka tautan ini untuk mengatur ulang kata sandi (berlaku " . (int)RESET_MENIT . " menit, sekali pakai):\n$link\n\nJika bukan Anda yang meminta, abaikan email ini."
    );
    return ['status' => 'terkirim', 'email' => samarkanEmail($info['email'])];
}

/**
 * Email konfirmasi setelah kata sandi diubah.
 * Bisa dipanggil juga dari halaman "Ganti Password" di dalam aplikasi:
 *   kirimNotifPasswordBerubah($pdo, $userId);
 */
function kirimNotifPasswordBerubah(PDO $pdo, int $userId): void {
    $info = emailUser($pdo, $userId);
    if (!$info || empty($info['email'])) return;

    $waktu = date('d M Y, H:i') . ' ' . date('T');
    $perangkat = ringkasPerangkat((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '-');

    $isi = 'Halo <b style="color:#131A2E;">' . mh($info['nama']) . '</b>, kata sandi akun
            <b style="color:#131A2E;">' . mh($info['username']) . '</b> baru saja diubah.
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
              <tr><td style="padding:14px 18px;font-size:14px;line-height:1.9;color:#4b5563;">
                <b style="color:#131A2E;">Waktu</b> &nbsp;' . mh($waktu) . '<br>
                <b style="color:#131A2E;">Perangkat</b> &nbsp;' . mh($perangkat) . '<br>
                <b style="color:#131A2E;">Alamat IP</b> &nbsp;' . mh($ip) . '
              </td></tr>
            </table>';
    $note = '<b>&#9888;&#65039; Bukan Anda?</b> Segera hubungi administrator sekolah agar akun Anda dapat diamankan.';

    kirimEmail(
        $info['email'], $info['nama'], 'Kata sandi akun Anda telah diubah',
        templateEmail('Kata sandi berhasil diubah', $isi, urlLengkap(BASE_URL . '/login.php'), 'Masuk ke Akun', $note),
        "Halo {$info['nama']},\nKata sandi akun {$info['username']} diubah pada $waktu ($perangkat, IP $ip).\nBukan Anda? Segera hubungi administrator sekolah."
    );
}