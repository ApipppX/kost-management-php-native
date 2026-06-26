<?php
session_start();
require 'koneksi.php';
cek_login();

if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$success_msg = "";

// ==========================================================
// 1. AMBIL DATA PENGATURAN UNTUK TEMPLATE OTOMATIS
// ==========================================================
$q_set = mysqli_query($conn, "SELECT nama_app, tgl_jatuh_tempo, denda_terlambat FROM pengaturan WHERE id = 1");
$setting = mysqli_fetch_assoc($q_set);

$set_nama = $setting['nama_app'] ?? 'KostAdmin Pro';
$set_tgl = $setting['tgl_jatuh_tempo'] ?? 10;
$set_denda = number_format($setting['denda_terlambat'] ?? 0, 0, ',', '.');

// ==========================================================
// 2. PROSES KIRIM PESAN BROADCAST
// ==========================================================
if (isset($_POST['kirim_broadcast'])) {
    $judul = mysqli_real_escape_string($conn, trim($_POST['judul']));
    $pesan = mysqli_real_escape_string($conn, trim($_POST['pesan']));
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe']);
    $target = $_POST['target']; 

    if ($target == 'all') {
        $q_p = mysqli_query($conn, "SELECT id FROM penghuni");
        while ($p = mysqli_fetch_assoc($q_p)) {
            $id_p = $p['id'];
            mysqli_query($conn, "INSERT INTO notifikasi (id_penghuni, judul, pesan, tipe) VALUES ('$id_p', '$judul', '$pesan', '$tipe')");
        }
        $success_msg = "Pengumuman berhasil disiarkan ke seluruh penyewa aktif.";
    } else {
        mysqli_query($conn, "INSERT INTO notifikasi (id_penghuni, judul, pesan, tipe) VALUES ('$target', '$judul', '$pesan', '$tipe')");
        $success_msg = "Notifikasi personal berhasil dikirimkan.";
    }
}

