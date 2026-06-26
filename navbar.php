<?php
// Proteksi: Pastikan koneksi database tersedia sebelum Navbar memanggil query
if (!isset($conn)) {
    require_once 'koneksi.php';
}

// ==========================================
// 1. AUTO-SETUP TABEL NOTIFIKASI
// ==========================================
$cek_tabel_notif = mysqli_query($conn, "SHOW TABLES LIKE 'notifikasi'");
if (mysqli_num_rows($cek_tabel_notif) == 0) {
    mysqli_query($conn, "CREATE TABLE notifikasi (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        id_penghuni INT(11) NULL,
        judul VARCHAR(150) NOT NULL,
        pesan TEXT NOT NULL,
        tipe ENUM('Tagihan', 'Pengumuman', 'Sistem') DEFAULT 'Pengumuman',
        is_read ENUM('0', '1') DEFAULT '0',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

// ==========================================
// 2. DATA PROFIL & NAVBAR APLIKASI
// ==========================================
$nav_username = $_SESSION['username'] ?? 'User';
$nav_role = $_SESSION['role'] ?? 'user';

// Ambil Foto Profil
$nav_query = mysqli_query($conn, "SELECT foto_profil FROM users WHERE username = '$nav_username'");
$nav_data = mysqli_fetch_assoc($nav_query);
$nav_foto = $nav_data['foto_profil'] ?? '';

$default_avatar = "https://ui-avatars.com/api/?name=".urlencode($nav_username)."&background=4F46E5&color=ffffff&size=150&bold=true";
$display_foto = (!empty($nav_foto) && file_exists('uploads/' . $nav_foto)) ? 'uploads/' . $nav_foto : $default_avatar;

// Ambil Nama Aplikasi
$app_name_navbar = "KostAdmin Pro"; 
$cek_tabel_setting = mysqli_query($conn, "SHOW TABLES LIKE 'pengaturan'");
if (mysqli_num_rows($cek_tabel_setting) > 0) {
    $q_setting = mysqli_query($conn, "SELECT nama_app FROM pengaturan WHERE id = 1");
    if ($q_setting && mysqli_num_rows($q_setting) > 0) {
        $set_data = mysqli_fetch_assoc($q_setting);
        if (!empty($set_data['nama_app'])) $app_name_navbar = $set_data['nama_app'];
    }
}

// ==========================================
// 3. LOGIKA NOTIFIKASI USER
// ==========================================
$unread_count = 0;
if ($nav_role == 'user') {
    $q_usr_id = mysqli_query($conn, "SELECT id_penghuni FROM users WHERE username = '$nav_username'");
    if($q_usr_id && mysqli_num_rows($q_usr_id) > 0) {
        $usr_res = mysqli_fetch_assoc($q_usr_id);
        $id_penghuni_nav = $usr_res['id_penghuni'];
        
        if ($id_penghuni_nav) {
            $q_notif = mysqli_query($conn, "SELECT COUNT(id) as unread FROM notifikasi WHERE id_penghuni = '$id_penghuni_nav' AND is_read = '0'");
            if($q_notif) {
                $notif_res = mysqli_fetch_assoc($q_notif);
                $unread_count = $notif_res['unread'];
            }
        }
    }
}
?>

<style>
    /* -----------------------------------------------------------
       ANTI SCROLLBAR JUMP (Mencegah Halaman Goyang)
       ----------------------------------------------------------- */
    html {
        overflow-y: scroll; /* Memaksa scrollbar selalu ada walau layar kosong */
    }

    /* Styling Dropdown Profile Premium */
    .nav-profile-toggle { cursor: pointer; transition: 0.2s; padding: 4px; border-radius: 50px; border: 1px solid transparent; }
    .nav-profile-toggle:hover { background: #F8FAFC; border-color: #E2E8F0; }
    .nav-profile-toggle::after { display: none; }
    
    .profile-dropdown-card {
        min-width: 260px; border: 1px solid #E2E8F0 !important; border-radius: 20px !important;
        box-shadow: 0 10px 40px -10px rgba(15, 23, 42, 0.1) !important;
        padding: 20px 15px 15px 15px !important; margin-top: 15px !important;
    }
    
    .profile-dropdown-card .avatar-large {
        width: 75px; height: 75px; border-radius: 50%; object-fit: cover;
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.15); margin-bottom: 12px;
    }
    
    .btn-profil-menu {
        border-radius: 10px; background-color: transparent; color: #0F172A;
        font-weight: 600; padding: 10px 14px; transition: 0.2s; text-align: left; 
        display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 0.9rem;
    }
    .btn-profil-menu:hover { background-color: #F8FAFC; color: #4F46E5; }
    
    .btn-keluar {
        border-radius: 10px; color: #EF4444; background: transparent;
        font-weight: 600; font-size: 0.9rem; transition: 0.2s; padding: 10px 14px; 
        text-align: left; display: flex; align-items: center; gap: 12px; text-decoration: none;
    }
    .btn-keluar:hover { background-color: #FEF2F2; color: #DC2626; }

    /* Styling Notification Bell & Megaphone */
    .nav-icon-btn {
        position: relative; display: flex; align-items: center; justify-content: center;
        width: 40px; height: 40px; border-radius: 50%; background: white; border: 1px solid #E2E8F0;
        color: #64748B; transition: 0.2s; text-decoration: none; font-size: 1.1rem;
    }
    .nav-icon-btn:hover { background: #F8FAFC; color: #4F46E5; border-color: #CBD5E1; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
    
    .notif-badge {
        position: absolute; top: -3px; right: -3px; background: #EF4444; color: white;
        font-size: 0.65rem; font-weight: 800; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 50px;
        display: flex; align-items: center; justify-content: center; border: 2px solid white;
    }
    
    .navbar-custom { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-bottom: 1px solid #E2E8F0; }
    .nav-link-custom { color: #64748B; font-weight: 600; font-size: 0.95rem; padding: 8px 16px !important; border-radius: 8px; transition: 0.2s; }
    .nav-link-custom:hover { color: #0F172A; background: #F8FAFC; }
</style>

<nav class="navbar navbar-expand-lg navbar-custom sticky-top py-2 mb-4">
    <div class="container">
        <a class="navbar-brand fw-extrabold text-dark d-flex align-items-center gap-2" href="dashboard.php" style="letter-spacing: -0.5px;">
            <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #4F46E5 0%, #818CF8 100%); color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);">
                <i class="fa-solid fa-house-chimney-window"></i>
            </div>
            <?= htmlspecialchars($app_name_navbar); ?>
        </a>
        
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto ms-lg-4 mt-3 mt-lg-0 gap-1">
                <li class="nav-item"><a class="nav-link nav-link-custom" href="dashboard.php">Dashboard</a></li>
                
                <?php if ($nav_role == 'admin') : ?>
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="kost.php">Properti</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="kamar.php">Kamar</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="penghuni.php">Penghuni</a></li>
                <?php else : ?>
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="pilih_kamar.php">Pilih Kamar</a></li>
                <?php endif; ?>
                
                <li class="nav-item"><a class="nav-link nav-link-custom" href="tagihan.php">Tagihan</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom" href="riwayat_transaksi.php">Riwayat</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom text-danger" href="komplain.php"><i class="fa-solid fa-circle-exclamation me-1"></i> Komplain</a></li>
            </ul>
            
            <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0 pt-3 pt-lg-0 border-top border-lg-0 border-start-lg ps-lg-3">
                
                <?php if ($nav_role == 'admin') : ?>
                    <a href="broadcast.php" class="nav-icon-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Broadcast Pesan">
                        <i class="fa-solid fa-bullhorn"></i>
                    </a>
                <?php else : ?>
                    <a href="notifikasi.php" class="nav-icon-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Inbox Notifikasi">
                        <i class="fa-regular fa-bell"></i>
                        <?php if($unread_count > 0) : ?>
                            <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle nav-profile-toggle d-flex align-items-center gap-2" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= $display_foto; ?>" alt="Profile" class="rounded-circle" style="width: 36px; height: 36px; object-fit: cover;">
                        <div class="text-start d-none d-sm-block pe-1">
                            <div class="small fw-bold lh-1 text-dark" style="font-size: 0.85rem;"><?= ucfirst($nav_username); ?></div>
                            <div class="text-muted" style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; margin-top: 2px;"><?= $nav_role; ?></div>
                        </div>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown-card text-center" aria-labelledby="profileDropdown">
                        <li>
                            <img src="<?= $display_foto; ?>" alt="Profile Besar" class="avatar-large">
                        </li>
                        <li>
                            <h6 class="fw-extrabold mb-0 text-dark"><?= ucfirst($nav_username); ?></h6>
                        </li>
                        <li>
                            <p class="text-muted mb-3" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase;"><?= $nav_role; ?></p>
                        </li>
                        
                        <li class="px-1">
                            <a class="btn-profil-menu w-100" href="profile.php">
                                <i class="fa-regular fa-user text-muted" style="width: 16px;"></i> Profil Saya
                            </a>
                        </li>
                        
                        <?php if ($nav_role == 'admin') : ?>
                        <li class="px-1 mt-1">
                            <a class="btn-profil-menu w-100" href="pengaturan.php">
                                <i class="fa-solid fa-sliders text-muted" style="width: 16px;"></i> Pengaturan
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li><hr class="dropdown-divider my-2 mx-2 opacity-25"></li>
                        <li class="px-1">
                            <a class="btn-keluar w-100" href="logout.php">
                                <i class="fa-solid fa-arrow-right-from-bracket" style="width: 16px;"></i> Keluar Akun
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</nav>

<script>
    document.addEventListener("DOMContentLoaded", function(){
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>