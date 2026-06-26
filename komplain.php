<?php
session_start();
require 'koneksi.php';

// Pastikan zona waktu sinkron dengan WIB
date_default_timezone_set('Asia/Jakarta');

cek_login();

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// ==========================================
// 1. DATA PENGHUNI OTOMATIS (USER)
// ==========================================
$id_penghuni_session = null;
$nama_kost_user = "";
$no_kamar_user = "";

if ($role == 'user') {
    $user_q = mysqli_query($conn, "SELECT p.id, p.nama_penghuni, k.no_kamar, ks.nama_kost 
                                   FROM users u
                                   JOIN penghuni p ON u.id_penghuni = p.id
                                   JOIN kamar k ON p.id_kamar = k.id
                                   JOIN kost ks ON k.id_kost = ks.id
                                   WHERE u.username = '$username'");
    if($user_q && mysqli_num_rows($user_q) > 0) {
        $u_data = mysqli_fetch_assoc($user_q);
        $id_penghuni_session = $u_data['id'];
        $nama_kost_user = $u_data['nama_kost'];
        $no_kamar_user = $u_data['no_kamar'];
    }
}

// ==========================================
// 2. FUNGSI AUTO-PRIORITY & ICON
// ==========================================
function tentukanPrioritas($teks) {
    $teks = strtolower($teks);
    $high = ['listrik', 'air', 'bocor', 'kunci', 'mati', 'kebakaran', 'konslet', 'banjir', 'jebol', 'mampet'];
    $mid = ['ac', 'lampu', 'wifi', 'internet', 'wastafel', 'kloset', 'kotor', 'bau', 'berisik'];
    foreach ($high as $key) { if (strpos($teks, $key) !== false) return 'Tinggi'; }
    foreach ($mid as $key) { if (strpos($teks, $key) !== false) return 'Sedang'; }
    return 'Rendah';
}

function getCategoryIcon($kategori) {
    switch ($kategori) {
        case 'Kelistrikan': return '<div class="ticket-icon ic-warning"><i class="fa-solid fa-bolt"></i></div>';
        case 'Air': return '<div class="ticket-icon ic-info"><i class="fa-solid fa-faucet-drip"></i></div>';
        case 'Fasilitas Kamar': return '<div class="ticket-icon ic-primary"><i class="fa-solid fa-bed"></i></div>';
        case 'Kebersihan': return '<div class="ticket-icon ic-success"><i class="fa-solid fa-broom"></i></div>';
        case 'Fasilitas Umum': return '<div class="ticket-icon ic-secondary"><i class="fa-solid fa-wifi"></i></div>';
        default: return '<div class="ticket-icon ic-dark"><i class="fa-solid fa-circle-exclamation"></i></div>';
    }
}

// ==========================================
// 3. SIMPAN LAPORAN (USER)
// ==========================================
if (isset($_POST['kirim_laporan']) && $role == 'user' && $id_penghuni_session) {
    $kat = mysqli_real_escape_string($conn, $_POST['kategori']);
    $judul = mysqli_real_escape_string($conn, trim($_POST['judul']));
    $desk = mysqli_real_escape_string($conn, trim($_POST['deskripsi']));
    
    // Penentuan prioritas otomatis dari teks
    $prio = tentukanPrioritas($judul . " " . $desk);
    
    $q_insert = "INSERT INTO komplain (id_penghuni, judul_laporan, kategori, prioritas, deskripsi, status) 
                 VALUES ('$id_penghuni_session', '$judul', '$kat', '$prio', '$desk', 'Menunggu')";
    mysqli_query($conn, $q_insert);
    echo "<script>window.location='komplain.php';</script>";
}

// ==========================================
// 4. ONE-CLICK UPDATE STATUS (ADMIN)
// ==========================================
if (isset($_POST['one_click_update']) && $role == 'admin') {
    $id_k = mysqli_real_escape_string($conn, $_POST['id_komplain']);
    $st_baru = mysqli_real_escape_string($conn, $_POST['status_tujuan']);
    mysqli_query($conn, "UPDATE komplain SET status = '$st_baru' WHERE id = '$id_k'");
    echo "<script>window.location='komplain.php';</script>";
}

// ==========================================
// 5. AMBIL SEMUA DATA & STATISTIK
// ==========================================
$where = ($role == 'admin') ? "" : "WHERE c.id_penghuni = '$id_penghuni_session'";
$query = "SELECT c.*, p.nama_penghuni, k.no_kamar, ks.nama_kost, ks.kode_cabang 
          FROM komplain c
          JOIN penghuni p ON c.id_penghuni = p.id
          JOIN kamar k ON p.id_kamar = k.id
          JOIN kost ks ON k.id_kost = ks.id
          $where
          ORDER BY FIELD(c.status, 'Menunggu', 'Proses', 'Selesai'), c.tanggal_lapor DESC";
$result = mysqli_query($conn, $query);

$semua_tiket = [];
$cnt_menunggu = 0; $cnt_proses = 0; $cnt_selesai = 0;

if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $semua_tiket[] = $row;
        if($row['status'] == 'Menunggu') $cnt_menunggu++;
        if($row['status'] == 'Proses') $cnt_proses++;
        if($row['status'] == 'Selesai') $cnt_selesai++;
    }
}
$total_tiket = count($semua_tiket);

