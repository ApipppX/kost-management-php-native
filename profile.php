<?php
session_start();
require 'koneksi.php';
cek_login();

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// 1. AMBIL DATA (SINKRONISASI RELASI MENGGUNAKAN EMAIL)
$query_str = "SELECT u.*, p.id as id_penghuni, p.nama_penghuni, p.no_hp as hp_penghuni, k.no_kamar, ks.nama_kost 
              FROM users u 
              LEFT JOIN penghuni p ON u.email = p.email 
              LEFT JOIN kamar k ON p.id_kamar = k.id
              LEFT JOIN kost ks ON k.id_kost = ks.id
              WHERE u.username = '$username'";
$query_user = mysqli_query($conn, $query_str);
$user = mysqli_fetch_assoc($query_user);
$foto_lama = $user['foto_profil'];

$hp_raw = $user['hp_penghuni'] ?? '';
$hp_display = preg_replace('/^(0|\+?62)/', '', $hp_raw);

$success_msg = "";
$error_msg = "";
$active_tab = "profil"; // Default tab

// 2. Logika A: UPLOAD FOTO 
if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === 0) {
    $file_foto = $_FILES['foto_profil'];
    if (!is_dir('uploads')) mkdir('uploads', 0777, true);

    mysqli_begin_transaction($conn);
    try {
        $ext = strtolower(pathinfo($file_foto['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed)) throw new Exception("Format tidak didukung. Gunakan JPG/PNG.");
        if ($file_foto['size'] > 2000000) throw new Exception("Ukuran maksimal file adalah 2MB.");

        $nama_file_baru = "PROF_" . strtoupper($username) . "_" . time() . "." . $ext;
        if (move_uploaded_file($file_foto['tmp_name'], 'uploads/' . $nama_file_baru)) {
            if (!empty($foto_lama) && file_exists('uploads/' . $foto_lama)) unlink('uploads/' . $foto_lama);
            mysqli_query($conn, "UPDATE users SET foto_profil = '$nama_file_baru' WHERE username = '$username'");
            $user['foto_profil'] = $nama_file_baru;
            $foto_lama = $nama_file_baru;
        }
        mysqli_commit($conn);
        $success_msg = "Foto profil berhasil diperbarui!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
    }
}

// 3. Logika B: HAPUS FOTO
if (isset($_POST['aksi_hapus_foto'])) {
    mysqli_begin_transaction($conn);
    try {
        if (!empty($foto_lama) && file_exists('uploads/' . $foto_lama)) unlink('uploads/' . $foto_lama);
        mysqli_query($conn, "UPDATE users SET foto_profil = NULL WHERE username = '$username'");
        mysqli_commit($conn);
        $success_msg = "Foto profil berhasil dihapus.";
        $user['foto_profil'] = NULL;
        $foto_lama = NULL;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Gagal menghapus foto.";
    }
}

// 4. Logika C: SIMPAN DATA DIRI (UPDATE KEDUA TABEL AGAR RELASI EMAIL TIDAK PUTUS)
if (isset($_POST['simpan_data_diri'])) {
    $active_tab = "profil";
    $email_baru = mysqli_real_escape_string($conn, $_POST['email']);
    $email_lama = $user['email'];
    
    mysqli_begin_transaction($conn);
    try {
        // Update email di tabel users
        mysqli_query($conn, "UPDATE users SET email = '$email_baru' WHERE username = '$username'");
        $user['email'] = $email_baru;

        // Jika user adalah penghuni, update no_hp dan email di tabel penghuni
        if ($role == 'user' && !empty($user['id_penghuni'])) {
            $raw_input = mysqli_real_escape_string($conn, $_POST['no_hp']);
            $clean_input = ltrim($raw_input, '0');
            $no_hp_baru = '62' . $clean_input; 
            
            $id_penghuni = $user['id_penghuni'];
            mysqli_query($conn, "UPDATE penghuni SET no_hp = '$no_hp_baru', email = '$email_baru' WHERE id = '$id_penghuni'");
            $user['hp_penghuni'] = $no_hp_baru;
            $hp_display = $clean_input;
        }
        mysqli_commit($conn);
        $success_msg = "Informasi kontak berhasil diperbarui!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Gagal memperbarui informasi kontak.";
    }
}

// 5. Logika D: GANTI PASSWORD
if (isset($_POST['ganti_password'])) {
    $active_tab = "keamanan";
    $pass_lama = $_POST['pass_lama'];
    $pass_baru = $_POST['pass_baru'];
    $pass_konfirm = $_POST['pass_konfirm'];

    if (password_verify($pass_lama, $user['password'])) {
        if ($pass_baru === $pass_konfirm) {
            if (strlen($pass_baru) >= 8) {
                $hash_baru = password_hash($pass_baru, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password = '$hash_baru' WHERE username = '$username'");
                $user['password'] = $hash_baru;
                $success_msg = "Kata sandi berhasil diperbarui! Gunakan sandi baru saat login nanti.";
            } else {
                $error_msg = "Kata sandi baru minimal 8 karakter.";
            }
        } else {
            $error_msg = "Konfirmasi kata sandi tidak cocok.";
        }
    } else {
        $error_msg = "Kata sandi saat ini salah.";
    }
}

// Avatar Setup
$default_avatar = "https://ui-avatars.com/api/?name=".urlencode($username)."&background=4F46E5&color=ffffff&size=250&font-size=0.4&bold=true";
$foto_preview = (!empty($user['foto_profil']) && file_exists('uploads/' . $user['foto_profil'])) 
                ? 'uploads/' . $user['foto_profil'] 
                : $default_avatar;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil & Pengaturan | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --primary: #4F46E5; --primary-hover: #4338CA; --bg: #F8FAFC; --text-main: #0F172A; --text-muted: #64748B; --border: #E2E8F0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        
        .container-app { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }
        
        /* Cards */
        .profile-card { background: white; border-radius: 24px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); padding: 30px; height: 100%; transition: 0.3s; }
        .profile-card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
        .card-header-clean { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px dashed var(--border); }
        .card-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
        
        /* Avatar & Stats Mini */
        .avatar-wrapper { position: relative; width: 140px; height: 140px; margin: 0 auto 20px; }
        .avatar-img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 5px solid white; box-shadow: 0 4px 20px rgba(79, 70, 229, 0.15); display: block; }
        
        .role-badge { display: inline-block; padding: 4px 12px; background: #EEF2FF; color: var(--primary); border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        
        .stat-mini-box { background: #F8FAFC; border: 1px solid var(--border); border-radius: 16px; padding: 15px; text-align: left; margin-top: 25px; }
        .stat-label { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; }
        .stat-value { font-size: 0.95rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        
        /* Buttons */
        .btn-upload-custom { background: var(--primary); color: white; border-radius: 50px; padding: 10px 20px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.2s; display: block; text-align: center; }
        .btn-upload-custom:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        
        .btn-delete-foto { background: white; border: 1px solid var(--border); color: #EF4444; border-radius: 50px; padding: 8px 20px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; display: block; width: 100%; }
        .btn-delete-foto:hover { background: #FEF2F2; border-color: #FECACA; color: #BE123C; }

        .btn-action { background: var(--primary); color: white; border-radius: 12px; padding: 12px 24px; font-weight: 800; border: none; transition: 0.3s; }
        .btn-action:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(79, 70, 229, 0.25); }

        /* Forms & Nav Tabs */
        .nav-custom { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        .nav-btn { background: transparent; border: none; padding: 8px 16px; border-radius: 10px; font-weight: 700; color: var(--text-muted); font-size: 0.9rem; transition: 0.2s; cursor: pointer; }
        .nav-btn:hover { background: #F1F5F9; color: var(--text-main); }
        .nav-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2); }

        .form-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { border-radius: 12px; border: 1.5px solid var(--border); padding: 12px 16px; background-color: #F8FAFC; font-weight: 600; color: var(--text-main); transition: all 0.2s; }
        .form-control:focus { background-color: #ffffff; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        .form-control:read-only { background-color: #F1F5F9; color: #94A3B8; cursor: not-allowed; }
        
        .input-group-text { border-color: var(--border); background: #F8FAFC; color: var(--text-muted); border-radius: 12px; }
        .prefix-box { background: white; font-weight: 800; color: var(--text-main); border-right: none; }
        .input-group:focus-within .prefix-box { border-color: var(--primary); }
        input[type="number"]::-webkit-outer-spin-button, input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type="number"] { -moz-appearance: textfield; }
        
        /* Pass toggle */
        .pass-wrapper { position: relative; }
        .toggle-pass { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8; cursor: pointer; transition: 0.2s; }
        .toggle-pass:hover { color: var(--primary); }
        
        .tab-content-custom { display: none; animation: fadeIn 0.3s ease; }
        .tab-content-custom.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="d-flex align-items-center mb-5">
            <a href="dashboard.php" class="btn btn-white shadow-sm rounded-circle me-3 border border-light d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; text-decoration:none;">
                <i class="fa-solid fa-arrow-left text-muted"></i>
            </a>
            <div>
                <h2 class="fw-800 mb-1">Pengaturan Profil</h2>
                <p class="text-muted small fw-600 mb-0">Kelola identitas, informasi kontak, dan keamanan akun Anda.</p>
            </div>
        </div>

        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="profile-card text-center">
                    <form method="POST" enctype="multipart/form-data" id="formFoto">
                        <div class="role-badge"><?= $role; ?> SYSTEM</div>
                        
                        <div class="avatar-wrapper">
                            <img src="<?= $foto_preview; ?>" class="avatar-img" alt="Foto Profil">
                        </div>
                        
                        <h4 class="fw-extrabold mb-0 text-dark"><?= !empty($user['nama_penghuni']) ? $user['nama_penghuni'] : ucfirst($username); ?></h4>
                        <p class="text-muted small fw-bold mb-4">@<?= $username; ?></p>
                        
                        <div class="px-md-3">
                            <label for="fileInput" class="btn-upload-custom mb-2">
                                <i class="fa-solid fa-camera me-1"></i> Perbarui Foto
                            </label>
                            <input type="file" name="foto_profil" id="fileInput" class="d-none" accept=".jpg, .jpeg, .png" onchange="document.getElementById('formFoto').submit();">

                            <?php if(!empty($user['foto_profil']) && file_exists('uploads/' . $user['foto_profil'])) : ?>
                                <button type="submit" name="aksi_hapus_foto" class="btn-delete-foto" onclick="return confirm('Hapus foto profil?')">
                                    Hapus Foto
                                </button>
                            <?php endif; ?>
                            <p class="text-muted mt-3 mb-0" style="font-size: 0.7rem; font-weight: 600;">Format: JPG, PNG. Maks: 2MB.</p>
                        </div>
                    </form>

                    <?php if ($role == 'user' && !empty($user['id_penghuni'])) : ?>
                        <div class="stat-mini-box">
                            <div class="stat-label">Penempatan Properti</div>
                            <div class="stat-value">
                                <i class="fa-solid fa-door-open text-primary"></i> 
                                <?= $user['nama_kost'] ?? 'Belum ada'; ?> (Unit <?= $user['no_kamar'] ?? '-'; ?>)
                            </div>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>

            <div class="col-lg-8">
                <div class="profile-card">
                    
                    <div class="nav-custom">
                        <button type="button" class="nav-btn <?= $active_tab == 'profil' ? 'active' : ''; ?>" onclick="switchTab('profil')">
                            <i class="fa-solid fa-user me-1"></i> Data Personal
                        </button>
                        <button type="button" class="nav-btn <?= $active_tab == 'keamanan' ? 'active' : ''; ?>" onclick="switchTab('keamanan')">
                            <i class="fa-solid fa-shield-halved me-1"></i> Keamanan Akun
                        </button>
                    </div>
                    
                    <div id="tab-profil" class="tab-content-custom <?= $active_tab == 'profil' ? 'active' : ''; ?>">
                        <form method="POST">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Username System</label>
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0"><i class="fa-solid fa-at"></i></span>
                                        <input type="text" class="form-control border-start-0 ps-0" value="<?= $user['username']; ?>" readonly>
                                    </div>
                                    <small class="text-muted mt-1 d-block" style="font-size: 0.7rem;">Username tidak dapat diubah.</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">ID Registrasi</label>
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0"><i class="fa-solid fa-hashtag"></i></span>
                                        <input type="text" class="form-control border-start-0 ps-0" value="USR-00<?= $user['id']; ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mt-4"><hr class="text-muted opacity-25 m-0"></div>

                                <div class="col-md-6 mt-4">
                                    <label class="form-label">Alamat Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0 bg-white"><i class="fa-regular fa-envelope"></i></span>
                                        <input type="email" name="email" class="form-control border-start-0 ps-0" value="<?= $user['email']; ?>" placeholder="email@anda.com" required>
                                    </div>
                                </div>

                                <?php if ($role == 'user') : ?>
                                <div class="col-md-6 mt-4">
                                    <label class="form-label">No. WhatsApp <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text prefix-box rounded-start-3">
                                            <i class="fa-brands fa-whatsapp text-success me-2 fs-6"></i> +62
                                        </span>
                                        <input type="number" name="no_hp" class="form-control border-start-0 ps-1" value="<?= $hp_display; ?>" placeholder="81234567890" required>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-end mt-5">
                                <button type="submit" name="simpan_data_diri" class="btn-action shadow-sm">
                                    Simpan Profil
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="tab-keamanan" class="tab-content-custom <?= $active_tab == 'keamanan' ? 'active' : ''; ?>">
                        <form method="POST">
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="alert bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 pb-0">
                                        <p class="small fw-bold text-primary mb-2"><i class="fa-solid fa-circle-info me-1"></i> Disarankan mengganti kata sandi secara berkala untuk menjaga keamanan akun Anda.</p>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Kata Sandi Saat Ini <span class="text-danger">*</span></label>
                                    <div class="pass-wrapper">
                                        <input type="password" name="pass_lama" class="form-control" placeholder="Masukkan kata sandi lama" required>
                                        <i class="fa-regular fa-eye toggle-pass" onclick="togglePassword(this)"></i>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Kata Sandi Baru <span class="text-danger">*</span></label>
                                    <div class="pass-wrapper">
                                        <input type="password" name="pass_baru" class="form-control" placeholder="Minimal 8 karakter" required minlength="8">
                                        <i class="fa-regular fa-eye toggle-pass" onclick="togglePassword(this)"></i>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Konfirmasi Kata Sandi Baru <span class="text-danger">*</span></label>
                                    <div class="pass-wrapper">
                                        <input type="password" name="pass_konfirm" class="form-control" placeholder="Ketik ulang sandi baru" required minlength="8">
                                        <i class="fa-regular fa-eye toggle-pass" onclick="togglePassword(this)"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-5">
                                <button type="submit" name="ganti_password" class="btn-action shadow-sm bg-dark text-white border-0">
                                    <i class="fa-solid fa-lock me-2"></i> Perbarui Kata Sandi
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
            
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Logika Ganti Tab
        function switchTab(tabId) {
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content-custom').forEach(tab => tab.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        // Logika Lihat/Sembunyi Password
        function togglePassword(icon) {
            const input = icon.parentElement.querySelector('input');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('fa-eye', 'fa-eye-slash');
                icon.style.color = 'var(--primary)';
            } else {
                input.type = "password";
                icon.classList.replace('fa-eye-slash', 'fa-eye');
                icon.style.color = '#94A3B8';
            }   
        }
    </script>
    
    <?php if($success_msg != "") : ?>
        <script>
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= $success_msg; ?>', confirmButtonColor: '#4F46E5', timer: 2500 });
        </script>
    <?php endif; ?>
    
    <?php if($error_msg != "") : ?>
        <script>
            Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan!', text: '<?= $error_msg; ?>', confirmButtonColor: '#0F172A' });
        </script>
    <?php endif; ?>

</body>
</html>