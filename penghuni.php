<?php
session_start();
require 'koneksi.php';
cek_login();

$success_msg = "";
$error_msg = "";

// ========================================================
// 1. PROSES LOGIKA MUTASI (PINDAH / KELUAR)
// ========================================================
if (isset($_POST['proses_mutasi']) && $_SESSION['role'] == 'admin') {
    $id_penghuni = mysqli_real_escape_string($conn, $_POST['id_penghuni']);
    $id_kamar_lama = mysqli_real_escape_string($conn, $_POST['id_kamar_lama']);
    $jenis_mutasi = $_POST['jenis_mutasi']; // 'pindah' atau 'keluar'

    // Mulai Transaksi Database agar aman
    mysqli_begin_transaction($conn);

    try {
        if ($jenis_mutasi == 'keluar') {
            
            // 1. Kamar lama menjadi tersedia
            if(!empty($id_kamar_lama)) {
                $q1 = mysqli_query($conn, "UPDATE kamar SET status = 'Tersedia' WHERE id = '$id_kamar_lama'");
                if (!$q1) throw new Exception("Gagal update kamar: " . mysqli_error($conn));
            }
            
            // 2. Penghuni dinonaktifkan (id_kamar tidak di-NULL-kan untuk menjaga histori)
            $q2 = mysqli_query($conn, "UPDATE penghuni SET status_penghuni = 'Keluar' WHERE id = '$id_penghuni'");
            if (!$q2) throw new Exception("Gagal update status penghuni: " . mysqli_error($conn));
            
            $success_msg = "Penghuni berhasil di-Checkout. Kamar kini tersedia kembali.";
            
        } elseif ($jenis_mutasi == 'pindah') {
            
            $id_kamar_baru = mysqli_real_escape_string($conn, $_POST['id_kamar_baru']);
            
            if (!empty($id_kamar_baru)) {
                // 1. Kamar lama menjadi tersedia
                if(!empty($id_kamar_lama)) {
                    $q1 = mysqli_query($conn, "UPDATE kamar SET status = 'Tersedia' WHERE id = '$id_kamar_lama'");
                    if (!$q1) throw new Exception("Gagal update kamar lama: " . mysqli_error($conn));
                }
                
                // 2. Kamar baru menjadi terisi
                $q2 = mysqli_query($conn, "UPDATE kamar SET status = 'Terisi' WHERE id = '$id_kamar_baru'");
                if (!$q2) throw new Exception("Gagal update kamar baru: " . mysqli_error($conn));
                
                // 3. Update lokasi kamar si penghuni
                $q3 = mysqli_query($conn, "UPDATE penghuni SET id_kamar = '$id_kamar_baru', status_penghuni = 'Aktif' WHERE id = '$id_penghuni'");
                if (!$q3) throw new Exception("Gagal memindahkan penghuni: " . mysqli_error($conn));
                
                $success_msg = "Penghuni berhasil dipindahkan ke unit baru.";
            } else {
                throw new Exception("Kamar tujuan belum dipilih! Silakan pilih kamar kosong.");
            }
        }
        
        mysqli_commit($conn);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Sistem Error: " . $e->getMessage();
    }
}

// ========================================================
// 2. AMBIL STATISTIK HEADER
// ========================================================
try {
    $total_p = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM penghuni"));
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM penghuni LIKE 'status_penghuni'");
    if (mysqli_num_rows($check_col) > 0) {
        $aktif_p = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM penghuni WHERE status_penghuni = 'Aktif'"));
    } else {
        $aktif_p = $total_p; 
    }
    
    $total_kamar = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kamar"));
    $kamar_kosong = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kamar WHERE status = 'Tersedia'"));
} catch(Exception $e) {
    $total_p = 0; $aktif_p = 0; $total_kamar = 0; $kamar_kosong = 0;
}

