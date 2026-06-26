<?php
session_start();
require 'koneksi.php';
cek_login();

if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$username_admin = $_SESSION['username'];
$error_msg = "";
$success_msg = "";

// --- PROSES SIMPAN REGISTRASI OFFLINE ---
if (isset($_POST['simpan'])) {
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_penghuni']));
    $id_kamar = mysqli_real_escape_string($conn, $_POST['id_kamar']);
    $username_baru = mysqli_real_escape_string($conn, strtolower(trim($_POST['username_baru'])));
    
    $raw_hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $no_hp = '62' . ltrim($raw_hp, '0');
    
    $cek_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username_baru'");
    $cek_kamar = mysqli_query($conn, "SELECT status FROM kamar WHERE id = '$id_kamar'");
    $kamar_data = mysqli_fetch_assoc($cek_kamar);

    if (mysqli_num_rows($cek_user) > 0) {
        $error_msg = "Username '$username_baru' sudah digunakan. Silakan pilih username lain.";
    } elseif ($kamar_data['status'] != 'Tersedia') {
        $error_msg = "Kamar ini baru saja disewa. Silakan pilih unit lain.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "INSERT INTO penghuni (nama_penghuni, no_hp, id_kamar) VALUES ('$nama', '$no_hp', '$id_kamar')");
            $id_penghuni_baru = mysqli_insert_id($conn); 

            $password_default = password_hash('12345678', PASSWORD_DEFAULT);
            mysqli_query($conn, "INSERT INTO users (username, password, role, id_penghuni) VALUES ('$username_baru', '$password_default', 'user', '$id_penghuni_baru')");

            mysqli_query($conn, "UPDATE kamar SET status = 'Terisi' WHERE id = '$id_kamar'");

            mysqli_commit($conn);
            $success_msg = "Registrasi Selesai! Data penghuni dan akses sistem telah dibuat.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = "Kegagalan sistem database: " . $e->getMessage();
        }
    }
}

