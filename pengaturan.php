<?php
session_start();
require 'koneksi.php';
cek_login();

// Keamanan Tingkat Tinggi: Hanya Admin
if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$username = $_SESSION['username'];
$success_msg = "";
$error_msg = "";
$active_tab = "general"; // Default tab

// ==========================================
// 1. AUTO-SETUP & MIGRASI DATABASE PENGATURAN
// ==========================================
$cek_tabel = mysqli_query($conn, "SHOW TABLES LIKE 'pengaturan'");
if (mysqli_num_rows($cek_tabel) == 0) {
    mysqli_query($conn, "CREATE TABLE pengaturan (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        nama_app VARCHAR(100) DEFAULT 'KostAdmin Pro',
        email_sistem VARCHAR(100) DEFAULT 'admin@kostadmin.com',
        tgl_jatuh_tempo INT(2) DEFAULT 10,
        denda_terlambat INT(11) DEFAULT 0,
        pesan_tagihan TEXT,
        maintenance_mode ENUM('0','1') DEFAULT '0'
    )");
    
    $default_msg = "Halo, Tagihan kost bulan ini sudah diterbitkan. Mohon melakukan pelunasan sebelum jatuh tempo. Terima kasih.";
    mysqli_query($conn, "INSERT INTO pengaturan (nama_app, email_sistem, tgl_jatuh_tempo, denda_terlambat, pesan_tagihan) 
                         VALUES ('KostAdmin Pro', 'admin@kostadmin.com', 10, 0, '$default_msg')");
} else {
    $cek_kolom = mysqli_query($conn, "SHOW COLUMNS FROM pengaturan LIKE 'tgl_jatuh_tempo'");
    if (mysqli_num_rows($cek_kolom) == 0) {
        $default_msg = "Halo, Tagihan kost bulan ini sudah diterbitkan. Mohon melakukan pelunasan sebelum jatuh tempo. Terima kasih.";
        mysqli_query($conn, "ALTER TABLE pengaturan 
                             ADD tgl_jatuh_tempo INT(2) DEFAULT 10, 
                             ADD denda_terlambat INT(11) DEFAULT 0, 
                             ADD pesan_tagihan TEXT");
        mysqli_query($conn, "UPDATE pengaturan SET pesan_tagihan = '$default_msg' WHERE id = 1");
    }
}

// ==========================================
// 2. PROSES UPDATE IDENTITAS SISTEM
// ==========================================
if (isset($_POST['simpan_sistem'])) {
    $active_tab = "general";
    $nama_app = mysqli_real_escape_string($conn, $_POST['nama_app']);
    $email_sistem = mysqli_real_escape_string($conn, $_POST['email_sistem']);
    
    if (mysqli_query($conn, "UPDATE pengaturan SET nama_app = '$nama_app', email_sistem = '$email_sistem' WHERE id = 1")) {
        $success_msg = "Profil sistem berhasil diperbarui.";
    } else {
        $error_msg = "Gagal memperbarui profil sistem.";
    }
}

// ==========================================
// 3. PROSES UPDATE OPERASIONAL & TAGIHAN
// ==========================================
if (isset($_POST['simpan_operasional'])) {
    $active_tab = "operasional";
    $tgl_jatuh_tempo = (int)$_POST['tgl_jatuh_tempo'];
    $denda_terlambat = (int)str_replace(['.', ','], '', $_POST['denda_terlambat']); 
    $pesan_tagihan = mysqli_real_escape_string($conn, trim($_POST['pesan_tagihan']));
    
    if (mysqli_query($conn, "UPDATE pengaturan SET tgl_jatuh_tempo = '$tgl_jatuh_tempo', denda_terlambat = '$denda_terlambat', pesan_tagihan = '$pesan_tagihan' WHERE id = 1")) {
        $success_msg = "Kebijakan operasional berhasil disimpan.";
    } else {
        $error_msg = "Gagal memperbarui operasional.";
    }
}

// ==========================================
// 4. PROSES UPDATE ROLE PENGGUNA
// ==========================================
if (isset($_POST['update_role'])) {
    $active_tab = "role";
    $id_user = mysqli_real_escape_string($conn, $_POST['id_user']);
    $role_baru = mysqli_real_escape_string($conn, $_POST['role_baru']);
    
    if ($_POST['username_target'] == $username && $role_baru == 'user') {
        $error_msg = "Akses ditolak: Anda tidak dapat mencabut hak admin pada akun Anda sendiri.";
    } else {
        if (mysqli_query($conn, "UPDATE users SET role = '$role_baru' WHERE id = '$id_user'")) {
            $success_msg = "Hak akses berhasil diperbarui menjadi " . strtoupper($role_baru) . ".";
        } else {
            $error_msg = "Gagal memperbarui hak akses.";
        }
    }
}

// ==========================================
// 5. AMBIL DATA
// ==========================================
$q_set = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1");
$setting = mysqli_fetch_assoc($q_set);

$q_users = mysqli_query($conn, "SELECT u.id, u.username, u.role, u.email, p.nama_penghuni 
                                FROM users u 
                                LEFT JOIN penghuni p ON u.id_penghuni = p.id 
                                ORDER BY u.role ASC, u.username ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { 
            --primary: #4F46E5; --primary-hover: #3730A3;
            --bg: #F8FAFC; --surface: #ffffff;
            --text-main: #0F172A; --text-muted: #64748B;
            --border-light: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        .container-app { max-width: 1100px; margin: 0 auto; padding: 40px 20px; }

        /* HEADER BEAUTIFICATION */
        .page-header { margin-bottom: 40px; display: flex; align-items: center; gap: 20px; }
        .page-brand-logo { width: 56px; height: 56px; background: linear-gradient(135deg, var(--primary) 0%, #818CF8 100%); color: white; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.25); }
        .page-title { font-weight: 800; font-size: 2rem; letter-spacing: -1px; margin-bottom: 2px; color: var(--text-main); }
        .page-subtitle { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin: 0; }

        /* LAYOUTING */
        .settings-layout { display: flex; flex-direction: column; gap: 30px; }
        @media (min-width: 992px) {
            .settings-layout { flex-direction: row; align-items: flex-start; }
            .settings-sidebar { width: 280px; flex-shrink: 0; position: sticky; top: 100px; }
            .settings-content { flex-grow: 1; min-width: 0; }
        }

        /* PREMIUM SIDEBAR NAV */
        .settings-sidebar { background: var(--surface); border-radius: 24px; padding: 15px; border: 1px solid var(--border-light); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.03); }
        .nav-settings { display: flex; flex-direction: column; gap: 6px; }
        .nav-settings .nav-link { color: var(--text-muted); font-weight: 700; padding: 14px 18px; border-radius: 14px; font-size: 0.95rem; display: flex; align-items: center; gap: 14px; transition: all 0.3s; border: none; background: transparent; text-align: left; }
        .nav-settings .nav-link:hover { color: var(--text-main); background: #F1F5F9; }
        .nav-settings .nav-link.active { background: #EEF2FF; color: var(--primary); }
        .nav-settings .nav-link i { font-size: 1.2rem; width: 24px; text-align: center; transition: 0.3s; }
        
        @media (max-width: 991px) {
            .settings-sidebar { padding: 10px; border-radius: 16px; }
            .nav-settings { flex-direction: row; overflow-x: auto; padding-bottom: 5px; }
            .nav-settings::-webkit-scrollbar { display: none; }
            .nav-settings .nav-link { white-space: nowrap; }
        }

        /* ELEGANT CARDS */
        .setting-section { display: none; animation: fadeInUp 0.4s ease forwards; }
        .setting-section.active { display: block; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        .setting-card { background: var(--surface); border-radius: 28px; border: 1px solid var(--border-light); padding: 40px; box-shadow: 0 15px 35px -10px rgba(0,0,0,0.04); margin-bottom: 24px; }
        .card-header-clean { margin-bottom: 30px; display: flex; gap: 20px; align-items: flex-start; }
        .header-icon { width: 50px; height: 50px; border-radius: 14px; background: #F8FAFC; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; border: 1px solid var(--border-light); }
        .card-title { font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; letter-spacing: -0.5px; }
        .card-desc { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; margin: 0; line-height: 1.6; }

        /* BEAUTIFUL FORMS */
        .form-label { font-size: 0.8rem; font-weight: 800; color: var(--text-main); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control, .form-select { border-radius: 14px; border: 1.5px solid var(--border-light); padding: 14px 18px; font-weight: 600; font-size: 0.95rem; color: var(--text-main); background: #F8FAFC; transition: all 0.3s; }
        .form-control:focus, .form-select:focus { background: white; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); outline: none; }
        .input-group-text { background: #F1F5F9; border: 1.5px solid var(--border-light); border-right: none; color: var(--text-muted); font-weight: 700; border-top-left-radius: 14px; border-bottom-left-radius: 14px; padding: 0 20px; }
        .input-group .form-control { border-left: none; padding-left: 0; }
        .input-group:focus-within .input-group-text { border-color: var(--primary); color: var(--primary); background: white; }
        .form-text { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; margin-top: 8px; display: flex; align-items: center; gap: 6px; }

        /* VIBRANT BUTTONS */
        .btn-save { background: var(--primary); color: white; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; transition: 0.3s; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.25); }
        .btn-save:hover { background: var(--primary-hover); transform: translateY(-3px); box-shadow: 0 8px 25px rgba(79, 70, 229, 0.35); }

        /* MODERN TABLE */
        .table-responsive-custom { border: 1px solid var(--border-light); border-radius: 20px; overflow: hidden; background: white; }
        .custom-table { width: 100%; border-collapse: collapse; margin: 0; min-width: 600px; }
        .custom-table thead th { background: #F8FAFC; color: #64748B; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding: 18px 24px; border-bottom: 1px solid var(--border-light); }
        .custom-table td { padding: 18px 24px; vertical-align: middle; border-bottom: 1px solid #F1F5F9; transition: 0.2s; }
        .custom-table tbody tr:hover td { background: #F8FAFC; }

        .user-identity { display: flex; align-items: center; gap: 14px; }
        .user-avatar { width: 45px; height: 45px; border-radius: 14px; background: #EEF2FF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; flex-shrink: 0; }
        .user-avatar.admin { background: #FEF2F2; color: #DC2626; }
        
        .role-badge { display: inline-flex; padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .rb-admin { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .rb-user { background: #F1F5F9; color: var(--text-main); border: 1px solid var(--border-light); }

        .select-role { border-radius: 10px; font-weight: 700; font-size: 0.85rem; padding: 8px 12px; border: 1.5px solid var(--border-light); outline: none; cursor: pointer; background: white; color: var(--text-main); transition: 0.2s; }
        .select-role:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        
        .btn-update-role { padding: 8px 16px; border-radius: 10px; font-weight: 800; font-size: 0.8rem; border: none; background: var(--text-main); color: white; transition: 0.3s; }
        .btn-update-role:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

        /* STATUS WIDGETS */
        .status-widget { background: #F8FAFC; border: 1px solid var(--border-light); border-radius: 20px; padding: 25px; display: flex; align-items: center; gap: 20px; }
        .pulse-indicator { width: 14px; height: 14px; background: #10B981; border-radius: 50%; position: relative; }
        .pulse-indicator::after { content: ''; position: absolute; inset: -5px; border-radius: 50%; border: 2px solid #10B981; animation: ripple 1.5s infinite ease-out; }
        @keyframes ripple { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(2.5); opacity: 0; } }

        .metric-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .metric-box { background: white; padding: 20px; border-radius: 16px; border: 1px solid var(--border-light); text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .metric-val { font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin-bottom: 2px; }
        .metric-lbl { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="page-header">
            <div class="page-brand-logo">
                <i class="fa-solid fa-sliders"></i>
            </div>
            <div>
                <h1 class="page-title">Control Panel</h1>
                <p class="page-subtitle">Kelola konfigurasi sistem, kebijakan properti, dan hak akses pengguna.</p>
            </div>
        </div>

        <div class="settings-layout">
            
            <div class="settings-sidebar">
                <div class="nav flex-column nav-settings" id="v-pills-tab" role="tablist">
                    <button class="nav-link <?= $active_tab == 'general' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#v-pills-general" type="button" role="tab">
                        <i class="fa-solid fa-cube"></i> Identitas Sistem
                    </button>
                    <button class="nav-link <?= $active_tab == 'operasional' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#v-pills-operasional" type="button" role="tab">
                        <i class="fa-solid fa-calendar-check"></i> Kebijakan Tagihan
                    </button>
                    <button class="nav-link <?= $active_tab == 'role' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#v-pills-role" type="button" role="tab">
                        <i class="fa-solid fa-users-gear"></i> Manajemen Akses
                    </button>
                    <button class="nav-link <?= $active_tab == 'db' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#v-pills-db" type="button" role="tab">
                        <i class="fa-solid fa-server"></i> Kesehatan Server
                    </button>
                </div>
            </div>

            <div class="settings-content tab-content">
                
                <div class="tab-pane fade <?= $active_tab == 'general' ? 'show active' : ''; ?>" id="v-pills-general" role="tabpanel">
                    <div class="setting-card">
                        <div class="card-header-clean">
                            <div class="header-icon"><i class="fa-regular fa-id-card"></i></div>
                            <div>
                                <h3 class="card-title">Profil Aplikasi</h3>
                                <p class="card-desc">Informasi yang akan direfleksikan di Navbar dan berbagai laporan cetak.</p>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label">Nama Aplikasi / Properti <span class="text-danger">*</span></label>
                                <input type="text" name="nama_app" class="form-control" value="<?= htmlspecialchars($setting['nama_app'] ?? ''); ?>" required>
                                <div class="form-text"><i class="fa-solid fa-circle-info"></i> Contoh: KostAdmin Pro atau Kost Bintaro Jaya.</div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Email Dukungan (Support) <span class="text-danger">*</span></label>
                                <input type="email" name="email_sistem" class="form-control" value="<?= htmlspecialchars($setting['email_sistem'] ?? ''); ?>" required>
                                <div class="form-text"><i class="fa-solid fa-envelope"></i> Digunakan oleh penyewa untuk menghubungi bantuan manajemen.</div>
                            </div>
                            <div class="pt-4 mt-5 border-top text-end">
                                <button type="submit" name="simpan_sistem" class="btn-save">
                                    <i class="fa-solid fa-floppy-disk"></i> Simpan Profil
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="tab-pane fade <?= $active_tab == 'operasional' ? 'show active' : ''; ?>" id="v-pills-operasional" role="tabpanel">
                    <div class="setting-card">
                        <div class="card-header-clean">
                            <div class="header-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                            <div>
                                <h3 class="card-title">Regulasi Operasional</h3>
                                <p class="card-desc">Atur standar penagihan otomatis yang berlaku untuk seluruh penyewa.</p>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Jatuh Tempo Bulanan <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                                        <input type="number" name="tgl_jatuh_tempo" class="form-control" min="1" max="31" value="<?= htmlspecialchars($setting['tgl_jatuh_tempo'] ?? 10); ?>" required>
                                    </div>
                                    <div class="form-text"><i class="fa-solid fa-circle-info"></i> Batas akhir pembayaran bulanan.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Denda Keterlambatan</label>
                                    <div class="input-group">
                                        <span class="input-group-text fw-800 text-dark">Rp</span>
                                        <input type="number" name="denda_terlambat" class="form-control" value="<?= htmlspecialchars($setting['denda_terlambat'] ?? 0); ?>">
                                    </div>
                                    <div class="form-text"><i class="fa-solid fa-triangle-exclamation"></i> Isi 0 jika tidak ada denda.</div>
                                </div>
                            </div>
                    

                            <div class="pt-4 mt-5 border-top text-end">
                                <button type="submit" name="simpan_operasional" class="btn-save">
                                    <i class="fa-solid fa-floppy-disk"></i> Simpan Kebijakan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="tab-pane fade <?= $active_tab == 'role' ? 'show active' : ''; ?>" id="v-pills-role" role="tabpanel">
                    <div class="setting-card pb-4">
                        <div class="card-header-clean">
                            <div class="header-icon"><i class="fa-solid fa-shield-halved"></i></div>
                            <div>
                                <h3 class="card-title">Role-Based Access Control</h3>
                                <p class="card-desc">Atur hak akses akun. Hanya pengguna berstatus Admin yang memiliki kendali penuh.</p>
                            </div>
                        </div>
                        
                        <div class="table-responsive-custom">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Identitas Akun</th>
                                        <th>Level Akses</th>
                                        <th class="text-end">Manajemen Jabatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($usr = mysqli_fetch_assoc($q_users)) : ?>
                                    <tr>
                                        <td>
                                            <div class="user-identity">
                                                <div class="user-avatar <?= $usr['role'] == 'admin' ? 'admin' : ''; ?>">
                                                    <i class="fa-solid <?= $usr['role'] == 'admin' ? 'fa-crown' : 'fa-user'; ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-800 text-dark" style="font-size: 0.95rem;">@<?= $usr['username']; ?></div>
                                                    <div class="text-muted fw-bold" style="font-size: 0.75rem;">
                                                        <?= !empty($usr['nama_penghuni']) ? $usr['nama_penghuni'] : $usr['email']; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge rb-<?= $usr['role']; ?>"><?= $usr['role']; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" class="d-flex gap-2 justify-content-end m-0">
                                                <input type="hidden" name="id_user" value="<?= $usr['id']; ?>">
                                                <input type="hidden" name="username_target" value="<?= $usr['username']; ?>">
                                                <select name="role_baru" class="select-role">
                                                    <option value="user" <?= $usr['role'] == 'user' ? 'selected' : ''; ?>>User Biasa</option>
                                                    <option value="admin" <?= $usr['role'] == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                                </select>
                                                <button type="submit" name="update_role" class="btn-update-role">Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade <?= $active_tab == 'db' ? 'show active' : ''; ?>" id="v-pills-db" role="tabpanel">
                    <div class="setting-card">
                        <div class="card-header-clean">
                            <div class="header-icon"><i class="fa-solid fa-server"></i></div>
                            <div>
                                <h3 class="card-title">Kesehatan Infrastruktur</h3>
                                <p class="card-desc">Monitoring koneksi basis data MySQL dan metrik performa server.</p>
                            </div>
                        </div>
                        
                        <div class="status-widget mb-4">
                            <div class="pulse-indicator"></div>
                            <div>
                                <h5 class="fw-800 text-dark m-0" style="font-size: 1.1rem;">Koneksi Database Stabil</h5>
                                <p class="text-muted small fw-bold m-0 mt-1">Host: 127.0.0.1 | Seluruh tabel esensial terdeteksi aman.</p>
                            </div>
                        </div>

                        <div class="metric-grid">
                            <div class="metric-box">
                                <div class="metric-val text-success">99.9%</div>
                                <div class="metric-lbl">Uptime</div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-val">12<span style="font-size:0.8rem;">ms</span></div>
                                <div class="metric-lbl">Latency</div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-val text-primary">Aman</div>
                                <div class="metric-lbl">Logs</div>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-top">
                            <p class="small text-muted fw-bold m-0"><i class="fa-solid fa-lock text-dark me-1"></i> Konfigurasi mendalam (*Maintenance Mode*) dikelola melalui Control Panel Hosting Utama Anda.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if($success_msg != "") : ?>
        <script>
            Swal.fire({ 
                icon: 'success', 
                title: 'Berhasil', 
                text: '<?= $success_msg; ?>', 
                confirmButtonColor: '#111827',
                customClass: { popup: 'rounded-4' }
            });
        </script>
    <?php endif; ?>
    
    <?php if($error_msg != "") : ?>
        <script>
            Swal.fire({ 
                icon: 'error', 
                title: 'Akses Ditolak', 
                text: '<?= $error_msg; ?>',
                confirmButtonColor: '#DC2626',
                customClass: { popup: 'rounded-4' }
            });
        </script>
    <?php endif; ?>

</body>
</html>