// Helper Fungsi Waktu Relatif
function formatWaktuRelatif($datetime) {
    $waktu = strtotime($datetime);
    $hari_ini = strtotime('today');
    $kemarin = strtotime('yesterday');

    if ($waktu >= $hari_ini) return "<span class='text-primary fw-bolder'>HARI INI</span>, " . date('H:i', $waktu);
    if ($waktu >= $kemarin && $waktu < $hari_ini) return "<span class='text-warning fw-bolder'>KEMARIN</span>, " . date('H:i', $waktu);
    return date('d M Y, H:i', $waktu);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Bantuan | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --primary: #4F46E5; --primary-hover: #4338CA;
            --success: #10B981; --warning: #F59E0B; --danger: #EF4444; --info: #0EA5E9;
            --bg: #F8FAFC; --surface: #ffffff;
            --text-main: #0F172A; --text-muted: #64748B;
            --border-light: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        .container-app { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }

        body.modal-open { padding-right: 0 !important; }
        html { overflow-y: scroll !important; }

        /* --- GLOBAL HEADER --- */
        .page-brand-logo { width: 45px; height: 45px; background: var(--primary); color: white; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 12px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        .page-title { font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; margin-bottom: 0; color: var(--text-main); }
        .page-subtitle { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin-left: 57px; }
        .gap-section { margin-bottom: 32px; }


        /* ==========================================
           USER VIEW STYLES
           ========================================== */
        .hero-user { background: linear-gradient(135deg, var(--primary) 0%, #312E81 100%); border-radius: 24px; padding: 40px; color: white; position: relative; overflow: hidden; box-shadow: 0 15px 35px -5px rgba(79, 70, 229, 0.3); }
        .hero-user::after { content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: rgba(255, 255, 255, 0.1); filter: blur(40px); border-radius: 50%; pointer-events: none; }
        
        .room-tag { background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 8px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }

        .quick-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; }
        .quick-card { background: var(--surface); border: 1px solid var(--border-light); padding: 20px 15px; border-radius: 20px; cursor: pointer; transition: 0.3s; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .quick-card:hover { border-color: var(--primary); transform: translateY(-5px); box-shadow: 0 15px 25px -5px rgba(79,70,229,0.15); }
        .quick-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; background: #F1F5F9; transition: 0.3s; }
        .quick-card:hover .quick-icon { background: #EEF2FF; color: var(--primary) !important; }
        .quick-card span { font-weight: 700; font-size: 0.85rem; color: var(--text-main); }

        /* User Ticket Timeline */
        .user-ticket { background: var(--surface); border: 1px solid var(--border-light); border-radius: 20px; padding: 20px; margin-bottom: 16px; transition: 0.3s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); position: relative; overflow: hidden; }
        .user-ticket::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: transparent; transition: 0.3s; }
        .user-ticket.st-Menunggu::before { background: var(--warning); }
        .user-ticket.st-Proses::before { background: var(--primary); }
        .user-ticket.st-Selesai { opacity: 0.7; background: #F8FAFC; }
        
        .user-ticket-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .ticket-id-badge { font-family: 'Monaco', monospace; background: #EEF2FF; color: var(--primary); padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; border: 1px solid #C7D2FE; }

        /* ==========================================
           ADMIN VIEW STYLES (INBOX & SEGMENTED TABS)
           ========================================== */
        .stat-grid-admin { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .admin-stat { background: var(--surface); border: 1px solid var(--border-light); border-radius: 20px; padding: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .admin-stat .ic { width: 48px; height: 48px; border-radius: 14px; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; }

        /* Segmented Control Tabs */
        .segmented-control { background: #E2E8F0; padding: 6px; border-radius: 14px; display: inline-flex; width: 100%; margin-bottom: 25px; position: relative; }
        .segmented-control .nav-link { flex: 1; text-align: center; color: var(--text-muted); font-weight: 700; font-size: 0.9rem; padding: 10px 0; border-radius: 10px; border: none; transition: 0.3s; }
        .segmented-control .nav-link:hover { color: var(--text-main); }
        .segmented-control .nav-link.active { background: var(--surface); color: var(--text-main); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

        /* Inbox Style Row */
        .inbox-row { background: var(--surface); border: 1px solid var(--border-light); border-radius: 16px; padding: 16px 20px; margin-bottom: 12px; display: flex; align-items: center; gap: 20px; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.01); }
        .inbox-row:hover { border-color: #CBD5E1; box-shadow: 0 8px 15px rgba(0,0,0,0.05); transform: translateX(4px); }
        .inbox-row.is-done { opacity: 0.6; background: #F8FAFC; }

        .ticket-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .ic-primary { background: #EEF2FF; color: var(--primary); }
        .ic-success { background: #ECFDF5; color: var(--success); }
        .ic-warning { background: #FFFBEB; color: var(--warning); }
        .ic-danger { background: #FEF2F2; color: var(--danger); }
        .ic-info { background: #E0F2FE; color: var(--info); }
        .ic-secondary { background: #F1F5F9; color: var(--text-muted); }
        .ic-dark { background: #F8FAFC; border: 1px solid var(--border-light); color: var(--text-main); }

        .inbox-main { flex-grow: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
        .inbox-title { font-weight: 800; font-size: 1rem; color: var(--text-main); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .inbox-meta { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        
        .prio-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: white; }
        .prio-Tinggi { background: var(--danger); }
        .prio-Sedang { background: var(--warning); }
        .prio-Rendah { background: var(--primary); }

        .btn-process { background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 10px; font-weight: 800; font-size: 0.8rem; transition: 0.2s; white-space: nowrap; }
        .btn-process:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(79,70,229,0.2); }
        .btn-finish { background: var(--success); color: white; border: none; padding: 8px 16px; border-radius: 10px; font-weight: 800; font-size: 0.8rem; transition: 0.2s; white-space: nowrap; }
        .btn-finish:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(16,185,129,0.2); }

        /* Shared Status Badge */
        .status-pill { padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid transparent; }
        .sp-Menunggu { background: #FFFBEB; color: #D97706; border-color: #FDE68A; }
        .sp-Proses { background: #EEF2FF; color: var(--primary); border-color: #C7D2FE; }
        .sp-Selesai { background: #ECFDF5; color: var(--success); border-color: #A7F3D0; }

        .empty-state { text-align: center; padding: 60px 20px; background: var(--surface); border-radius: 24px; border: 1px dashed var(--border-light); }

        /* Modal Custom */
        .modal-content { border: none; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .form-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { border-radius: 14px; border: 1.5px solid var(--border-light); padding: 14px 18px; font-weight: 500; font-size: 0.95rem; background: #F8FAFC; transition: 0.2s; }
        .form-control:focus { background: white; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }

        @media (max-width: 768px) {
            .stat-grid-admin { grid-template-columns: 1fr; }
            .inbox-row { flex-direction: column; align-items: flex-start; gap: 12px; position: relative; }
            .inbox-row .text-end { width: 100%; display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <?php if($role == 'user') : ?>
            
            <div class="hero-user gap-section">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center position-relative" style="z-index: 2;">
                    <div>
                        <div class="small fw-800 opacity-75 mb-2" style="letter-spacing: 1.5px;">PUSAT BANTUAN</div>
                        <h3 class="mb-0">Halo, <?= ucfirst($username); ?> 👋</h3>
                        <p class="mt-2 mb-0 opacity-75" style="font-weight: 500;">Ada kendala di kamar Anda? Kami siap membantu.</p>
                    </div>
                    <div class="mt-4 mt-md-0">
                        <div class="room-tag">
                            <i class="fa-solid fa-location-dot text-danger"></i> <?= $nama_kost_user; ?> (Unit <?= $no_kamar_user; ?>)
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center mb-3">
                <h5 class="fw-800 m-0" style="letter-spacing: -0.5px;">Lapor Cepat</h5>
                <span class="ms-2 badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill small">1 Klik</span>
            </div>
            
            <div class="quick-grid gap-section">
                <div class="quick-card" onclick="bukaModal('AC Bermasalah/Bocor', 'Fasilitas Kamar')">
                    <div class="quick-icon text-info"><i class="fa-solid fa-snowflake"></i></div>
                    <span>Kendala AC</span>
                </div>
                <div class="quick-card" onclick="bukaModal('Keran/Pipa Air Bermasalah', 'Air')">
                    <div class="quick-icon text-primary"><i class="fa-solid fa-faucet-drip"></i></div>
                    <span>Air & Saluran</span>
                </div>
                <div class="quick-card" onclick="bukaModal('Kendala Listrik/Lampu', 'Kelistrikan')">
                    <div class="quick-icon text-warning"><i class="fa-solid fa-bolt"></i></div>
                    <span>Kelistrikan</span>
                </div>
                <div class="quick-card" onclick="bukaModal('Koneksi WiFi Lemot/Mati', 'Fasilitas Umum')">
                    <div class="quick-icon text-secondary"><i class="fa-solid fa-wifi"></i></div>
                    <span>Koneksi WiFi</span>
                </div>
                <div class="quick-card" onclick="bukaModal('Perlu Pembersihan Area', 'Kebersihan')">
                    <div class="quick-icon text-success"><i class="fa-solid fa-broom"></i></div>
                    <span>Kebersihan</span>
                </div>
                <div class="quick-card bg-primary text-white border-primary" style="box-shadow: 0 8px 20px rgba(79,70,229,0.3);" onclick="bukaModal('', 'Lainnya')">
                    <div class="quick-icon bg-white text-primary"><i class="fa-solid fa-pen-to-square"></i></div>
                    <span class="text-white">Ketik Manual</span>
                </div>
            </div>

            <h5 class="fw-800 mb-3" style="letter-spacing: -0.5px;">Riwayat Tiket Anda</h5>
            <div>
                <?php 
                $empty_user = true;
                foreach($semua_tiket as $row) : 
                    $empty_user = false;
                ?>
                    <div class="user-ticket st-<?= $row['status']; ?>">
                        <div class="user-ticket-header">
                            <div class="ticket-id-badge">#TKT-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></div>
                            <div class="status-pill sp-<?= $row['status']; ?>">
                                <i class="fa-solid <?= $row['status']=='Menunggu'?'fa-clock':($row['status']=='Proses'?'fa-spinner fa-spin':'fa-check'); ?>"></i> <?= $row['status']; ?>
                            </div>
                        </div>
                        <h6 class="fw-800 text-dark mb-1"><?= $row['judul_laporan']; ?></h6>
                        <p class="text-muted small fw-600 mb-3"><?= $row['deskripsi']; ?></p>
                        <div class="small fw-700 text-muted d-flex align-items-center gap-2">
                            <i class="fa-regular fa-calendar"></i> <?= formatWaktuRelatif($row['tanggal_lapor']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if($empty_user): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-mug-hot fs-1 text-muted opacity-25 mb-3"></i>
                        <h6 class="fw-bold text-dark mb-1">Ruangan Anda Bebas Kendala!</h6>
                        <p class="text-muted small mb-0">Jika ada fasilitas yang rusak, segera lapor melalui menu di atas.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <?php if($role == 'admin') : ?>
            
            <div class="gap-section d-flex align-items-center">
                <div class="page-brand-logo"><i class="fa-solid fa-headset"></i></div>
                <div>
                    <h1 class="page-title">Helpdesk Operasional</h1>
                    <p class="page-subtitle m-0">Inbox kendali laporan kendala fasilitas properti.</p>
                </div>
            </div>

            <div class="stat-grid-admin gap-section">
                <div class="admin-stat border-warning border-opacity-50">
                    <div class="ic bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-inbox"></i></div>
                    <div>
                        <div class="form-label mb-1">Tiket Masuk</div>
                        <div class="fs-4 fw-800 text-dark lh-1"><?= $cnt_menunggu; ?> <span class="fs-6 fw-bold text-muted">Tiket</span></div>
                    </div>
                </div>
                <div class="admin-stat">
                    <div class="ic bg-primary bg-opacity-10 text-primary"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                    <div>
                        <div class="form-label mb-1">Sedang Dikerjakan</div>
                        <div class="fs-4 fw-800 text-dark lh-1"><?= $cnt_proses; ?> <span class="fs-6 fw-bold text-muted">Tiket</span></div>
                    </div>
                </div>
                <div class="admin-stat">
                    <div class="ic bg-success bg-opacity-10 text-success"><i class="fa-solid fa-check-double"></i></div>
                    <div>
                        <div class="form-label mb-1">Selesai (Total)</div>
                        <div class="fs-4 fw-800 text-dark lh-1"><?= $cnt_selesai; ?> <span class="fs-6 fw-bold text-muted">Tiket</span></div>
                    </div>
                </div>
            </div>

            <ul class="nav segmented-control" id="inboxTabs">
                <li class="nav-item flex-fill">
                    <button class="nav-link active w-100" data-bs-toggle="pill" data-bs-target="#pane-menunggu">
                        Menunggu <span class="badge bg-warning text-dark ms-1 rounded-pill"><?= $cnt_menunggu; ?></span>
                    </button>
                </li>
                <li class="nav-item flex-fill">
                    <button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#pane-proses">
                        Diproses <span class="badge bg-primary text-white ms-1 rounded-pill"><?= $cnt_proses; ?></span>
                    </button>
                </li>
                <li class="nav-item flex-fill">
                    <button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#pane-semua">
                        Riwayat <span class="badge bg-secondary text-white ms-1 rounded-pill"><?= $total_tiket; ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content mt-4">
                
                <div class="tab-pane fade show active" id="pane-menunggu">
                    <?php foreach($semua_tiket as $row) : if($row['status'] != 'Menunggu') continue; ?>
                        <div class="inbox-row border-warning border-opacity-25">
                            <?= getCategoryIcon($row['kategori']); ?>
                            
                            <div class="inbox-main">
                                <div class="inbox-title"><?= $row['judul_laporan']; ?></div>
                                <div class="inbox-meta mt-1">
                                    <span class="prio-badge prio-<?= $row['prioritas']; ?>"><?= $row['prioritas']; ?></span>
                                    <span class="text-primary fw-800">#TKT-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    <span>•</span>
                                    <span><i class="fa-solid fa-user me-1 opacity-50"></i><?= $row['nama_penghuni']; ?> (<?= $row['kode_cabang']; ?>-<?= $row['no_kamar']; ?>)</span>
                                </div>
                            </div>

                            <div class="text-end d-flex align-items-center gap-4">
                                <div class="small fw-700 text-muted d-none d-md-block">
                                    <?= formatWaktuRelatif($row['tanggal_lapor']); ?>
                                </div>
                                <form method="POST" class="m-0">
                                    <input type="hidden" name="id_komplain" value="<?= $row['id']; ?>">
                                    <input type="hidden" name="status_tujuan" value="Proses">
                                    <button type="submit" name="one_click_update" class="btn-process">
                                        Kerjakan <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if($cnt_menunggu == 0): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox fs-1 text-muted opacity-25 mb-3"></i>
                            <h6 class="fw-bold text-dark mb-0">Inbox Kosong.</h6>
                            <p class="text-muted small mt-1">Semua keluhan penghuni sudah tertangani dengan baik.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade" id="pane-proses">
                    <?php foreach($semua_tiket as $row) : if($row['status'] != 'Proses') continue; ?>
                        <div class="inbox-row border-primary border-opacity-25">
                            <?= getCategoryIcon($row['kategori']); ?>
                            
                            <div class="inbox-main">
                                <div class="inbox-title"><?= $row['judul_laporan']; ?></div>
                                <div class="inbox-meta mt-1">
                                    <span class="prio-badge prio-<?= $row['prioritas']; ?>"><?= $row['prioritas']; ?></span>
                                    <span class="text-primary fw-800">#TKT-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    <span>•</span>
                                    <span><i class="fa-solid fa-user me-1 opacity-50"></i><?= $row['nama_penghuni']; ?> (<?= $row['kode_cabang']; ?>-<?= $row['no_kamar']; ?>)</span>
                                </div>
                            </div>

                            <div class="text-end d-flex align-items-center gap-4">
                                <div class="small fw-700 text-primary d-none d-md-block">
                                    <i class="fa-solid fa-spinner fa-spin me-1"></i> Sedang Jalan
                                </div>
                                <form method="POST" class="m-0">
                                    <input type="hidden" name="id_komplain" value="<?= $row['id']; ?>">
                                    <input type="hidden" name="status_tujuan" value="Selesai">
                                    <button type="submit" name="one_click_update" class="btn-finish">
                                        <i class="fa-solid fa-check me-1"></i> Selesaikan
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if($cnt_proses == 0): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-check-double fs-1 text-muted opacity-25 mb-3"></i>
                            <h6 class="fw-bold text-dark mb-0">Tidak ada pekerjaan tertunda.</h6>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade" id="pane-semua">
                    <?php foreach($semua_tiket as $row) : ?>
                        <div class="inbox-row <?= $row['status'] == 'Selesai' ? 'is-done' : ''; ?>">
                            <?= getCategoryIcon($row['kategori']); ?>
                            <div class="inbox-main">
                                <div class="inbox-title"><?= $row['judul_laporan']; ?></div>
                                <div class="inbox-meta mt-1">
                                    <span class="text-dark fw-800">#TKT-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    <span>•</span>
                                    <span><?= $row['nama_penghuni']; ?> (Kmr <?= $row['no_kamar']; ?>)</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="status-pill sp-<?= $row['status']; ?>">
                                    <?= $row['status']; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if($total_tiket == 0): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-database fs-1 text-muted opacity-25 mb-3"></i>
                            <h6 class="fw-bold text-dark mb-0">Database kosong.</h6>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        <?php endif; ?>

    </div>

    <div class="modal fade" id="modalLapor" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-800 mb-0">Buat Tiket Keluhan</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="kategori" id="formKategori">
                        <div class="mb-3">
                            <label class="form-label">Ringkasan Masalah <span class="text-danger">*</span></label>
                            <input type="text" name="judul" id="formJudul" class="form-control" required autocomplete="off" placeholder="Misal: AC panas">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Keterangan Tambahan</label>
                            <textarea name="deskripsi" id="formDeskripsi" class="form-control" rows="3" placeholder="Jelaskan detail kendala agar teknisi mudah memahami..."></textarea>
                        </div>
                        <button type="submit" name="kirim_laporan" class="btn btn-primary w-100 py-3 rounded-3 fw-800 fs-6 shadow-sm">
                            <i class="fa-solid fa-paper-plane me-2"></i> Kirim Tiket Bantuan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal Trigger Logic
        function bukaModal(judul, kategori) {
            document.getElementById('formKategori').value = kategori;
            document.getElementById('formJudul').value = judul;
            setTimeout(() => document.getElementById('formDeskripsi').focus(), 400);
            new bootstrap.Modal(document.getElementById('modalLapor')).show();
        }
    </script>
</body>
</html>