// ========================================================
// 3. AMBIL DATA KAMAR KOSONG (UNTUK DROPDOWN PINDAH)
// ========================================================
$q_kamar_kosong = mysqli_query($conn, "SELECT k.id, k.no_kamar, ks.nama_kost, k.harga_perbulan 
                                       FROM kamar k 
                                       LEFT JOIN kost ks ON k.id_kost = ks.id 
                                       WHERE k.status = 'Tersedia' 
                                       ORDER BY ks.nama_kost ASC, k.no_kamar ASC");

// ========================================================
// 4. QUERY DATA UTAMA TABEL
// ========================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$query = "SELECT p.*, k.id as id_kamar, k.no_kamar, ks.nama_kost, u.email, u.username
          FROM penghuni p
          LEFT JOIN kamar k ON p.id_kamar = k.id
          LEFT JOIN kost ks ON k.id_kost = ks.id
          LEFT JOIN users u ON u.id_penghuni = p.id
          WHERE p.nama_penghuni LIKE '%$search%' OR u.email LIKE '%$search%'";

if (mysqli_num_rows($check_col) > 0) {
    $query .= " ORDER BY p.status_penghuni ASC, p.id DESC";
} else {
    $query .= " ORDER BY p.id DESC";
}
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Penghuni | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --primary: #4F46E5; --primary-hover: #4338CA;
            --success: #10B981; --warning: #F59E0B; --danger: #EF4444;
            --bg: #F8FAFC; --surface: #ffffff;
            --text-main: #0F172A; --text-muted: #64748B;
            --border-light: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        .container-app { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }

        /* --- HEADER & TITLES --- */
        .page-brand-logo { width: 45px; height: 45px; background: var(--primary); color: white; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 12px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        .page-title { font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; margin-bottom: 0; }
        .page-subtitle { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin-left: 57px; }
        .gap-section { margin-bottom: 32px; }

        /* --- STATISTIC CARDS --- */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; }
        .stat-card-info { background: var(--surface); border-radius: 24px; border: 1px solid var(--border-light); padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; align-items: flex-start; gap: 16px; transition: 0.3s; }
        .stat-card-info:hover { transform: translateY(-4px); box-shadow: 0 12px 25px -5px rgba(0,0,0,0.05); border-color: #cbd5e1; }
        
        .ic-box { width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .ic-primary { background: #EEF2FF; color: var(--primary); }
        .ic-success { background: #ECFDF5; color: var(--success); }
        .ic-warning { background: #FFFBEB; color: var(--warning); }
        
        .stat-text p { margin: 0 0 6px 0; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-text h3 { margin: 0; font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; color: var(--text-main); line-height: 1; font-variant-numeric: tabular-nums; }
        .stat-desc { font-size: 0.8rem; font-weight: 600; margin-top: 10px; display: flex; align-items: center; gap: 6px; }

        /* --- UNIFIED FILTER --- */
        .unified-filter { background: var(--surface); border: 1px solid var(--border-light); border-radius: 100px; padding: 8px; display: flex; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); width: fit-content; transition: 0.3s; }
        .unified-filter:focus-within { border-color: var(--primary); box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.1); }
        .uf-search { display: flex; align-items: center; padding: 0 20px; position: relative; min-width: 320px; }
        .uf-search i { color: #94A3B8; font-size: 1rem; }
        .uf-search input { border: none; background: transparent; padding: 10px 15px; font-size: 0.95rem; font-weight: 500; outline: none; width: 100%; color: var(--text-main); }
        .uf-btn { background: var(--text-main); color: white; border: none; border-radius: 50px; padding: 12px 28px; font-weight: 700; font-size: 0.9rem; transition: 0.2s; margin-left: 8px; }
        .uf-btn:hover { background: var(--primary); transform: translateY(-1px); }
        .uf-reset { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 50%; background: #FEF2F2; color: #E11D48; text-decoration: none; margin-left: 12px; transition: 0.2s; }
        .uf-reset:hover { background: #FECACA; color: #BE123C; }

        .btn-add-tenant { background: var(--primary); color: white; padding: 12px 24px; border-radius: 50px; font-weight: 700; text-decoration: none; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.2); }
        .btn-add-tenant:hover { background: var(--primary-hover); color: white; transform: translateY(-2px); }

        /* --- TABLE STYLING --- */
        .table-card { background: var(--surface); border-radius: 24px; border: 1px solid var(--border-light); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); overflow: hidden; }
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table thead th { background: #F8FAFC; color: #64748B; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 20px 24px; border-bottom: 1px solid var(--border-light); white-space: nowrap; }
        .custom-table tbody tr { transition: 0.2s ease; border-bottom: 1px solid #F1F5F9; }
        .custom-table tbody tr:hover { background: #F8FAFC; }
        .custom-table tbody tr.row-inactive { background: #F8FAFC; opacity: 0.7; }
        .custom-table td { padding: 20px 24px; vertical-align: middle; }

        /* AVATAR & TYPOGRAPHY */
        .tenant-box { display: flex; align-items: center; gap: 16px; }
        .tenant-avatar { width: 45px; height: 45px; border-radius: 14px; background: linear-gradient(135deg, var(--primary) 0%, #818CF8 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2); flex-shrink: 0; }
        .tenant-name { font-weight: 800; color: var(--text-main); font-size: 1rem; margin-bottom: 2px; }
        .tenant-email { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
        
        .unit-badge { display: inline-flex; align-items: center; background: #F1F5F9; color: var(--text-main); padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 0.75rem; border: 1px solid var(--border-light); margin-bottom: 4px; }
        .prop-name { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; display: block; }

        .status-pill { padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .pill-active { background: #ECFDF5; color: var(--success); border: 1px solid #A7F3D0; }
        .pill-inactive { background: #F1F5F9; color: var(--text-muted); border: 1px solid var(--border-light); }

        /* ACTION BUTTONS */
        .action-group { display: flex; gap: 6px; justify-content: center; }
        .btn-icon { width: 36px; height: 36px; border-radius: 10px; border: none; background: #F8FAFC; border: 1px solid var(--border-light); color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: 0.2s; cursor: pointer; text-decoration: none; font-size: 0.9rem; }
        .btn-icon:hover { background: white; box-shadow: 0 2px 6px rgba(0,0,0,0.06); transform: translateY(-2px); }
        .btn-icon.wa { color: #16A34A; }
        .btn-icon.wa:hover { border-color: #16A34A; color: #16A34A; }
        .btn-icon.email { color: #2563EB; }
        .btn-icon.email:hover { border-color: #2563EB; color: #2563EB; }
        .btn-icon.mutasi { color: #8B5CF6; }
        .btn-icon.mutasi:hover { border-color: #8B5CF6; color: #8B5CF6; }
        .btn-icon.edit { color: #D97706; }
        .btn-icon.edit:hover { border-color: #D97706; color: #D97706; }
        .btn-icon.delete { color: #DC2626; }
        .btn-icon.delete:hover { border-color: #DC2626; color: #DC2626; }
        .btn-icon.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        
        .empty-state { text-align: center; padding: 60px 20px; }

        /* MODAL MUTASI STYLING */
        .modal-content { border: none; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; }
        .modal-header-custom { background: #1E293B; color: white; padding: 25px; border-bottom: none; }
        .radio-box-container { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .radio-box { position: relative; }
        .radio-box input { position: absolute; opacity: 0; cursor: pointer; }
        .radio-box .rb-label { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 10px; background: #F8FAFC; border: 2px solid var(--border-light); border-radius: 16px; cursor: pointer; transition: 0.2s; font-weight: 800; color: var(--text-muted); gap: 8px; }
        .radio-box .rb-label i { font-size: 1.8rem; }
        .radio-box input:checked + .rb-label.keluar { background: #FEF2F2; border-color: #EF4444; color: #EF4444; }
        .radio-box input:checked + .rb-label.pindah { background: #EEF2FF; border-color: var(--primary); color: var(--primary); }
        .form-select-custom { border-radius: 14px; border: 1.5px solid var(--border-light); padding: 14px; font-weight: 600; background: white; }
        .form-select-custom:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-section">
            <div class="d-flex align-items-center">
                <div class="page-brand-logo"><i class="fa-solid fa-users"></i></div>
                <div>
                    <h1 class="page-title">Database Penghuni</h1>
                    <p class="page-subtitle m-0">Kelola dan pantau seluruh penyewa aset properti Anda.</p>
                </div>
            </div>
            
            <?php if($_SESSION['role'] == 'admin') : ?>
            <div class="mt-3 mt-md-0">
                <a href="tambah_penghuni.php" class="btn-add-tenant">
                    <i class="fa-solid fa-user-plus"></i> Registrasi Penyewa
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="stat-grid gap-section">
            <div class="stat-card-info">
                <div class="ic-box ic-primary"><i class="fa-solid fa-address-book"></i></div>
                <div class="stat-text w-100">
                    <p>Total Database</p>
                    <h3><?= $total_p; ?> <span style="font-size: 1rem; color: #94A3B8; font-weight:600;">Orang</span></h3>
                    <div class="stat-desc text-primary"><i class="fa-solid fa-server"></i> Tersimpan di sistem</div>
                </div>
            </div>

            <div class="stat-card-info">
                <div class="ic-box ic-success"><i class="fa-solid fa-user-check"></i></div>
                <div class="stat-text w-100">
                    <p>Penyewa Aktif</p>
                    <h3><?= $aktif_p; ?> <span style="font-size: 1rem; color: #94A3B8; font-weight:600;">Unit</span></h3>
                    <div class="stat-desc text-success"><i class="fa-solid fa-chart-line"></i> Sedang menyewa properti</div>
                </div>
            </div>

            <div class="stat-card-info">
                <div class="ic-box ic-warning"><i class="fa-solid fa-door-open"></i></div>
                <div class="stat-text w-100">
                    <p>Kamar Tersedia</p>
                    <h3><?= $kamar_kosong; ?> <span style="font-size: 1rem; color: #94A3B8; font-weight:600;">Unit</span></h3>
                    <div class="stat-desc text-warning"><i class="fa-solid fa-bullhorn"></i> Siap untuk dipasarkan</div>
                </div>
            </div>
        </div>

        <div class="gap-section">
            <form method="GET" action="" class="d-flex flex-wrap align-items-center">
                <div class="unified-filter mb-0">
                    <div class="uf-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Cari nama atau email penghuni..." value="<?= htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="uf-btn">Cari Data</button>
                </div>
                <?php if(!empty($search)) : ?>
                    <a href="penghuni.php" class="uf-reset" title="Reset Pencarian"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th class="ps-4">Informasi Identitas</th>
                            <th>Penempatan Unit</th>
                            <th>Jalur Komunikasi</th>
                            <th>Tgl. Registrasi</th>
                            <th>Status Akses</th>
                            <?php if($_SESSION['role'] == 'admin') : ?>
                            <th class="text-center pe-4">Manajemen</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result && mysqli_num_rows($result) > 0) : ?>
                            <?php while($row = mysqli_fetch_assoc($result)) : 
                                // Cek status jika kolom ada, defaultnya Aktif
                                $status = isset($row['status_penghuni']) ? $row['status_penghuni'] : 'Aktif';
                                $is_inactive = ($status == 'Keluar' || $status == 'Pindah');
                                $email = $row['email'] ?? 'Belum punya akun';
                                $no_hp = $row['no_hp'] ?? '';
                            ?>
                            <tr class="<?= $is_inactive ? 'row-inactive' : ''; ?>">
                                <td class="ps-4">
                                    <div class="tenant-box">
                                        <div class="tenant-avatar">
                                            <?= strtoupper(substr($row['nama_penghuni'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="tenant-name"><?= ucwords(strtolower($row['nama_penghuni'])); ?></div>
                                            <div class="tenant-email"><i class="fa-solid fa-envelope me-1 opacity-50"></i> <?= $email; ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <?php if(!$is_inactive && $row['no_kamar']) : ?>
                                        <span class="unit-badge"><i class="fa-solid fa-key me-2 text-primary opacity-75"></i> KM. <?= $row['no_kamar']; ?></span>
                                        <span class="prop-name"><?= $row['nama_kost']; ?></span>
                                    <?php else : ?>
                                        <span class="text-danger small fw-800"><i class="fa-solid fa-circle-xmark me-1"></i> KOSONG/KELUAR</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="action-group" style="justify-content: flex-start;">
                                        <a href="<?= !empty($no_hp) ? 'https://wa.me/'.preg_replace('/^0/', '62', $no_hp) : '#'; ?>" target="_blank" class="btn-icon wa <?= empty($no_hp) ? 'disabled' : ''; ?>" data-bs-toggle="tooltip" title="Chat WhatsApp">
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </a>
                                        <a href="<?= ($email != 'Belum punya akun') ? 'mailto:'.$email : '#'; ?>" class="btn-icon email <?= ($email == 'Belum punya akun') ? 'disabled' : ''; ?>" data-bs-toggle="tooltip" title="Kirim Email">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </a>
                                    </div>
                                </td>

                                <td>
                                    <?php if(isset($row['tanggal_masuk'])) : ?>
                                        <div class="fw-800 small text-dark"><?= date('d M Y', strtotime($row['tanggal_masuk'])); ?></div>
                                    <?php else : ?>
                                        <div class="fw-800 small text-muted">-</div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if(!$is_inactive) : ?>
                                        <span class="status-pill pill-active"><i class="fa-solid fa-shield-check"></i> AKTIF</span>
                                    <?php else : ?>
                                        <span class="status-pill pill-inactive"><i class="fa-solid fa-clock-rotate-left"></i> <?= strtoupper($status); ?></span>
                                    <?php endif; ?>
                                </td>

                                <?php if($_SESSION['role'] == 'admin') : ?>
                                <td class="text-center pe-4">
                                    <div class="action-group">
                                        
                                        <button type="button" class="btn-icon mutasi <?= $is_inactive ? 'disabled' : ''; ?>" data-bs-toggle="tooltip" title="Pindah Kamar / Keluar" onclick="openMutasiModal('<?= $row['id']; ?>', '<?= addslashes($row['nama_penghuni']); ?>', '<?= $row['id_kamar']; ?>', '<?= $row['no_kamar']; ?>')">
                                            <i class="fa-solid fa-right-left"></i>
                                        </button>
                                        <a href="hapus_penghuni.php?id=<?= $row['id']; ?>" class="btn-icon delete <?= !$is_inactive ? 'disabled' : ''; ?>" data-bs-toggle="tooltip" title="Hapus Permanen" onclick="return confirm('Hapus data riwayat ini selamanya?')">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fa-solid fa-users-slash mb-3" style="font-size: 3.5rem; color: #CBD5E1;"></i>
                                    <h5 class="fw-bold text-dark mb-1">Data Tidak Ditemukan</h5>
                                    <p class="text-muted small mb-0">Belum ada penghuni terdaftar atau kata kunci pencarian salah.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalMutasi" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header-custom">
                    <h5 class="fw-800 m-0"><i class="fa-solid fa-people-arrows me-2"></i> Mutasi Penghuni</h5>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <h5 class="fw-extrabold text-dark" id="m_nama_penghuni">Nama Penghuni</h5>
                        <span class="badge bg-light text-dark border px-3 py-2 mt-1" id="m_kamar_lama">Kamar: -</span>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="id_penghuni" id="m_id_penghuni">
                        <input type="hidden" name="id_kamar_lama" id="m_id_kamar_lama">
                        
                        <label class="form-label small fw-800 text-muted uppercase mb-2">Pilih Jenis Mutasi</label>
                        <div class="radio-box-container">
                            <label class="radio-box">
                                <input type="radio" name="jenis_mutasi" value="pindah" checked>
                                <div class="rb-label pindah">
                                    <i class="fa-solid fa-person-walking-luggage"></i>
                                    Pindah Kamar
                                </div>
                            </label>
                            <label class="radio-box">
                                <input type="radio" name="jenis_mutasi" value="keluar">
                                <div class="rb-label keluar">
                                    <i class="fa-solid fa-person-walking-arrow-right"></i>
                                    Keluar Kost
                                </div>
                            </label>
                        </div>

                        <div id="kamar_baru_container" class="mb-4">
                            <label class="form-label small fw-800 text-muted uppercase mb-2">Pilih Kamar Tujuan</label>
                            <select name="id_kamar_baru" id="kamar_baru_select" class="form-select form-select-custom">
                                <option value="">-- Pilih Kamar Tersedia --</option>
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

                        <div id="warning_keluar" class="alert alert-danger border-0 rounded-4 fw-bold mb-4" style="display: none; background: #FEF2F2;">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i> Peringatan: Penghuni ini akan berstatus "Keluar" dan kamar saat ini akan dikosongkan.
                        </div>

                        <button type="submit" name="proses_mutasi" class="btn btn-dark w-100 rounded-4 py-3 fw-800 fs-6">
                            Eksekusi Mutasi <i class="fa-solid fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Inisialisasi Tooltip
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Buka Modal Mutasi
        function openMutasiModal(idPenghuni, nama, idKamarLama, noKamarLama) {
            document.getElementById('m_id_penghuni').value = idPenghuni;
            document.getElementById('m_id_kamar_lama').value = idKamarLama;
            document.getElementById('m_nama_penghuni').innerText = nama;
            document.getElementById('m_kamar_lama').innerText = "Kamar Saat Ini: KM. " + (noKamarLama ? noKamarLama : '-');
            
            new bootstrap.Modal(document.getElementById('modalMutasi')).show();
        }

        // Logic Animasi Transisi Pindah/Keluar (TANPA required HTML5)
        document.querySelectorAll('input[name="jenis_mutasi"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const containerKamar = document.getElementById('kamar_baru_container');
                const selectKamar = document.getElementById('kamar_baru_select');
                const warningKeluar = document.getElementById('warning_keluar');

                if (this.value === 'pindah') {
                    containerKamar.style.display = 'block';
                    warningKeluar.style.display = 'none';
                } else {
                    containerKamar.style.display = 'none';
                    selectKamar.value = ""; 
                    warningKeluar.style.display = 'block';
                }
            });
        });
    </script>

    <?php if($success_msg != "") : ?>
        <script>Swal.fire({ icon: 'success', title: 'Mutasi Berhasil', text: '<?= $success_msg; ?>', confirmButtonColor: '#0F172A', customClass: { popup: 'rounded-4' }});</script>
    <?php endif; ?>
    
    <?php if($error_msg != "") : ?>
        <script>Swal.fire({ icon: 'error', title: 'Peringatan Sistem', text: '<?= $error_msg; ?>', confirmButtonColor: '#EF4444', customClass: { popup: 'rounded-4' }});</script>
    <?php endif; ?>

</body>
</html>