// Ambil daftar kamar (Tahan Banting jika kolom kode_cabang belum ada)
try {
    $kamar_query = mysqli_query($conn, "
        SELECT kam.id, kam.no_kamar, kam.harga_perbulan, k.nama_kost
        FROM kamar kam 
        JOIN kost k ON kam.id_kost = k.id 
        WHERE kam.status = 'Tersedia' 
        ORDER BY k.nama_kost ASC, kam.no_kamar ASC
    ");
} catch(Exception $e) {
    $kamar_query = false;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Penyewa | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { 
            --primary: #4F46E5; --primary-hover: #4338CA; 
            --bg: #F8FAFC; --surface: #ffffff; 
            --text-main: #0F172A; --text-muted: #64748B; 
            --border-light: #E2E8F0; 
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        
        .container-app { max-width: 1100px; margin: 0 auto; padding: 40px 20px; }
        
        /* HEADER */
        .page-header { margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .btn-back { background: var(--surface); border: 1px solid var(--border-light); width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 12px; color: var(--text-muted); text-decoration: none; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .btn-back:hover { background: #F1F5F9; color: var(--text-main); }
        .header-title { font-weight: 800; font-size: 1.5rem; letter-spacing: -0.5px; margin: 0; }
        .header-subtitle { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin: 0; }

        /* CARDS */
        .main-card { background: var(--surface); border-radius: 20px; border: 1px solid var(--border-light); padding: 30px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.02); height: 100%; }
        
        /* FORM SECTIONS */
        .form-section { background: #F8FAFC; border: 1px solid var(--border-light); border-radius: 16px; padding: 25px; margin-bottom: 20px; }
        .section-badge { display: inline-flex; align-items: center; gap: 8px; background: white; border: 1px solid var(--border-light); padding: 6px 12px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--primary); margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        
        /* INPUTS */
        .form-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 12px; border: 1.5px solid var(--border-light); padding: 12px 16px; font-weight: 600; font-size: 0.95rem; color: var(--text-main); transition: 0.2s; background: white; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        
        /* +62 Prefix Style */
        .input-group-text.wa-prefix { background: #F1F5F9; border-color: var(--border-light); border-right: none; color: var(--text-main); font-weight: 800; border-top-left-radius: 12px; border-bottom-left-radius: 12px; padding: 0 16px; }
        .input-group .form-control.wa-input { border-left: none; padding-left: 0; }
        .input-group:focus-within .wa-prefix { border-color: var(--primary); }
        input[type="number"]::-webkit-outer-spin-button, input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type="number"] { -moz-appearance: textfield; }

        /* BUTTONS */
        .btn-submit { background: var(--primary); color: white; border: none; border-radius: 12px; padding: 14px 0; font-weight: 800; font-size: 0.95rem; width: 100%; transition: 0.3s; }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(79, 70, 229, 0.25); }

        /* SIDEBAR INFO PANEL */
        .info-panel { background: linear-gradient(145deg, #1E293B 0%, #0F172A 100%); border-radius: 20px; padding: 30px; color: white; height: 100%; box-shadow: 0 15px 30px -5px rgba(15, 23, 42, 0.4); position: relative; overflow: hidden; }
        .info-panel::after { content: ''; position: absolute; top: -30%; right: -30%; width: 200px; height: 200px; background: rgba(79, 70, 229, 0.4); filter: blur(50px); border-radius: 50%; pointer-events: none; }
        .info-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; z-index: 1; position: relative; }
        
        .step-item { display: flex; gap: 15px; margin-bottom: 25px; z-index: 1; position: relative; }
        .step-icon { width: 36px; height: 36px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; color: #38BDF8; flex-shrink: 0; }
        .step-text h6 { font-weight: 700; font-size: 0.9rem; margin: 0 0 4px 0; color: white; }
        .step-text p { font-size: 0.8rem; color: #94A3B8; margin: 0; line-height: 1.5; }

        .alert-pro { background: rgba(56, 189, 248, 0.1); border-left: 4px solid #38BDF8; border-radius: 8px; padding: 15px; z-index: 1; position: relative; }
        .alert-pro-title { font-size: 0.75rem; font-weight: 800; color: #38BDF8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .alert-pro-desc { font-size: 0.8rem; color: #E2E8F0; margin: 0; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="page-header">
            <a href="penghuni.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h1 class="header-title">Registrasi Penyewa</h1>
                <p class="header-subtitle">Tambah penyewa baru (Walk-in) ke dalam sistem.</p>
            </div>
        </div>

        <form method="POST">
            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="main-card">
                        
                        <div class="form-section">
                            <div class="section-badge"><i class="fa-solid fa-1"></i> Data Pribadi</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Nama Lengkap Sesuai Identitas <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_penghuni" class="form-control" placeholder="Ketik nama lengkap penyewa..." required autocomplete="off">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Nomor WhatsApp Aktif <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text wa-prefix"><i class="fa-brands fa-whatsapp text-success me-2 fs-6"></i> +62</span>
                                        <input type="number" name="no_hp" class="form-control wa-input" placeholder="81234567890" required autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section mb-4">
                            <div class="section-badge"><i class="fa-solid fa-2"></i> Penempatan & Akses</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Pilih Unit Kamar <span class="text-danger">*</span></label>
                                    <select name="id_kamar" class="form-select" required>
                                        <option value="">-- Pilih Lokasi Properti & Unit --</option>
                                        <?php if ($kamar_query && mysqli_num_rows($kamar_query) > 0) : ?>
                                            <?php while ($kamar = mysqli_fetch_assoc($kamar_query)) : ?>
                                                <option value="<?= $kamar['id']; ?>">
                                                    <?= $kamar['nama_kost']; ?> - Unit <?= $kamar['no_kamar']; ?> (Tarif: Rp <?= number_format($kamar['harga_perbulan'], 0, ',', '.'); ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        <?php else : ?>
                                            <option value="" disabled>Status: Semua unit kamar saat ini penuh.</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-12 mt-3">
                                    <label class="form-label">Buat Username Login <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted ps-3"><i class="fa-solid fa-at"></i></span>
                                        <input type="text" name="username_baru" class="form-control border-start-0 ps-1" placeholder="contoh: budi.santoso" required autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="simpan" class="btn-submit">
                            <i class="fa-solid fa-check-circle me-2"></i> Konfirmasi & Daftarkan Penyewa
                        </button>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="info-panel">
                        <div class="info-title">
                            <i class="fa-solid fa-microchip text-primary"></i> Automasi Sistem
                        </div>

                        <div class="step-item">
                            <div class="step-icon"><i class="fa-solid fa-database"></i></div>
                            <div class="step-text">
                                <h6>Simpan Biodata</h6>
                                <p>Sistem menyimpan data identitas dan kontak penyewa ke database pusat.</p>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-icon"><i class="fa-solid fa-door-closed"></i></div>
                            <div class="step-text">
                                <h6>Kunci Unit Kamar</h6>
                                <p>Kamar yang dipilih otomatis berubah status menjadi 'Terisi' (Tidak bisa di-booking orang lain).</p>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-icon"><i class="fa-solid fa-user-lock"></i></div>
                            <div class="step-text">
                                <h6>Generate Kredensial</h6>
                                <p>Sistem merilis akun portal penyewa agar mereka bisa melihat tagihan bulanan.</p>
                            </div>
                        </div>

                        <div class="alert-pro mt-4">
                            <div class="alert-pro-title"><i class="fa-solid fa-circle-info me-1"></i> KATA SANDI SEMENTARA</div>
                            <p class="alert-pro-desc">Beritahu penyewa untuk login menggunakan username yang Anda buat, dan masukkan kata sandi default: <strong class="text-white">12345678</strong>.</p>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if($success_msg != "") : ?>
        <script>
            Swal.fire({ 
                icon: 'success', 
                title: 'Registrasi Sukses!', 
                text: '<?= $success_msg; ?>', 
                confirmButtonColor: '#4F46E5',
                confirmButtonText: 'Tutup'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'penghuni.php';
                }
            });
        </script>
    <?php endif; ?>
    
    <?php if($error_msg != "") : ?>
        <script>
            Swal.fire({ 
                icon: 'error', 
                title: 'Validasi Gagal', 
                text: '<?= $error_msg; ?>',
                confirmButtonColor: '#0F172A'
            });
        </script>
    <?php endif; ?>

</body>
</html>