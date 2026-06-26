<?php
session_start();
require 'koneksi.php';
cek_login();

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$hari_ini = date('Y-m-d');
$bulan_ini = date('m');
$tahun_ini = date('Y');

$auto_invoice_warning = "";

// ========================================================
// AMBIL DATA PENGATURAN SISTEM
// ========================================================
$q_set = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1");
$setting = mysqli_fetch_assoc($q_set);

$tenggang_waktu_hari = $setting['tgl_jatuh_tempo'] ?? 7; 
$denda_keterlambatan = $setting['denda_terlambat'] ?? 0;
$template_wa = $setting['pesan_tagihan'] ?? "Mohon segera melunasi tagihan kost Anda bulan ini.";

// ========================================================
// 1. FITUR AUTO-INVOICE (SIKLUS 30 HARI & FILTER AKTIF)
// ========================================================
try {
    // KUNCI REALISTIS: Hanya proses penghuni yang 'Aktif'. 
    // Jika 'Keluar', tidak akan dapat tagihan baru.
    // Jika 'Pindah', k.harga_perbulan otomatis mengambil harga kamar baru.
    $q_penghuni_aktif = mysqli_query($conn, "SELECT p.id, k.harga_perbulan 
                                             FROM penghuni p 
                                             JOIN kamar k ON p.id_kamar = k.id 
                                             WHERE p.status_penghuni = 'Aktif'");

    if ($q_penghuni_aktif) {
        while ($penghuni = mysqli_fetch_assoc($q_penghuni_aktif)) {
            $id_p_auto = $penghuni['id'];
            $harga_kamar = isset($penghuni['harga_perbulan']) ? $penghuni['harga_perbulan'] : 0;
            
            // Cari tagihan terakhir untuk penghuni ini
            $cek_last = mysqli_query($conn, "SELECT created_at FROM tagihan WHERE id_penghuni = '$id_p_auto' ORDER BY created_at DESC LIMIT 1");
            
            $buat_baru = false;

            if (mysqli_num_rows($cek_last) == 0) {
                // Penghuni baru, belum pernah ada tagihan
                $buat_baru = true;
            } else {
                // Sudah ada riwayat. Hitung 1 bulan (Siklus 30 Hari) dari tagihan terakhir
                $last_inv = mysqli_fetch_assoc($cek_last);
                $waktu_terakhir = strtotime($last_inv['created_at']);
                $siklus_berikutnya = strtotime('+1 month', $waktu_terakhir); 
                
                // Jika waktu real-time hari ini sudah melewati siklus berikutnya
                if (time() >= $siklus_berikutnya) {
                    $buat_baru = true;
                }
            }
            
            // Generate Invoice Baru sesuai harga kamar saat ini
            if ($buat_baru && $harga_kamar > 0) {
                $no_inv_baru = "INV-" . date('Ymd') . "-" . rand(100, 999) . $id_p_auto;
                mysqli_query($conn, "INSERT INTO tagihan (id_penghuni, no_invoice, jumlah, status, created_at) 
                                     VALUES ('$id_p_auto', '$no_inv_baru', '$harga_kamar', 'Belum Lunas', NOW())");
            }
        }
    }
} catch (mysqli_sql_exception $e) {
    $auto_invoice_warning = "Peringatan Sistem: " . $e->getMessage();
}

// ========================================================
// 2. LOGIKA STATISTIK INFORMATIF
// ========================================================
$piutang = 0; $lunas = 0; $jml_tagihan_aktif = 0; $collection_rate = 0;
$next_due_timestamp = 0;
$has_pending = false; 

if ($role == 'admin') {
    try {
        $stat_res = mysqli_query($conn, "
            SELECT 
                SUM(CASE WHEN status = 'Belum Lunas' THEN jumlah ELSE 0 END) as piutang,
                SUM(CASE WHEN status = 'Lunas' THEN jumlah ELSE 0 END) as lunas,
                COUNT(CASE WHEN status = 'Belum Lunas' THEN 1 END) as tagihan_aktif
            FROM tagihan 
            WHERE MONTH(created_at) = '$bulan_ini' AND YEAR(created_at) = '$tahun_ini'
        ");
        $stat = mysqli_fetch_assoc($stat_res);
        $piutang = $stat['piutang'] ?? 0;
        $lunas = $stat['lunas'] ?? 0;
        $jml_tagihan_aktif = $stat['tagihan_aktif'] ?? 0;
        
        $total_uang = $piutang + $lunas;
        $collection_rate = ($total_uang > 0) ? round(($lunas / $total_uang) * 100) : 0;
    } catch(Exception $e) {
        $piutang = 0; $lunas = 0; $jml_tagihan_aktif = 0; $collection_rate = 0;
    }
} else {
    $user_res = mysqli_query($conn, "SELECT id_penghuni FROM users WHERE username = '$username'");
    $user_data = mysqli_fetch_assoc($user_res);
    $id_p = $user_data['id_penghuni'];
    
    // Cek apakah user berstatus AKTIF atau KELUAR
    $is_active_tenant = false;
    if($id_p) {
        $cek_status = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status_penghuni FROM penghuni WHERE id = '$id_p'"));
        if(isset($cek_status['status_penghuni']) && $cek_status['status_penghuni'] == 'Aktif') {
            $is_active_tenant = true;
        }
    }

    if ($id_p) {
        $stat_res = mysqli_query($conn, "
            SELECT 
                SUM(CASE WHEN status = 'Belum Lunas' THEN jumlah ELSE 0 END) as piutang,
                SUM(CASE WHEN status = 'Lunas' THEN jumlah ELSE 0 END) as lunas,
                COUNT(CASE WHEN status = 'Belum Lunas' THEN 1 END) as tagihan_aktif
            FROM tagihan WHERE id_penghuni = '$id_p'
        ");
        $stat = mysqli_fetch_assoc($stat_res);
        $piutang = $stat['piutang'] ?? 0;
        $lunas = $stat['lunas'] ?? 0;
        $jml_tagihan_aktif = $stat['tagihan_aktif'] ?? 0;
        
        $has_pending = ($jml_tagihan_aktif > 0);

        if (!$has_pending && $is_active_tenant) {
            $cek_last = mysqli_query($conn, "SELECT created_at FROM tagihan WHERE id_penghuni = '$id_p' ORDER BY created_at DESC LIMIT 1");
            if(mysqli_num_rows($cek_last) > 0) {
                $last_data = mysqli_fetch_assoc($cek_last);
                // Prediksi Siklus Tagihan Berikutnya
                $next_due_timestamp = strtotime('+1 month', strtotime($last_data['created_at']));
            } else {
                $next_due_timestamp = strtotime('today'); // Hari ini jika belum pernah
            }
        }
    }
}

// ========================================================
// 3. FILTER & SORT
// ========================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';

switch ($sort) {
    case 'terbaru': $order_by = "tagihan.id DESC"; break;
    case 'nominal_tinggi': $order_by = "tagihan.jumlah DESC"; break;
    default: $order_by = "tagihan.status ASC, tagihan.created_at DESC"; break;
}

// ========================================================
// 4. QUERY BERDASARKAN ROLE
// ========================================================
try {
    if ($role == 'admin') {
        $query_str = "SELECT tagihan.*, penghuni.nama_penghuni, penghuni.no_hp, k.kode_cabang, k.nama_kost, kam.no_kamar 
                      FROM tagihan 
                      JOIN penghuni ON tagihan.id_penghuni = penghuni.id
                      LEFT JOIN kamar kam ON penghuni.id_kamar = kam.id
                      LEFT JOIN kost k ON kam.id_kost = k.id
                      WHERE (penghuni.nama_penghuni LIKE '%$search%' OR tagihan.no_invoice LIKE '%$search%')";
        if($filter_status != '') $query_str .= " AND tagihan.status = '$filter_status'";
        $query_str .= " ORDER BY $order_by";
    } else {
        if ($id_p) {
            $query_str = "SELECT tagihan.*, penghuni.nama_penghuni, k.kode_cabang, k.nama_kost, kam.no_kamar 
                          FROM tagihan 
                          JOIN penghuni ON tagihan.id_penghuni = penghuni.id
                          LEFT JOIN kamar kam ON penghuni.id_kamar = kam.id
                          LEFT JOIN kost k ON kam.id_kost = k.id
                          WHERE tagihan.id_penghuni = '$id_p' AND tagihan.no_invoice LIKE '%$search%'";
            if($filter_status != '') $query_str .= " AND tagihan.status = '$filter_status'";
            $query_str .= " ORDER BY $order_by";
        } else {
            $query_str = "SELECT * FROM tagihan WHERE id = -1"; 
        }
    }
    $result = mysqli_query($conn, $query_str);
} catch (Exception $e) {
    $result = false; 
    $auto_invoice_warning = "Error Database Relasi: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Penagihan | KostAdmin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --primary: #4F46E5; --primary-hover: #4338CA;
            --bg: #F8FAFC; --surface: #ffffff;
            --text-main: #0F172A; --text-muted: #64748B;
            --border-light: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        
        .container-app { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        /* --- BRAND HEADER --- */
        .page-brand-logo { width: 45px; height: 45px; background: var(--primary); color: white; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 12px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        .page-title { font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; margin-bottom: 0; }
        .page-subtitle { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin-left: 57px; }
        .gap-section { margin-bottom: 32px; }

        /* --- DASHBOARD CARDS --- */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; }
        .stat-card-info { background: var(--surface); border-radius: 24px; border: 1px solid var(--border-light); padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; align-items: flex-start; gap: 16px; transition: 0.3s; }
        .stat-card-info:hover { transform: translateY(-4px); box-shadow: 0 12px 25px -5px rgba(0,0,0,0.05); border-color: #cbd5e1; }
        
        .stat-card-dark { background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border: none; color: white; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.4); position: relative; overflow: hidden; }
        .stat-card-dark::after { content: ''; position: absolute; top: -50%; right: -20%; width: 150px; height: 150px; background: rgba(79, 70, 229, 0.3); filter: blur(40px); border-radius: 50%; }
        .ic-dark { background: rgba(255,255,255,0.1); color: #38BDF8; border: 1px solid rgba(255,255,255,0.1); }
        .text-neon { color: #38BDF8 !important; }

        .ic-box { width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; z-index: 1; }
        .ic-red { background: #FEF2F2; color: #E11D48; }
        .ic-green { background: #F0FDF4; color: #10B981; }
        .ic-blue { background: #EEF2FF; color: #4F46E5; }
        
        .stat-text { z-index: 1; position: relative; }
        .stat-text p { margin: 0 0 6px 0; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card-dark .stat-text p { color: #94A3B8; }
        .stat-text h3 { margin: 0; font-weight: 800; font-size: 1.6rem; letter-spacing: -0.5px; color: var(--text-main); line-height: 1; font-variant-numeric: tabular-nums; }
        .stat-card-dark .stat-text h3 { color: white; font-family: 'Monaco', monospace; font-size: 1.3rem; margin-top: 4px; }
        
        .stat-desc { font-size: 0.8rem; font-weight: 600; margin-top: 10px; display: flex; align-items: center; gap: 6px; }
        .rate-bar-bg { width: 100%; height: 6px; background: #F1F5F9; border-radius: 10px; margin-top: 12px; overflow: hidden; }
        .rate-bar-fill { height: 100%; background: var(--primary); border-radius: 10px; transition: width 1s ease; }

        /* --- UNIFIED FILTER --- */
        .unified-filter { background: var(--surface); border: 1px solid var(--border-light); border-radius: 100px; padding: 8px; display: flex; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); width: fit-content; transition: 0.3s; }
        .unified-filter:focus-within { border-color: var(--primary); box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.1); }
        .uf-search { display: flex; align-items: center; padding: 0 20px; position: relative; min-width: 320px; }
        .uf-search i { color: #94A3B8; font-size: 1rem; }
        .uf-search input { border: none; background: transparent; padding: 10px 15px; font-size: 0.95rem; font-weight: 500; outline: none; width: 100%; color: var(--text-main); }
        .uf-divider { width: 1px; height: 28px; background: var(--border-light); margin: 0 8px; }
        .uf-select { border: none; background: transparent; padding: 10px 20px; font-size: 0.9rem; font-weight: 600; color: var(--text-main); outline: none; cursor: pointer; appearance: none; -webkit-appearance: none; }
        .uf-btn { background: var(--text-main); color: white; border: none; border-radius: 50px; padding: 12px 28px; font-weight: 700; font-size: 0.9rem; transition: 0.2s; margin-left: 8px; }
        .uf-btn:hover { background: var(--primary); transform: translateY(-1px); }
        .uf-reset { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 50%; background: #FEF2F2; color: #E11D48; text-decoration: none; margin-left: 12px; transition: 0.2s; }
        .uf-reset:hover { background: #FECACA; color: #BE123C; }

        @media (max-width: 768px) {
            .unified-filter { flex-direction: column; border-radius: 16px; width: 100%; align-items: stretch; padding: 15px; }
            .uf-divider { width: 100%; height: 1px; margin: 10px 0; }
            .uf-btn, .uf-reset { width: 100%; border-radius: 10px; margin: 10px 0 0 0; }
        }

        /* --- TABLE --- */
        .table-card { background: var(--surface); border-radius: 24px; border: 1px solid var(--border-light); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); overflow: hidden; }
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table thead th { background: #F8FAFC; color: #64748B; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 20px 24px; border-bottom: 1px solid var(--border-light); white-space: nowrap; }
        .custom-table tbody tr { transition: 0.2s ease; border-bottom: 1px solid #F1F5F9; }
        .custom-table tbody tr:hover { background: #F8FAFC; }
        .custom-table td { padding: 20px 24px; vertical-align: middle; }

        /* AVATAR & LOGOS */
        .tenant-box { display: flex; align-items: center; gap: 16px; }
        .tenant-avatar { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .room-badge { display: inline-flex; align-items: center; background: #F1F5F9; color: var(--text-main); padding: 2px 8px; border-radius: 6px; font-weight: 800; font-size: 0.7rem; border: 1px solid var(--border-light); margin-left: 8px; }
        
        .tenant-name { font-weight: 800; color: var(--text-main); font-size: 1rem; margin-bottom: 2px; display: flex; align-items: center; }
        .tenant-prop { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
        
        .inv-badge { font-family: 'Monaco', monospace; color: var(--text-main); font-weight: 700; font-size: 0.85rem; background: #F1F5F9; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-light); display: inline-block;}
        .inv-date { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; margin-top: 8px; display: block; }
        
        .amount-val { font-weight: 800; color: var(--text-main); font-size: 1.15rem; letter-spacing: -0.5px; }

        .status-pill { padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .pill-paid { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }
        .pill-warning { background: #FFF7ED; color: #D97706; border: 1px solid #FDE68A; }
        .pill-danger { background: #FEF2F2; color: #E11D48; border: 1px solid #FECACA; }

        /* LIVE PULSING DOT FOR PENDING TIMER */
        .live-timer { display: flex; align-items: center; gap: 10px; background: #F8FAFC; padding: 6px 14px; border-radius: 50px; width: fit-content; border: 1px solid var(--border-light); }
        .live-timer.late { background: #FEF2F2; border-color: #FECACA; }
        
        .pulse-dot { width: 8px; height: 8px; background-color: #F59E0B; border-radius: 50%; box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); animation: pulse-orange 1.5s infinite; flex-shrink: 0; }
        @keyframes pulse-orange {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
            70% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        .countdown-text { font-size: 0.8rem; font-weight: 800; color: var(--text-main); font-variant-numeric: tabular-nums; letter-spacing: 0.5px; }

        /* ACTION BUTTONS */
        .action-group { display: inline-flex; background: #F8FAFC; border: 1px solid var(--border-light); border-radius: 14px; padding: 4px; gap: 4px; }
        .btn-icon { width: 36px; height: 36px; border-radius: 10px; border: none; background: transparent; color: #64748B; display: flex; align-items: center; justify-content: center; transition: 0.2s; cursor: pointer; font-size: 0.9rem; text-decoration: none; }
        .btn-icon:hover { background: white; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .btn-icon.view:hover { color: var(--primary); }
        .btn-icon.wa:hover { color: #059669; }
        
        .btn-pay-wrapper { display: flex; flex-direction: column; align-items: center; gap: 6px; }
        .btn-icon.pay { background: var(--primary); color: white; width: auto; padding: 0 20px; font-weight: 700; font-size: 0.85rem; height: 36px; }
        .btn-icon.pay:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(79,70,229,0.3); }
        .payment-logos { display: flex; gap: 4px; opacity: 0.6; font-size: 0.7rem; color: var(--text-main); }

        .empty-state { text-align: center; padding: 80px 20px; }

        /* MODAL INVOICE */
        .modern-invoice .modal-content { border: none; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .inv-header { background: #0F172A; color: white; padding: 30px; position: relative; }
        .inv-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .inv-logo { width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .inv-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; margin: 0; }
        .btn-close-inv { position: absolute; top: 25px; right: 25px; background: rgba(255,255,255,0.1); border: none; width: 32px; height: 32px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; }
        .btn-close-inv:hover { background: rgba(255,255,255,0.2); }
        .inv-body { background: #F8FAFC; padding: 30px; }
        .inv-paper { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border-light); }
        .item-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed var(--border-light); }
        .item-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }
        .item-value { font-size: 0.9rem; font-weight: 700; color: var(--text-main); text-align: right; }
        .total-row { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--text-main); }
        .total-label { font-size: 1rem; font-weight: 800; color: var(--text-main); }
        .total-value { font-size: 1.8rem; font-weight: 800; color: var(--primary); letter-spacing: -1px; }
        .inv-footer { background: white; padding: 20px 30px; border-top: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; }
        
        @media print {
            body * { visibility: hidden; }
            #invoiceModal, #invoiceModal * { visibility: visible; }
            #invoiceModal { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; }
            .inv-header { background: white !important; color: black !important; border-bottom: 2px solid black; }
            .btn-close-inv, .inv-footer { display: none !important; }
            .inv-paper { box-shadow: none; border: none; padding: 0; }
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <?php if($auto_invoice_warning != "") : ?>
            <div class="alert alert-warning alert-dismissible fade show fw-bold border-0 shadow-sm" style="border-radius: 15px;" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i> <?= $auto_invoice_warning; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="gap-section d-flex align-items-center">
            <div class="page-brand-logo">
                <i class="fa-solid fa-house-chimney-window"></i>
            </div>
            <div>
                <h1 class="page-title"><?= ($role == 'admin') ? 'Manajemen Tagihan' : 'Tagihan Berjalan'; ?></h1>
                <p class="page-subtitle m-0"><?= ($role == 'admin') ? 'Tinjauan metrik penagihan siklus 30-hari.' : 'Daftar kewajiban sewa properti yang perlu diselesaikan.'; ?></p>
            </div>
        </div>

        <div class="stat-grid gap-section">
            
            <?php if($role == 'user' && $id_p && !$has_pending && $is_active_tenant) : ?>
            <div class="stat-card-info stat-card-dark">
                <div class="ic-box ic-dark"><i class="fa-solid fa-calendar-clock"></i></div>
                <div class="stat-text w-100">
                    <p>Siklus Tagihan Berikutnya</p>
                    <h3 id="next-billing-timer" data-next="<?= $next_due_timestamp; ?>">-- : -- : --</h3>
                    <div class="stat-desc text-white-50"><i class="fa-solid fa-bolt text-neon me-1"></i> Terbit otomatis (Siklus 30 Hari)</div>
                </div>
            </div>
            <?php endif; ?>

            <div class="stat-card-info">
                <div class="ic-box ic-red"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div class="stat-text w-100">
                    <p><?= ($role == 'admin') ? 'Piutang Menunggu' : 'Total Tagihan'; ?></p>
                    <h3>Rp <?= number_format($piutang, 0, ',', '.'); ?></h3>
                    <div class="stat-desc text-danger"><i class="fa-solid fa-clock"></i> <?= $jml_tagihan_aktif; ?> Faktur Aktif</div>
                </div>
            </div>

            <div class="stat-card-info">
                <div class="ic-box ic-green"><i class="fa-solid fa-vault"></i></div>
                <div class="stat-text w-100">
                    <p><?= ($role == 'admin') ? 'Dana Diterima' : 'Sudah Dilunasi'; ?></p>
                    <h3>Rp <?= number_format($lunas, 0, ',', '.'); ?></h3>
                    <div class="stat-desc text-success"><i class="fa-solid fa-circle-check"></i> Tercatat di sistem</div>
                </div>
            </div>

            <?php if($role == 'admin') : ?>
            <div class="stat-card-info">
                <div class="ic-box ic-blue"><i class="fa-solid fa-percent"></i></div>
                <div class="stat-text w-100">
                    <p>Rasio Kesuksesan</p>
                    <h3><?= $collection_rate; ?>%</h3>
                    <div class="rate-bar-bg"><div class="rate-bar-fill" style="width: <?= $collection_rate; ?>%;"></div></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="gap-section">
            <form method="GET" action="" class="d-flex flex-wrap align-items-center">
                <div class="unified-filter mb-0">
                    <div class="uf-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Cari nomor faktur atau nama penghuni..." value="<?= htmlspecialchars($search); ?>">
                    </div>
                    <div class="uf-divider d-none d-md-block"></div>
                    <select name="status" class="uf-select">
                        <option value="">Semua Status</option>
                        <option value="Belum Lunas" <?= $filter_status == 'Belum Lunas' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Lunas" <?= $filter_status == 'Lunas' ? 'selected' : ''; ?>>Lunas</option>
                    </select>
                    <div class="uf-divider d-none d-md-block"></div>
                    <select name="sort" class="uf-select">
                        <option value="default" <?= $sort == 'default' ? 'selected' : ''; ?>>Terbaru / Mendesak</option>
                        <option value="nominal_tinggi" <?= $sort == 'nominal_tinggi' ? 'selected' : ''; ?>>Nominal Tertinggi</option>
                    </select>
                    <button type="submit" class="uf-btn">Terapkan</button>
                </div>
                
                <?php if(!empty($search) || !empty($filter_status) || $sort != 'default') : ?>
                    <a href="tagihan.php" class="uf-reset" data-bs-toggle="tooltip" title="Reset Filter"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th class="ps-4">No. Faktur</th>
                            <th>Penyewa & Unit</th>
                            <th>Tagihan (IDR)</th>
                            <th>Batas Waktu Pembayaran</th>
                            <th>Status</th>
                            <th class="text-center pe-4">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result && mysqli_num_rows($result) > 0) : ?>
                            <?php while($row = mysqli_fetch_assoc($result)) : 
                                
                                // =========================================================
                                // LOGIKA JATUH TEMPO (TENGGANG WAKTU)
                                // =========================================================
                                $waktu_buat = strtotime($row['created_at']);
                                
                                // Jatuh Tempo adalah: Tanggal Invoice Terbit + TENGGANG WAKTU dari database pengaturan
                                $waktu_jatuh_tempo = strtotime('+' . $tenggang_waktu_hari . ' days', $waktu_buat);

                                $is_terlambat = false;
                                $total_bayar = $row['jumlah'];
                                
                                if (time() > $waktu_jatuh_tempo && $row['status'] == 'Belum Lunas') {
                                    $is_terlambat = true;
                                    $total_bayar += $denda_keterlambatan;
                                }

                                // Pesan WA Otomatis
                                $wa_msg = "Halo " . $row['nama_penghuni'] . ",\n\n" . 
                                          $template_wa . "\n\n" .
                                          "Detail Tagihan:\n" .
                                          "- No Faktur: #" . $row['no_invoice'] . "\n" .
                                          "- Tgl Terbit: " . date('d M Y', $waktu_buat) . "\n" .
                                          "- Jatuh Tempo: " . date('d M Y', $waktu_jatuh_tempo) . "\n" .
                                          "- Total Bayar: *Rp " . number_format($total_bayar, 0, ',', '.') . "*\n\n" .
                                          "Mohon segera dilunasi. Abaikan pesan ini jika Anda sudah membayar.";
                                
                                // Data Modal
                                $modal_data = htmlspecialchars(json_encode([
                                    'invoice' => $row['no_invoice'],
                                    'nama' => $row['nama_penghuni'],
                                    'kamar' => $row['no_kamar'] ?? '-',
                                    'kost' => $row['nama_kost'] ?? '-',
                                    'jumlah' => number_format($total_bayar,0,',','.'),
                                    'tanggal' => date('d M Y', strtotime($row['created_at'])),
                                    'status' => $row['status'],
                                    'denda' => $is_terlambat ? number_format($denda_keterlambatan,0,',','.') : 0
                                ]));

                                $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($row['nama_penghuni']) . "&background=random&color=fff&bold=true&rounded=true";
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="inv-badge"><i class="fa-solid fa-file-invoice me-1 text-muted"></i> <?= $row['no_invoice']; ?></span>
                                    <span class="inv-date">Terbit: <?= date('d M Y', strtotime($row['created_at'])); ?></span>
                                </td>
                                
                                <td>
                                    <div class="tenant-box">
                                        <img src="<?= $avatar_url; ?>" alt="Avatar" class="tenant-avatar">
                                        <div>
                                            <div class="tenant-name">
                                                <?= ucwords(strtolower($row['nama_penghuni'])); ?>
                                                <?php if($row['no_kamar']) : ?>
                                                    <span class="room-badge" data-bs-toggle="tooltip" title="Unit Kamar"><i class="fa-solid fa-door-open me-1 opacity-50"></i> <?= $row['no_kamar']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if(isset($row['nama_kost'])) : ?>
                                                <div class="tenant-prop"><i class="fa-solid fa-location-dot me-1 opacity-50 text-danger"></i> <?= isset($row['kode_cabang']) ? $row['kode_cabang'] : ''; ?> - <?= $row['nama_kost']; ?></div>
                                            <?php else : ?>
                                                <div class="tenant-prop fst-italic text-muted">Sudah Keluar/Pindah</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="amount-val">Rp <?= number_format($total_bayar, 0, ',', '.'); ?></div>
                                    <?php if($is_terlambat && $denda_keterlambatan > 0) : ?>
                                        <div class="text-danger small fw-bold mt-1" style="font-size: 0.7rem;"><i class="fa-solid fa-plus text-danger"></i> Denda Rp <?= number_format($denda_keterlambatan, 0, ',', '.'); ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if($row['status'] == 'Belum Lunas') : ?>
                                        <div class="fw-bold text-dark mb-1"><?= date('d M Y, H:i', $waktu_jatuh_tempo); ?></div>
                                        
                                        <?php if($is_terlambat) : ?>
                                            <span class="badge bg-danger">Melewati Tenggang Waktu</span>
                                        <?php else : ?>
                                            <div class="live-timer countdown-container" data-expire="<?= $waktu_jatuh_tempo; ?>">
                                                <span class="pulse-dot"></span>
                                                <span class="countdown-text">--:--:--</span>
                                            </div>
                                        <?php endif; ?>

                                    <?php else : ?>
                                        <span class="text-muted small fw-bold"><i class="fa-solid fa-check-circle text-success me-1"></i> Tuntas</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if($row['status'] == 'Lunas') : ?>
                                        <span class="status-pill pill-paid"><i class="fa-solid fa-check"></i> Lunas</span>
                                    <?php elseif($is_terlambat) : ?>
                                        <span class="status-pill pill-danger" data-bs-toggle="tooltip" title="Melewati Jatuh Tempo!"><i class="fa-solid fa-triangle-exclamation"></i> Kritis</span>
                                    <?php else : ?>
                                        <span class="status-pill pill-warning"><i class="fa-solid fa-clock"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center pe-4">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <div class="action-group shadow-sm">
                                            <button type="button" class="btn-icon view" data-bs-toggle="tooltip" title="Rincian Faktur" onclick='openInvoiceModal(<?= $modal_data; ?>)'>
                                                <i class="fa-solid fa-eye"></i>
                                            </button>

                                            <?php if($role == 'admin') : ?>
                                                <?php if($row['status'] == 'Belum Lunas') : ?>
                                                    <a href="https://wa.me/<?= preg_replace('/^0/', '62', isset($row['no_hp']) ? $row['no_hp'] : ''); ?>?text=<?= urlencode($wa_msg); ?>" target="_blank" class="btn-icon wa" data-bs-toggle="tooltip" title="Hubungi via WA">
                                                        <i class="fa-brands fa-whatsapp"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <?php if($row['status'] == 'Belum Lunas') : ?>
                                                    <a href="proses_bayar.php?invoice=<?= $row['no_invoice']; ?>" class="btn-icon pay text-decoration-none">
                                                        Bayar <i class="fa-solid fa-arrow-right ms-1"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if($role == 'user' && $row['status'] == 'Belum Lunas') : ?>
                                        <div class="payment-logos ms-1" data-bs-toggle="tooltip" title="Menerima QRIS, Transfer & E-Wallet">
                                            <i class="fa-solid fa-qrcode text-dark"></i>
                                            <i class="fa-brands fa-cc-visa text-primary"></i>
                                            <i class="fa-brands fa-cc-mastercard text-danger"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fa-solid fa-inbox mb-3" style="font-size: 3.5rem; color: #CBD5E1;"></i>
                                    <h5 class="fw-bold text-dark mb-1">Tidak Ada Data</h5>
                                    <p class="text-muted small mb-0">Belum ada faktur yang tercatat atau sesuai dengan pencarian Anda.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade modern-invoice" id="invoiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                
                <div class="inv-header">
                    <button type="button" class="btn-close-inv" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i></button>
                    <div class="inv-brand">
                        <div class="inv-logo"><i class="fa-solid fa-house-chimney-window"></i></div>
                        <div>
                            <h4 class="inv-title"><?= htmlspecialchars($setting['nama_app'] ?? 'KostAdmin'); ?></h4>
                            <div class="small text-white-50 fw-semibold">Faktur Penagihan Resmi</div>
                        </div>
                    </div>
                </div>

                <div class="inv-body">
                    <div class="inv-paper">
                        <div class="text-center mb-4">
                            <div class="text-muted small fw-bold mb-1">NOMOR INVOICE</div>
                            <div class="fs-4 fw-extrabold text-primary font-monospace" id="m-inv"></div>
                            <div class="mt-2 d-inline-block px-3 py-1 rounded-pill" id="m-status-badge" style="font-size: 0.75rem; font-weight: 800;"></div>
                        </div>

                        <div class="item-row">
                            <span class="item-label">Nama Penyewa</span>
                            <span class="item-value" id="m-nama"></span>
                        </div>
                        <div class="item-row">
                            <span class="item-label">Properti & Unit</span>
                            <span class="item-value" id="m-kost-kamar"></span>
                        </div>
                        <div class="item-row">
                            <span class="item-label">Tanggal Terbit</span>
                            <span class="item-value" id="m-tanggal"></span>
                        </div>
                        <div class="item-row d-none" id="m-denda-row">
                            <span class="item-label text-danger">Denda Keterlambatan</span>
                            <span class="item-value text-danger" id="m-denda"></span>
                        </div>
                        
                        <div class="total-row">
                            <span class="total-label">Total Tagihan</span>
                            <span class="total-value">Rp <span id="m-jumlah"></span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    function openInvoiceModal(data) {
        document.getElementById('m-inv').innerText = data.invoice;
        document.getElementById('m-nama').innerText = data.nama;
        document.getElementById('m-kost-kamar').innerText = (data.kost ? data.kost : '-') + " (KM. " + (data.kamar ? data.kamar : '-') + ")";
        document.getElementById('m-tanggal').innerText = data.tanggal;
        
        let statusBadge = document.getElementById('m-status-badge');
        if(data.status === 'Lunas') {
            statusBadge.innerHTML = '<i class="fa-solid fa-check"></i> TELAH DILUNASI';
            statusBadge.style.backgroundColor = '#DCFCE7';
            statusBadge.style.color = '#16A34A';
        } else {
            statusBadge.innerHTML = '<i class="fa-solid fa-hourglass-half"></i> MENUNGGU PEMBAYARAN';
            statusBadge.style.backgroundColor = '#FFF7ED';
            statusBadge.style.color = '#D97706';
        }
        
        let dendaRow = document.getElementById('m-denda-row');
        if(data.denda != 0) {
            dendaRow.classList.remove('d-none');
            document.getElementById('m-denda').innerText = "+ Rp " + data.denda;
        } else {
            dendaRow.classList.add('d-none');
        }

        document.getElementById('m-jumlah').innerText = data.jumlah;
        var invoiceModal = new bootstrap.Modal(document.getElementById('invoiceModal'));
        invoiceModal.show();
    }

    // Smart Live Countdown untuk Jatuh Tempo
    function updateTimers() {
        document.querySelectorAll('.countdown-container').forEach(container => {
            const expire = parseInt(container.dataset.expire);
            const now = Math.floor(Date.now() / 1000);
            const remaining = expire - now;
            
            if (remaining <= 0) {
                container.querySelector('.countdown-text').innerText = "00:00:00";
                const dot = container.querySelector('.pulse-dot');
                if(dot) dot.style.display = 'none';
                return;
            }

            const days = Math.floor(remaining / 86400);
            const hours = String(Math.floor((remaining % 86400) / 3600)).padStart(2, '0');
            const mins = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
            const secs = String(remaining % 60).padStart(2, '0');
            
            if (days > 0) {
                container.querySelector('.countdown-text').innerText = `${days} Hari, ${hours}:${mins}:${secs}`;
            } else {
                container.querySelector('.countdown-text').innerText = `${hours}:${mins}:${secs}`;
            }
        });
    }
    setInterval(updateTimers, 1000);
    updateTimers();

    // Next Billing Cycle Countdown Logic
    function updateNextBilling() {
        const nextBillingEl = document.getElementById('next-billing-timer');
        if (!nextBillingEl) return;

        const nextDue = parseInt(nextBillingEl.dataset.next) * 1000; 
        const now = Date.now();
        const diff = nextDue - now;

        if (diff <= 0) {
            nextBillingEl.innerText = "Tagihan Siap Rilis!";
            return;
        }

        const d = Math.floor(diff / (1000 * 60 * 60 * 24));
        const h = Math.floor((diff / (1000 * 60 * 60)) % 24);
        const m = Math.floor((diff / 1000 / 60) % 60);
        const s = Math.floor((diff / 1000) % 60);

        nextBillingEl.innerText = `${d}h ${h}j ${m}m ${s}d`;
    }
    setInterval(updateNextBilling, 1000);
    updateNextBilling();
    </script>
</body>
</html>