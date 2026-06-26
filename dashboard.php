<?php
session_start();
require 'koneksi.php';
cek_login();

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$success_msg = "";
$error_msg = "";

// Ambil data user lengkap
$user_full = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'"));
$id_p = $user_full['id_penghuni'];

// =========================================================
// 1. PROSES MUTASI MANDIRI OLEH USER (PINDAH / KELUAR)
// =========================================================
if (isset($_POST['user_mutasi']) && $role == 'user') {
    $jenis_mutasi = $_POST['jenis_mutasi']; 
    $id_kamar_lama = mysqli_real_escape_string($conn, $_POST['id_kamar_lama']);
    
    mysqli_begin_transaction($conn);
    try {
        if ($jenis_mutasi == 'keluar') {
            if(!empty($id_kamar_lama)) {
                mysqli_query($conn, "UPDATE kamar SET status = 'Tersedia' WHERE id = '$id_kamar_lama'");
            }
            // Ubah status penghuni menjadi Keluar
            mysqli_query($conn, "UPDATE penghuni SET status_penghuni = 'Keluar' WHERE id = '$id_p'");
            $success_msg = "Anda berhasil berhenti menyewa. Kamar Anda telah dikosongkan.";
            
        } elseif ($jenis_mutasi == 'pindah') {
            $id_kamar_baru = mysqli_real_escape_string($conn, $_POST['id_kamar_baru']);
            if (!empty($id_kamar_baru)) {
                if(!empty($id_kamar_lama)) {
                    mysqli_query($conn, "UPDATE kamar SET status = 'Tersedia' WHERE id = '$id_kamar_lama'");
                }
                mysqli_query($conn, "UPDATE kamar SET status = 'Terisi' WHERE id = '$id_kamar_baru'");
                mysqli_query($conn, "UPDATE penghuni SET id_kamar = '$id_kamar_baru', status_penghuni = 'Aktif' WHERE id = '$id_p'");
                $success_msg = "Proses pindah kamar berhasil. Sistem telah diperbarui.";
            } else {
                throw new Exception("Anda belum memilih kamar tujuan!");
            }
        }
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Gagal memproses mutasi: " . $e->getMessage();
    }
}

// =========================================================
// AMBIL PENGATURAN GLOBAL SISTEM
// =========================================================
$q_set = mysqli_query($conn, "SELECT tgl_jatuh_tempo, denda_terlambat FROM pengaturan WHERE id = 1");
$setting = mysqli_fetch_assoc($q_set);
$tgl_jt_default = $setting['tgl_jatuh_tempo'] ?? 10;
$denda_keterlambatan = $setting['denda_terlambat'] ?? 0;

// --- LOGIKA DATA GRAFIK & AKTIVITAS REAL DATABASE ---
$labels = [];
$data_values = [];

