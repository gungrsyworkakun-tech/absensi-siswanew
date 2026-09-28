<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
$pageTitle = 'Lokasi Absensi GPS';

$lokasi = $pdo->query("SELECT * FROM lokasi_sekolah ORDER BY id DESC LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lokasi        = trim($_POST['nama_lokasi']);
    $latitude           = (float)$_POST['latitude'];
    $longitude          = (float)$_POST['longitude'];
    $radius             = (int)$_POST['radius_meter'];
    $jam_mulai          = $_POST['jam_mulai'];
    $jam_selesai        = $_POST['jam_selesai'];
    $jam_pulang_mulai   = $_POST['jam_pulang_mulai'];
    $jam_pulang_selesai = $_POST['jam_pulang_selesai'];

    if ($lokasi) {
        $stmt = $pdo->prepare("UPDATE lokasi_sekolah SET nama_lokasi=?, latitude=?, longitude=?, radius_meter=?, jam_mulai=?, jam_selesai=?, jam_pulang_mulai=?, jam_pulang_selesai=? WHERE id=?");
        $stmt->execute([$nama_lokasi, $latitude, $longitude, $radius, $jam_mulai, $jam_selesai, $jam_pulang_mulai, $jam_pulang_selesai, $lokasi['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO lokasi_sekolah (nama_lokasi, latitude, longitude, radius_meter, jam_mulai, jam_selesai, jam_pulang_mulai, jam_pulang_selesai) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$nama_lokasi, $latitude, $longitude, $radius, $jam_mulai, $jam_selesai, $jam_pulang_mulai, $jam_pulang_selesai]);
    }
    setFlash('success', 'Titik lokasi & pengaturan absen GPS berhasil disimpan.');
    redirect('lokasi/index.php');
}

$lokasi = $pdo->query("SELECT * FROM lokasi_sekolah ORDER BY id DESC LIMIT 1")->fetch();
$lat = $lokasi['latitude'] ?? -8.65;
$lng = $lokasi['longitude'] ?? 115.2167;
$radius = $lokasi['radius_meter'] ?? 150;

include __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill me-2"></i>Lokasi &amp; Radius Absensi GPS</h4>
<p class="text-muted small">Atur titik koordinat sekolah dan radius toleransi. Siswa hanya bisa absen GPS jika posisinya berada di dalam radius ini, pada jam yang ditentukan.</p>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card p-4">
      <form method="POST" id="formLokasi">
        <div class="mb-3">
          <label class="form-label">Nama Lokasi</label>
          <input type="text" name="nama_lokasi" class="form-control" value="<?= clean($lokasi['nama_lokasi'] ?? 'Sekolah') ?>" required>
        </div>
        <div class="row">
          <div class="col-6 mb-3">
            <label class="form-label">Latitude</label>
            <input type="text" name="latitude" id="inputLat" class="form-control" value="<?= $lat ?>" required>
          </div>
          <div class="col-6 mb-3">
            <label class="form-label">Longitude</label>
            <input type="text" name="longitude" id="inputLng" class="form-control" value="<?= $lng ?>" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Radius Toleransi (meter)</label>
          <input type="number" name="radius_meter" id="inputRadius" class="form-control" value="<?= $radius ?>" min="10" max="2000" required>
          <small class="text-muted">Rekomendasi: 100–200 meter untuk area sekolah.</small>
        </div>

        <hr class="my-3">
        <div class="fw-semibold small text-muted mb-2"><i class="bi bi-box-arrow-in-right me-1"></i>Jam Absen Masuk</div>
        <div class="row">
          <div class="col-6 mb-3">
            <label class="form-label">Jam Mulai</label>
            <input type="time" name="jam_mulai" class="form-control" value="<?= clean($lokasi['jam_mulai'] ?? '06:00') ?>" required>
          </div>
          <div class="col-6 mb-3">
            <label class="form-label">Jam Selesai</label>
            <input type="time" name="jam_selesai" class="form-control" value="<?= clean($lokasi['jam_selesai'] ?? '08:00') ?>" required>
          </div>
        </div>

        <div class="fw-semibold small text-muted mb-2 mt-2"><i class="bi bi-box-arrow-right me-1"></i>Jam Absen Pulang</div>
        <div class="row">
          <div class="col-6 mb-3">
            <label class="form-label">Jam Mulai</label>
            <input type="time" name="jam_pulang_mulai" class="form-control" value="<?= clean($lokasi['jam_pulang_mulai'] ?? '14:00') ?>" required>
          </div>
          <div class="col-6 mb-3">
            <label class="form-label">Jam Selesai</label>
            <input type="time" name="jam_pulang_selesai" class="form-control" value="<?= clean($lokasi['jam_pulang_selesai'] ?? '16:00') ?>" required>
          </div>
        </div>
        <small class="text-muted d-block mb-3">Siswa hanya bisa absen pulang di rentang jam ini, dan hanya setelah berhasil absen masuk.</small>

        <button type="button" class="btn btn-outline-primary w-100 mb-2" id="btnLokasiSaya"><i class="bi bi-crosshair"></i> Gunakan Lokasi Saya Saat Ini</button>
        <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Simpan Pengaturan</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-3">
      <p class="small text-muted mb-2">Klik pada peta untuk memindahkan titik lokasi. Lingkaran biru menunjukkan area radius absen.</p>
      <div id="map" style="height:480px; border-radius:10px;"></div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const initLat = <?= (float)$lat ?>;
const initLng = <?= (float)$lng ?>;
const initRadius = <?= (int)$radius ?>;

const map = L.map('map').setView([initLat, initLng], 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let marker = L.marker([initLat, initLng], { draggable: true }).addTo(map);
let circle = L.circle([initLat, initLng], { radius: initRadius, color: '#2952a3', fillOpacity: 0.15 }).addTo(map);

function updatePoint(lat, lng) {
  document.getElementById('inputLat').value = lat.toFixed(7);
  document.getElementById('inputLng').value = lng.toFixed(7);
  marker.setLatLng([lat, lng]);
  circle.setLatLng([lat, lng]);
}

map.on('click', function (e) {
  updatePoint(e.latlng.lat, e.latlng.lng);
});

marker.on('dragend', function (e) {
  const pos = marker.getLatLng();
  updatePoint(pos.lat, pos.lng);
});

document.getElementById('inputRadius').addEventListener('input', function () {
  circle.setRadius(parseInt(this.value || 0));
});

document.getElementById('btnLokasiSaya').addEventListener('click', function () {
  if (!navigator.geolocation) { alert('Browser tidak mendukung GPS.'); return; }
  navigator.geolocation.getCurrentPosition(function (pos) {
    const lat = pos.coords.latitude, lng = pos.coords.longitude;
    updatePoint(lat, lng);
    map.setView([lat, lng], 17);
  }, function () {
    alert('Gagal mengambil lokasi. Pastikan izin GPS diaktifkan.');
  }, { enableHighAccuracy: true });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>