// Ambil data penghuni untuk Dropdown
$penghuni_list = mysqli_query($conn, "SELECT p.id, p.nama_penghuni, k.no_kamar 
                                      FROM penghuni p 
                                      JOIN kamar k ON p.id_kamar = k.id 
                                      ORDER BY p.nama_penghuni ASC");

// Ambil riwayat dengan Analitik Keterbacaan
$riwayat = mysqli_query($conn, "SELECT 
                                    judul, tipe, created_at,
                                    COUNT(id) as total_dikirim,
                                    SUM(CASE WHEN is_read = '1' THEN 1 ELSE 0 END) as total_dibaca
                                FROM notifikasi 
                                GROUP BY judul, tipe, created_at 
                                ORDER BY created_at DESC LIMIT 5");

function getBadgeColor($tipe) {
    if($tipe == 'Tagihan') return 'warning';
    if($tipe == 'Sistem') return 'secondary';
    return 'primary';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcast Center | KostAdmin Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --primary: #4F46E5; --bg: #F4F7F9; --surface: #ffffff; --text-main: #0F172A; --text-muted: #64748B; --border: #E2E8F0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-main); }
        .container-app { max-width: 1150px; margin: 0 auto; padding: 40px 20px; }
        
        .page-header { margin-bottom: 30px; display: flex; align-items: center; gap: 16px; }
        .page-brand-logo { width: 52px; height: 52px; background: linear-gradient(135deg, var(--text-main) 0%, #334155 100%); color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; box-shadow: 0 8px 15px rgba(15, 23, 42, 0.2); }
        .page-title { font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; margin-bottom: 2px; color: var(--text-main); }
        
        /* CARD PANELS */
        .card-panel { background: var(--surface); border-radius: 24px; border: 1px solid var(--border); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05); height: 100%; overflow: hidden; }
        .card-header-clean { padding: 25px 30px 15px 30px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-title-text { font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 10px; }
        .card-body-clean { padding: 30px; }

        /* QUICK TEMPLATES STYLING */
        .template-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px dashed var(--border); }
        .btn-template { font-size: 0.75rem; font-weight: 800; padding: 8px 14px; border-radius: 50px; transition: 0.2s; cursor: pointer; display: flex; align-items: center; gap: 6px; border: 1px solid transparent; }
        
        .btn-tmp-tagihan { background: #FFFBEB; color: #D97706; border-color: #FEF3C7; }
        .btn-tmp-tagihan:hover { background: #FEF3C7; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(217, 119, 6, 0.15); }
        
        .btn-tmp-denda { background: #FEF2F2; color: #E11D48; border-color: #FECACA; }
        .btn-tmp-denda:hover { background: #FECACA; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(225, 29, 72, 0.15); }
        
        .btn-tmp-sistem { background: #F1F5F9; color: #475569; border-color: #E2E8F0; }
        .btn-tmp-sistem:hover { background: #E2E8F0; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(71, 85, 105, 0.15); }

        /* FORM CONTROLS */
        .form-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 12px; border: 1.5px solid var(--border); padding: 14px 18px; font-weight: 600; font-size: 0.95rem; background: #F8FAFC; color: var(--text-main); transition: 0.2s; }
        .form-control:focus, .form-select:focus { background: white; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); outline: none; }
        
        .btn-send { background: var(--text-main); color: white; border: none; border-radius: 14px; padding: 15px 25px; font-weight: 800; font-size: 1rem; transition: 0.3s; width: 100%; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-send:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(79, 70, 229, 0.25); }

        /* ANALYTICS HISTORY LIST */
        .analytics-item { padding: 20px 0; border-bottom: 1px solid var(--border); }
        .analytics-item:last-child { border-bottom: none; padding-bottom: 0; }
        
        .an-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .an-title { font-weight: 800; font-size: 1rem; color: var(--text-main); margin-bottom: 4px; }
        .an-meta { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); }
        
        .progress-wrapper { background: #F1F5F9; border-radius: 50px; height: 8px; width: 100%; overflow: hidden; margin-top: 8px; }
        .progress-fill { height: 100%; background: var(--primary); border-radius: 50px; transition: width 1s ease; }
        .an-stats { display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 800; margin-top: 6px; }
        .an-stats .read-val { color: #10B981; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-app">
        
        <div class="page-header">
            <div class="page-brand-logo"><i class="fa-solid fa-satellite-dish"></i></div>
            <div>
                <h1 class="page-title">Broadcast & Analitik</h1>
                <p class="text-muted fw-500 m-0">Kirim pengumuman otomatis dan pantau keterbacaan (Read Receipts) penyewa secara langsung.</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card-panel">
                    <div class="card-header-clean">
                        <div class="card-title-text"><i class="fa-solid fa-pen-nib text-primary"></i> Tulis Pesan Baru</div>
                    </div>
                    
                    <div class="card-body-clean">
                        
                        <div class="template-pills">
                            <span class="text-muted small fw-bold me-2 align-self-center"><i class="fa-solid fa-bolt text-warning"></i> Quick Template:</span>
                            <button type="button" class="btn-template btn-tmp-tagihan" onclick="applyTemplate('tagihan_baru')">
                                <i class="fa-solid fa-file-invoice-dollar"></i> Tagihan Baru
                            </button>
                            <button type="button" class="btn-template btn-tmp-tagihan" onclick="applyTemplate('jatuh_tempo')">
                                <i class="fa-solid fa-clock"></i> Jatuh Tempo
                            </button>
                            <button type="button" class="btn-template btn-tmp-denda" onclick="applyTemplate('denda')">
                                <i class="fa-solid fa-triangle-exclamation"></i> Peringatan Denda
                            </button>
                            <button type="button" class="btn-template btn-tmp-sistem" onclick="applyTemplate('maintenance')">
                                <i class="fa-solid fa-server"></i> Info Server
                            </button>
                        </div>

                        <form method="POST">
                            <div class="row g-4">
                                <div class="col-md-12">
                                    <label class="form-label">Subjek / Judul Pesan</label>
                                    <input type="text" id="inp-judul" name="judul" class="form-control" placeholder="Contoh: Info Perbaikan Fasilitas" required autocomplete="off">
                                </div>
                                
                                <div class="col-md-5">
                                    <label class="form-label">Kategori</label>
                                    <select id="inp-tipe" name="tipe" class="form-select">
                                        <option value="Pengumuman">📢 Pengumuman</option>
                                        <option value="Tagihan">💰 Tagihan</option>
                                        <option value="Sistem">⚙️ Sistem</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-7">
                                    <label class="form-label">Penerima Pesan</label>
                                    <select name="target" class="form-select">
                                        <option value="all">🚀 Semua Penyewa Aktif (Massal)</option>
                                        <optgroup label="Penyewa Spesifik:">
                                            <?php while($p = mysqli_fetch_assoc($penghuni_list)): ?>
                                                <option value="<?= $p['id']; ?>"><?= $p['nama_penghuni']; ?> (KM. <?= $p['no_kamar'] ?? '-'; ?>)</option>
                                            <?php endwhile; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Isi Pesan Detail</label>
                                    <textarea id="inp-pesan" name="pesan" class="form-control" rows="6" placeholder="Ketikkan detail pesan di sini..." required></textarea>
                                </div>
                                
                                <div class="col-12 mt-4 pt-2">
                                    <button type="submit" name="kirim_broadcast" class="btn-send">
                                        <i class="fa-regular fa-paper-plane"></i> Transmisikan Pesan
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card-panel">
                    <div class="card-header-clean bg-light">
                        <div class="card-title-text"><i class="fa-solid fa-chart-pie text-success"></i> Status Keterbacaan</div>
                    </div>
                    
                    <div class="card-body-clean">
                        <?php if(mysqli_num_rows($riwayat) > 0) : ?>
                            <?php while($h = mysqli_fetch_assoc($riwayat)): 
                                $persentase = ($h['total_dikirim'] > 0) ? round(($h['total_dibaca'] / $h['total_dikirim']) * 100) : 0;
                                $badge_color = getBadgeColor($h['tipe']);
                            ?>
                                <div class="analytics-item">
                                    <div class="an-header">
                                        <div>
                                            <div class="an-title"><?= htmlspecialchars($h['judul']); ?></div>
                                            <div class="an-meta"><i class="fa-regular fa-clock me-1"></i> <?= date('d M, H:i', strtotime($h['created_at'])); ?></div>
                                        </div>
                                        <span class="badge bg-<?= $badge_color; ?> bg-opacity-10 text-<?= $badge_color; ?> border border-<?= $badge_color; ?> rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                                            <?= $h['tipe']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="progress-wrapper">
                                        <div class="progress-fill" style="width: <?= $persentase; ?>%;"></div>
                                    </div>
                                    <div class="an-stats">
                                        <span class="text-muted">Target: <?= $h['total_dikirim']; ?> Akun</span>
                                        <span class="read-val"><i class="fa-solid fa-check-double"></i> Dibaca: <?= $h['total_dibaca']; ?> (<?= $persentase; ?>%)</span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <div class="text-center py-5">
                                <i class="fa-solid fa-chart-simple fs-1 text-muted opacity-25 mb-3"></i>
                                <h6 class="fw-bold text-dark mb-1">Belum Ada Data</h6>
                                <p class="text-muted small mb-0">Analitik keterbacaan pesan massal akan muncul di sini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function applyTemplate(type) {
            // Ambil elemen input dari HTML
            let judul = document.getElementById('inp-judul');
            let tipe = document.getElementById('inp-tipe');
            let pesan = document.getElementById('inp-pesan');

            // Inject Data dari Database (Tabel Pengaturan) menggunakan PHP ke dalam Javascript
            let namaApp = "<?= $set_nama; ?>";
            let tglJatuhTempo = "<?= $set_tgl; ?>";
            let nominalDenda = "<?= $set_denda; ?>";

            if (type === 'tagihan_baru') {
                judul.value = "Informasi Tagihan Bulan Ini";
                tipe.value = "Tagihan";
                pesan.value = "Halo, kami dari Manajemen " + namaApp + " ingin menginformasikan bahwa tagihan kamar untuk bulan ini telah diterbitkan oleh sistem. \n\nSilakan periksa rincian pada menu 'Tagihan' di akun Anda. Terima kasih atas kerjasamanya.";
                
            } else if (type === 'jatuh_tempo') {
                judul.value = "Peringatan Jatuh Tempo Pembayaran";
                tipe.value = "Tagihan";
                pesan.value = "Mengingatkan kembali bahwa batas akhir pembayaran sewa kamar adalah tanggal " + tglJatuhTempo + " setiap bulannya. \n\nMohon segera melakukan pelunasan tagihan aktif Anda untuk menghindari pengenaan denda keterlambatan.";
                
            } else if (type === 'denda') {
                judul.value = "Pemberitahuan Denda Keterlambatan";
                tipe.value = "Tagihan";
                pesan.value = "Pemberitahuan Resmi: \n\nDikarenakan status pembayaran Anda telah melewati batas waktu tanggal " + tglJatuhTempo + ", maka tagihan Anda kini dikenakan denda keterlambatan sebesar Rp " + nominalDenda + " sesuai dengan kebijakan operasional " + namaApp + ".\n\nHarap segera melunasi total tagihan Anda.";
                
            } else if (type === 'maintenance') {
                judul.value = "Maintenance Sistem & Server";
                tipe.value = "Sistem";
                pesan.value = "Pemberitahuan: Sistem portal " + namaApp + " akan mengalami pemeliharaan rutin pada malam ini. \n\nBeberapa fitur mungkin tidak dapat diakses sementara waktu. Kami memohon maaf atas ketidaknyamanan ini.";
            }

            // Tambahkan efek animasi kilat pada kotak teks saat template diklik
            pesan.style.transition = "all 0.3s";
            pesan.style.backgroundColor = "#EEF2FF";
            setTimeout(() => { pesan.style.backgroundColor = "#F8FAFC"; }, 400);
        }
    </script>

    <?php if($success_msg): ?>
        <script>
            Swal.fire({ icon: 'success', title: 'Terkirim', text: '<?= $success_msg; ?>', confirmButtonColor: '#0F172A', customClass: { popup: 'rounded-4' }});
        </script>
    <?php endif; ?>
</body>
</html>