// Data Kamar Kosong (Untuk Dropdown Pindah Kamar User)
$q_kamar_kosong = mysqli_query($conn, "SELECT k.id, k.no_kamar, ks.nama_kost, k.harga_perbulan 
                                       FROM kamar k LEFT JOIN kost ks ON k.id_kost = ks.id 
                                       WHERE k.status = 'Tersedia' ORDER BY ks.nama_kost ASC, k.no_kamar ASC");

if ($role == 'admin') {
    $jml_kost = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kost"));
    $jml_kamar = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kamar"));
    
    // Pastikan menangani error jika kolom status_penghuni tidak ada di database versi lama
    $cek_col = mysqli_query($conn, "SHOW COLUMNS FROM penghuni LIKE 'status_penghuni'");
    if(mysqli_num_rows($cek_col) > 0){
        $jml_penghuni = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM penghuni WHERE status_penghuni = 'Aktif'"));
    } else {
        $jml_penghuni = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM penghuni"));
    }
    
    $jml_tunggakan = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tagihan WHERE status = 'Belum Lunas'"));

    $query_chart = "SELECT DATE_FORMAT(tanggal_bayar, '%b') as bulan, SUM(jumlah_bayar) as total 
                    FROM pembayaran GROUP BY MONTH(tanggal_bayar) ORDER BY tanggal_bayar ASC LIMIT 6";

    $query_activity = "SELECT p.*, pen.nama_penghuni FROM pembayaran p 
                       JOIN tagihan t ON p.no_invoice = t.no_invoice
                       JOIN penghuni pen ON t.id_penghuni = pen.id
                       ORDER BY p.tanggal_bayar DESC LIMIT 3";
} else {
    // STATISTIK USER
    $kamar_saya = "N/A"; 
    $lokasi_kost = "Belum Terhubung"; 
    $id_kamar_saat_ini = null;
    $is_active_tenant = false;
    
    $tagihan_pending = 0;
    $total_tagihan_tampil = 0;
    $masa_aktif_teks = "<span class='text-muted small'>Belum ada data</span>";
    $jatuh_tempo_berikutnya = "-";

    if ($id_p) {
        // Cek apakah user ini aktif menyewa kamar (Bukan yang sudah Keluar)
        $q_kmr = mysqli_query($conn, "SELECT p.status_penghuni, k.id as id_kamar, k.no_kamar, ks.nama_kost FROM penghuni p JOIN kamar k ON p.id_kamar = k.id JOIN kost ks ON k.id_kost = ks.id WHERE p.id = '$id_p'");
        
        if(mysqli_num_rows($q_kmr) > 0){
            $d_kmr = mysqli_fetch_assoc($q_kmr);
            // Hanya anggap punya kamar jika statusnya AKTIF atau PINDAH
            if(!isset($d_kmr['status_penghuni']) || $d_kmr['status_penghuni'] == 'Aktif' || $d_kmr['status_penghuni'] == 'Pindah') {
                $kamar_saya = $d_kmr['no_kamar'];
                $lokasi_kost = $d_kmr['nama_kost'];
                $id_kamar_saat_ini = $d_kmr['id_kamar'];
                $is_active_tenant = true;
            }
        }
        
        if($is_active_tenant) {
            $q_tagihan = mysqli_query($conn, "SELECT no_invoice, jumlah, created_at FROM tagihan WHERE id_penghuni = '$id_p' AND status = 'Belum Lunas' ORDER BY id DESC LIMIT 1");
            if(mysqli_num_rows($q_tagihan) > 0) {
                $tagihan_data = mysqli_fetch_assoc($q_tagihan);
                $tagihan_pending = 1;
                
                $waktu_buat = strtotime($tagihan_data['created_at']);
                $bln_tagihan = date('Y-m', $waktu_buat);
                
                $waktu_jatuh_tempo = strtotime($bln_tagihan . '-' . str_pad($tgl_jt_default, 2, '0', STR_PAD_LEFT) . ' 23:59:59');
                if ($waktu_buat > $waktu_jatuh_tempo) {
                    $waktu_jatuh_tempo = strtotime('+1 month', $waktu_jatuh_tempo);
                }

                $jatuh_tempo_berikutnya = date('d M Y', $waktu_jatuh_tempo);
                $sisa_waktu = $waktu_jatuh_tempo - time();
                $sisa_hari = ceil($sisa_waktu / (60 * 60 * 24));
                $total_tagihan_tampil = $tagihan_data['jumlah'];

                if ($sisa_waktu < 0) {
                    $total_tagihan_tampil += $denda_keterlambatan;
                    $masa_aktif_teks = "<span class='text-danger fw-extrabold'><i class='fa-solid fa-circle-xmark'></i> Terlambat " . abs($sisa_hari) . " Hari</span>";
                } elseif ($sisa_hari <= 3) {
                    $masa_aktif_teks = "<span class='text-danger fw-bold'><i class='fa-solid fa-triangle-exclamation'></i> Segera Bayar! (Sisa ".$sisa_hari." Hari)</span>";
                } else {
                    $masa_aktif_teks = "<span class='text-warning fw-bold'><i class='fa-solid fa-clock'></i> Tenggang Waktu: ".$sisa_hari." Hari</span>";
                }
                
            } else {
                $q_masa_aktif = mysqli_query($conn, "SELECT p.tanggal_bayar FROM pembayaran p JOIN tagihan t ON p.no_invoice = t.no_invoice WHERE t.id_penghuni = '$id_p' AND t.status = 'Lunas' ORDER BY p.tanggal_bayar DESC LIMIT 1");
                if (mysqli_num_rows($q_masa_aktif) > 0) {
                    $data_bayar = mysqli_fetch_assoc($q_masa_aktif);
                    $bln_depan = date('Y-m', strtotime('+1 month', strtotime($data_bayar['tanggal_bayar'])));
                    $waktu_jatuh_tempo = strtotime($bln_depan . '-' . str_pad($tgl_jt_default, 2, '0', STR_PAD_LEFT));
                    
                    $jatuh_tempo_berikutnya = date('d M Y', $waktu_jatuh_tempo);
                    $sisa_hari = ceil(($waktu_jatuh_tempo - time()) / (60 * 60 * 24));
                    $masa_aktif_teks = "<span class='text-success fw-bold'><i class='fa-solid fa-shield-check'></i> Aman (Jeda ".$sisa_hari." hari)</span>";
                }
            }
        }
    }

    $query_chart = "SELECT DATE_FORMAT(p.tanggal_bayar, '%b') as bulan, SUM(p.jumlah_bayar) as total 
                    FROM pembayaran p JOIN tagihan t ON p.no_invoice = t.no_invoice
                    WHERE t.id_penghuni = '$id_p' GROUP BY MONTH(p.tanggal_bayar) ORDER BY p.tanggal_bayar ASC LIMIT 6";

    $query_activity = "SELECT p.*, pen.nama_penghuni FROM pembayaran p 
                       JOIN tagihan t ON p.no_invoice = t.no_invoice
                       JOIN penghuni pen ON t.id_penghuni = pen.id
                       WHERE t.id_penghuni = '$id_p' ORDER BY p.tanggal_bayar DESC LIMIT 3";
}

$res_chart = mysqli_query($conn, $query_chart);
while ($row = mysqli_fetch_assoc($res_chart)) { $labels[] = $row['bulan']; $data_values[] = (int)$row['total']; }
if (empty($labels)) { $labels = ['Jan']; $data_values = [0]; }
$label_json = json_encode($labels);
$data_json = json_encode($data_values);

$res_activity = mysqli_query($conn, $query_activity);

$default_avatar = "https://ui-avatars.com/api/?name=".urlencode($username)."&background=4F46E5&color=ffffff&size=150&bold=true";
$foto_dashboard = (!empty($user_full['foto_profil']) && file_exists('uploads/' . $user_full['foto_profil'])) ? 'uploads/' . $user_full['foto_profil'] : $default_avatar;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - KostAdmin Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root { --primary: #4F46E5; --bg: #F8FAFC; --surface: #ffffff; --text-main: #0F172A; --border: #E2E8F0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        
        .welcome-banner { background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 24px; padding: 40px; color: white; margin-bottom: 30px; box-shadow: 0 15px 30px rgba(15, 23, 42, 0.1); position: relative; overflow: hidden; }
        .welcome-banner::after { content: ''; position: absolute; top: -50px; right: -50px; width: 250px; height: 250px; background: rgba(79, 70, 229, 0.2); filter: blur(50px); border-radius: 50%; pointer-events: none; }
        
        .stat-card { background: var(--surface); border-radius: 24px; border: 1px solid var(--border); padding: 25px; transition: all 0.3s; height: 100%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.06); border-color: #CBD5E1; }
        .stat-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 15px; }
        
        .icon-blue { background: #EEF2FF; color: var(--primary); }
        .icon-green { background: #ECFDF5; color: #10B981; }
        .icon-purple { background: #FAF5FF; color: #A855F7; }
        .icon-red { background: #FEF2F2; color: #EF4444; }
        
        .profile-mini { background: white; border-radius: 24px; padding: 25px; border: 1px solid var(--border); }
        .profile-img-mini { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 4px solid var(--border); box-shadow: 0 4px 15px rgba(79, 70, 229, 0.1); }
        .chart-container { background: white; border-radius: 24px; padding: 25px; border: 1px solid var(--border); height: 350px; }
        
        .quick-action-btn { padding: 12px 16px; border-radius: 14px; border: 1px solid var(--border); background: #F8FAFC; transition: 0.2s; text-decoration: none; color: var(--text-main); display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 0.9rem; }
        .quick-action-btn:hover { background: white; color: var(--primary); border-color: var(--primary); box-shadow: 0 4px 10px rgba(79, 70, 229, 0.1); transform: translateX(3px); }

        .cta-kamar { background: white; border: 2px dashed #cbd5e1; border-radius: 24px; transition: 0.3s; cursor: pointer; }
        .cta-kamar:hover { border-color: var(--primary); background: #f8faff; }

        /* MODAL MUTASI USER STYLING */
        .modal-header-custom { background: #1E293B; color: white; padding: 25px; border-bottom: none; border-radius: 24px 24px 0 0;}
        .radio-box-container { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .radio-box { position: relative; }
        .radio-box input { position: absolute; opacity: 0; cursor: pointer; }
        .radio-box .rb-label { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 10px; background: #F8FAFC; border: 2px solid var(--border); border-radius: 16px; cursor: pointer; transition: 0.2s; font-weight: 800; color: #64748B; gap: 8px; }
        .radio-box .rb-label i { font-size: 1.8rem; }
        .radio-box input:checked + .rb-label.keluar { background: #FEF2F2; border-color: #EF4444; color: #EF4444; }
        .radio-box input:checked + .rb-label.pindah { background: #EEF2FF; border-color: var(--primary); color: var(--primary); }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row g-4">
            <div class="col-lg-3">
                <div class="profile-mini shadow-sm mb-4 text-center">
                    <img src="<?= $foto_dashboard; ?>" class="profile-img-mini" alt="Profil">
                    <h5 class="fw-extrabold mb-0 text-dark"><?= ucfirst($username); ?></h5>
                    <p class="text-muted small mb-3 fw-bold" style="letter-spacing: 0.5px;"><?= strtoupper($role); ?></p>
                    <a href="profile.php" class="btn btn-light btn-sm w-100 rounded-pill border fw-bold text-muted transition-0.3s hover-primary">Konfigurasi Profil</a>
                </div>
                
                <div class="card border-0 shadow-sm rounded-4 p-4" style="border: 1px solid var(--border) !important;">
                    <h6 class="fw-800 mb-3 small text-uppercase text-muted" style="letter-spacing: 1px;">Akses Cepat</h6>
                    <div class="d-flex flex-column gap-2">
                        <?php if($role == 'user'): ?>
                            <a href="pilih_kamar.php" class="quick-action-btn"><i class="fa-solid fa-house-medical text-primary"></i> Hubungkan Kamar</a>
                        <?php endif; ?>
                        <a href="tagihan.php" class="quick-action-btn"><i class="fa-solid fa-file-invoice-dollar text-warning"></i> Lihat Tagihan</a>
                        <?php if($role == 'admin'): ?>
                            <a href="penghuni.php" class="quick-action-btn"><i class="fa-solid fa-users text-success"></i> Kelola Penyewa</a>
                        <?php endif; ?>
                        <a href="riwayat_transaksi.php" class="quick-action-btn"><i class="fa-solid fa-clock-rotate-left text-secondary"></i> Riwayat Bayar</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="welcome-banner">
                    <div class="row align-items-center">
                        <div class="col-md-8 position-relative" style="z-index: 2;">
                            <h2 class="fw-extrabold mb-2" style="letter-spacing: -0.5px;"><span id="greeting">Halo</span>, <?= ucfirst($username); ?>! ✨</h2>
                            <p class="mb-0 text-white-50 fw-500">Ringkasan statistik dan aktivitas properti Anda saat ini.</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0 position-relative" style="z-index: 2;">
                            <h3 class="fw-800 mb-0 font-monospace" id="realtime-clock">00:00:00</h3>
                            <p class="mb-0 small text-white-50 fw-bold"><?= date('l, d M Y'); ?></p>
                        </div>
                    </div>
                </div>

                <?php if ($role == 'user') : ?>
                    
                    <?php if (!$is_active_tenant) : ?>
                        <div class="cta-kamar text-center p-5 mb-4 shadow-sm" onclick="window.location.href='pilih_kamar.php'">
                            <div class="mb-3"><i class="fa-solid fa-house-circle-check text-primary opacity-50" style="font-size: 3.5rem;"></i></div>
                            <h4 class="fw-extrabold text-dark mb-2">Anda Belum Terhubung ke Kamar</h4>
                            <p class="text-muted mb-4 px-md-5">Anda belum memiliki kamar aktif atau telah berhenti menyewa. Pilih lokasi dan ajukan booking untuk mulai menetap.</p>
                            <a href="pilih_kamar.php" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-key me-2"></i> Ajukan Sewa Kamar</a>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <div class="row g-4 mb-4">
                    <?php if ($role == 'admin') : ?>
                        <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon icon-blue"><i class="fa-solid fa-building"></i></div><div class="stat-label small text-muted fw-800 text-uppercase">Properti</div><div class="h4 fw-extrabold mb-0 text-dark"><?= $jml_kost; ?></div></div></div>
                        <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon icon-purple"><i class="fa-solid fa-door-open"></i></div><div class="stat-label small text-muted fw-800 text-uppercase">Kamar</div><div class="h4 fw-extrabold mb-0 text-dark"><?= $jml_kamar; ?></div></div></div>
                        <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon icon-green"><i class="fa-solid fa-users"></i></div><div class="stat-label small text-muted fw-800 text-uppercase">Penghuni</div><div class="h4 fw-extrabold mb-0 text-dark"><?= $jml_penghuni; ?></div></div></div>
                        <div class="col-md-3 col-6"><div class="stat-card border-start border-4 border-danger"><div class="stat-icon icon-red"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="stat-label small text-muted fw-800 text-uppercase">Menunggak</div><div class="h4 fw-extrabold mb-0 text-danger"><?= $jml_tunggakan; ?></div></div></div>
                    <?php else : ?>
                        <?php if($is_active_tenant): ?>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-icon icon-blue mb-3"><i class="fa-solid fa-bed"></i></div>
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="stat-label small text-muted fw-800 text-uppercase mb-1">Unit Sewa Anda</div>
                                            <div class="h5 fw-extrabold mb-0 text-dark">KM. <?= $kamar_saya; ?></div>
                                            <div class="text-muted text-truncate fw-500" style="font-size: 0.8rem; margin-top: 4px;"><i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= $lokasi_kost; ?></div>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger w-100 fw-bold mt-3" style="border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#modalMutasiUser">
                                        <i class="fa-solid fa-right-from-bracket me-1"></i> Pindah / Keluar
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="stat-card <?= ($tagihan_pending > 0) ? 'border-danger bg-danger bg-opacity-10' : ''; ?>">
                                    <div class="stat-icon <?= ($tagihan_pending > 0) ? 'bg-danger text-white shadow-sm' : 'icon-green'; ?> mb-3">
                                        <i class="fa-solid fa-file-invoice-dollar"></i>
                                    </div>
                                    <div class="stat-label small text-muted fw-800 text-uppercase mb-1">Status Pembayaran</div>
                                    
                                    <?php if ($tagihan_pending > 0) : ?>
                                        <div class="h5 fw-extrabold text-danger mb-0">Rp <?= number_format($total_tagihan_tampil, 0, ',', '.'); ?></div>
                                        <div class="text-danger fw-bold" style="font-size: 0.75rem; margin-top: 4px;">Belum Dibayar</div>
                                    <?php else : ?>
                                        <div class="h5 fw-extrabold text-success mb-0">Lunas</div>
                                        <div class="text-muted fw-500" style="font-size: 0.75rem; margin-top: 4px;">Tidak ada tagihan aktif</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-icon icon-purple mb-3"><i class="fa-solid fa-calendar-day"></i></div>
                                    <div class="stat-label small text-muted fw-800 text-uppercase mb-1">Jatuh Tempo Berikutnya</div>
                                    <div class="h5 fw-extrabold text-dark mb-0"><?= $jatuh_tempo_berikutnya; ?></div>
                                    <div class="mt-2" style="font-size: 0.8rem;"><?= $masa_aktif_teks; ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-12">
                        <div class="chart-container shadow-sm">
                            <h6 class="fw-800 mb-4 text-dark"><i class="fa-solid fa-chart-line text-primary me-2"></i> <?= ($role == 'admin') ? 'Grafik Pemasukan Bulanan' : 'Grafik Riwayat Pembayaran Anda'; ?></h6>
                            <canvas id="myChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4" style="border: 1px solid var(--border) !important;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="fw-800 mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-muted me-2"></i> Transaksi Terkini</h6>
                            <a href="riwayat_transaksi.php" class="btn btn-sm btn-light border rounded-pill px-4 fw-bold text-muted">Lihat Semua</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <thead class="text-muted small border-bottom">
                                    <tr>
                                        <th class="py-3 fw-800 text-uppercase">Keterangan</th>
                                        <th class="py-3 fw-800 text-uppercase">Tanggal</th>
                                        <th class="py-3 fw-800 text-uppercase">Jumlah</th>
                                        <th class="py-3 fw-800 text-uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <?php while($act = mysqli_fetch_assoc($res_activity)) : ?>
                                    <tr class="border-bottom">
                                        <td class="py-3">
                                            <div class="fw-800 text-dark">Faktur #<?= $act['no_invoice']; ?></div>
                                            <div class="text-muted fw-600"><?= strtoupper($act['nama_penghuni']); ?></div>
                                        </td>
                                        <td class="py-3 fw-600 text-muted"><?= date('d M Y', strtotime($act['tanggal_bayar'])); ?></td>
                                        <td class="fw-extrabold text-dark py-3">Rp <?= number_format($act['jumlah_bayar'], 0, ',', '.'); ?></td>
                                        <td class="py-3"><span class="badge bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-pill px-3 py-1 fw-bold">LUNAS</span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    
                                    <?php if(mysqli_num_rows($res_activity) == 0) : ?>
                                        <tr><td colspan="4" class="text-center text-muted py-5"><i class="fa-solid fa-receipt fs-2 opacity-25 d-block mb-3"></i><span class="fw-bold">Belum ada riwayat transaksi.</span></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($role == 'user' && $is_active_tenant) : ?>
    <div class="modal fade" id="modalMutasiUser" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 24px;">
                <div class="modal-header-custom">
                    <h5 class="fw-800 m-0"><i class="fa-solid fa-door-open me-2"></i> Pengajuan Pindah / Keluar</h5>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-4" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST">
                        <input type="hidden" name="id_kamar_lama" value="<?= $id_kamar_saat_ini; ?>">
                        
                        <label class="form-label small fw-800 text-muted uppercase mb-2">Tentukan Pilihan Anda</label>
                        <div class="radio-box-container">
                            <label class="radio-box">
                                <input type="radio" name="jenis_mutasi" value="pindah" checked>
                                <div class="rb-label pindah">
                                    <i class="fa-solid fa-bed"></i> Pindah Kamar
                                </div>
                            </label>
                            <label class="radio-box">
                                <input type="radio" name="jenis_mutasi" value="keluar">
                                <div class="rb-label keluar">
                                    <i class="fa-solid fa-person-walking-arrow-right"></i> Berhenti Sewa
                                </div>
                            </label>
                        </div>

                        <div id="u_kamar_baru_container" class="mb-4">
                            <label class="form-label small fw-800 text-muted uppercase mb-2">Pilih Kamar Baru</label>
                            <select name="id_kamar_baru" id="u_kamar_baru_select" class="form-select" style="border-radius: 12px; padding: 12px 15px; font-weight: 600; border-color: var(--border);">
                                <option value="">-- Lihat Kamar Kosong --</option>
                                <?php 
                                mysqli_data_seek($q_kamar_kosong, 0); 
                                while($kk = mysqli_fetch_assoc($q_kamar_kosong)) : 
                                ?>
                                    <option value="<?= $kk['id']; ?>">
                                        <?= $kk['nama_kost']; ?> - KM. <?= $kk['no_kamar']; ?> (Rp <?= number_format($kk['harga_perbulan'],0,',','.'); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div id="u_warning_keluar" class="alert alert-danger border-0 rounded-4 fw-bold mb-4" style="display: none; background: #FEF2F2;">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i> Peringatan: Status Anda akan diubah menjadi tidak aktif, tagihan akan dihentikan, dan kamar ini akan segera dirilis untuk penyewa lain.
                        </div>

                        <button type="submit" name="user_mutasi" class="btn btn-dark w-100 rounded-4 py-3 fw-800 fs-6">
                            Lanjutkan <i class="fa-solid fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Logika Switch Animasi Pindah/Keluar User
        document.querySelectorAll('input[name="jenis_mutasi"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const u_kamarContainer = document.getElementById('u_kamar_baru_container');
                const u_kamarSelect = document.getElementById('u_kamar_baru_select');
                const u_warning = document.getElementById('u_warning_keluar');

                if (this.value === 'pindah') {
                    u_kamarContainer.style.display = 'block';
                    u_kamarSelect.setAttribute('required', 'required');
                    u_warning.style.display = 'none';
                } else {
                    u_kamarContainer.style.display = 'none';
                    u_kamarSelect.removeAttribute('required');
                    u_kamarSelect.value = ""; 
                    u_warning.style.display = 'block';
                }
            });
        });
    </script>
    <?php endif; ?>

    <script>
        // Logika Jam Real-time
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const clk = document.getElementById('realtime-clock');
            if(clk) clk.textContent = `${hours}:${minutes}:${seconds}`;

            let greet = "";
            if (hours >= 5 && hours < 11) greet = "Selamat Pagi";
            else if (hours >= 11 && hours < 15) greet = "Selamat Siang";
            else if (hours >= 15 && hours < 18) greet = "Selamat Sore";
            else greet = "Selamat Malam";
            
            const grt = document.getElementById('greeting');
            if(grt) grt.textContent = greet;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Inisialisasi Grafik Keuangan
        const ctx = document.getElementById('myChart');
        if(ctx) {
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?= $label_json ?>,
                    datasets: [{
                        label: 'Nominal Rp',
                        data: <?= $data_json ?>,
                        borderColor: '#4F46E5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#4F46E5',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#E2E8F0' }, ticks: { color: '#64748B', font: {family: 'Plus Jakarta Sans', weight: '600'} } },
                        x: { grid: { display: false }, ticks: { color: '#64748B', font: {family: 'Plus Jakarta Sans', weight: '600'} } }
                    }
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if($success_msg != "") : ?>
        <script>Swal.fire({ icon: 'success', title: 'Berhasil', text: '<?= $success_msg; ?>', confirmButtonColor: '#0F172A', customClass: { popup: 'rounded-4' }});</script>
    <?php endif; ?>
    <?php if($error_msg != "") : ?>
        <script>Swal.fire({ icon: 'error', title: 'Gagal', text: '<?= $error_msg; ?>', confirmButtonColor: '#EF4444', customClass: { popup: 'rounded-4' }});</script>
    <?php endif; ?>
</body>
</html>