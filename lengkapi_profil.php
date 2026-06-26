<?php
session_start();
require 'koneksi.php';

// Pastikan user masuk ke halaman ini melalui alur Google Auth
if (!isset($_SESSION['temp_google_email'])) {
    header("Location: login.php");
    exit;
}

$email_google = $_SESSION['temp_google_email'];
// Buat default username dari nama depan email
$default_username = explode('@', $email_google)[0];
$error_msg = '';

if (isset($_POST['submit_profil'])) {
    $username = mysqli_real_escape_string($conn, strtolower(stripslashes($_POST['username'])));
    
    // Normalisasi Nomor WhatsApp
    $raw_hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $clean_hp = ltrim($raw_hp, '0'); 
    $final_hp = '62' . $clean_hp;

    // Validasi apakah username yang diinput sudah dipakai orang lain
    $cek_username = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
    
    if (mysqli_num_rows($cek_username) > 0) {
        $error_msg = "Maaf, Username '{$username}' sudah digunakan. Silakan coba yang lain.";
    } else {
        $password_random = password_hash(uniqid(), PASSWORD_DEFAULT); 
        
        // 1. Masukkan data ke tabel users terlebih dahulu
        $query_user = "INSERT INTO users (username, email, password, role) 
                       VALUES ('$username', '$email_google', '$password_random', 'user')";
        
        if (mysqli_query($conn, $query_user)) {
            
            // 2. Masukkan data profil ke tabel penghuni (Sesuaikan dengan gambar database)
            $query_penghuni = "INSERT INTO penghuni (nama_penghuni, email, no_hp) 
                               VALUES ('$username', '$email_google', '$final_hp')";
            
            if (mysqli_query($conn, $query_penghuni)) {
                // Jika kedua tabel berhasil diisi, set session login
                $_SESSION['login'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user';
                
                unset($_SESSION['temp_google_email']);
                
                header("Location: dashboard.php");
                exit;
            } else {
                // Jika query kedua gagal, hapus user yang telanjur dibuat agar tidak duplikat
                mysqli_query($conn, "DELETE FROM users WHERE email = '$email_google'");
                $error_msg = "Gagal menyimpan data ke tabel penghuni: " . mysqli_error($conn);
            }
        } else {
            $error_msg = "Gagal menyimpan akun ke tabel users: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Profil | KostAdmin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root { 
            --primary: #4361ee; 
            --primary-hover: #3751c7;
            --dark: #0f172a;
            --text-muted: #64748b;
            --bg-light: #f8fafc;
        }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-light);
            margin: 0;
            overflow-x: hidden;
        }

        /* --- Split Layout Setup --- */
        .auth-wrapper { min-height: 100vh; display: flex; flex-wrap: wrap; }
        
        /* --- Left Side (Hero/Branding) --- */
        .auth-hero {
            background: linear-gradient(135deg, var(--primary) 0%, #2b45cc 100%);
            color: white;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        
        .auth-hero::after {
            content: ''; position: absolute; top: 0; right: 0; bottom: 0; left: 0;
            background-image: radial-gradient(circle at 20% 150%, rgba(255,255,255,0.1) 0%, transparent 50%), 
                              radial-gradient(circle at 80% -50%, rgba(255,255,255,0.15) 0%, transparent 50%);
            pointer-events: none;
        }

        .brand-logo-hero { display: inline-flex; align-items: center; gap: 12px; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; z-index: 1; }
        .brand-logo-hero i { background: white; color: var(--primary); width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }

        .hero-content { z-index: 1; margin-top: auto; margin-bottom: auto; }
        .hero-tagline { color: #cbd5e1; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; font-size: 0.85rem; margin-bottom: 15px; }
        .hero-title { font-weight: 800; font-size: 3rem; line-height: 1.2; letter-spacing: -1px; margin-bottom: 20px; }
        .hero-desc { font-size: 1.1rem; color: #e2e8f0; font-weight: 400; line-height: 1.6; max-width: 90%; }

        /* --- Right Side (Form Area) --- */
        .auth-form-area {
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .form-container { width: 100%; max-width: 420px; animation: fadeIn 0.5s ease-in forwards; }
        
        .form-header h2 { font-weight: 800; color: var(--dark); letter-spacing: -1px; font-size: 1.8rem; margin-bottom: 8px; }
        .form-header p { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 30px; }

        /* --- Form Elements --- */
        .form-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block; }
        
        .input-box { 
            position: relative; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; transition: all 0.2s; margin-bottom: 20px;
        }
        .input-box.readonly { background: #e2e8f0; opacity: 0.8; }
        .input-box:not(.readonly):focus-within { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1); }
        .input-box i.icon-left { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1rem; }
        .input-box input { width: 100%; border: none; background: transparent; padding: 14px 16px 14px 45px; outline: none; font-weight: 600; color: var(--dark); font-size: 0.95rem; }
        .input-box.readonly input { color: #64748b; }
        
        /* Modifikasi Spesifik untuk WA Input (+62) */
        .wa-box { display: flex; align-items: center; padding: 0 16px; }
        .wa-prefix { font-weight: 800; color: var(--dark); padding-right: 10px; border-right: 1px solid #e2e8f0; margin-right: 10px; display: flex; align-items: center; gap: 8px; }
        .wa-box input { padding-left: 0; }
        input[type="number"]::-webkit-outer-spin-button, input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type="number"] { -moz-appearance: textfield; }

        .btn-main { background: var(--primary); color: white; border: none; border-radius: 14px; padding: 15px; font-weight: 700; width: 100%; transition: 0.3s; margin-top: 10px; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-main:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(67, 97, 238, 0.25); }
        
        .alert-custom { border-radius: 14px; border: none; font-weight: 600; font-size: 0.85rem; padding: 14px 16px; margin-bottom: 25px; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* Mobile Adjustments */
        @media (max-width: 991.98px) {
            .auth-hero { padding: 40px 20px; text-align: center; justify-content: center; min-height: 25vh; }
            .brand-logo-hero { justify-content: center; width: 100%; margin-bottom: 15px; }
            .hero-title { font-size: 1.8rem; }
            .hero-desc { display: none; } /* Hide description on mobile to save space */
        }
    </style>
</head>
<body>

    <div class="container-fluid p-0">
        <div class="row g-0 auth-wrapper">
            
            <div class="col-lg-5 col-xl-6 auth-hero">
                <div class="brand-logo-hero">
                    <i class="fa-solid fa-house-chimney-window"></i> KostAdmin
                </div>
                
                <div class="hero-content">
                    <div class="hero-tagline">Onboarding System</div>
                    <h1 class="hero-title">Satu Langkah<br>Lagi!</h1>
                    <p class="hero-desc">Akun Google Anda berhasil ditautkan. Lengkapi profil di samping agar kami dapat mempersonalisasi pengalaman dan notifikasi tagihan Anda.</p>
                </div>
                
                <div class="z-1 d-none d-lg-block text-white-50 small fw-medium">
                    &copy; 2026 KostAdmin System. All rights reserved.
                </div>
            </div>

            <div class="col-lg-7 col-xl-6 auth-form-area">
                <div class="form-container">
                    
                    <div class="form-header">
                        <h2>Lengkapi Profil</h2>
                        <p>Silakan tentukan Username dan Nomor WhatsApp Anda.</p>
                    </div>

                    <?php if($error_msg) : ?>
                        <div class="alert alert-danger alert-custom"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error_msg; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <label class="form-label">Email Tertaud (Google)</label>
                        <div class="input-box readonly">
                            <i class="fa-solid fa-envelope icon-left"></i>
                            <input type="email" value="<?= $email_google ?>" readonly>
                        </div>

                        <label class="form-label">Buat Username Baru</label>
                        <div class="input-box">
                            <i class="fa-solid fa-at icon-left"></i>
                            <input type="text" name="username" value="<?= $default_username ?>" required autocomplete="off">
                        </div>

                        <label class="form-label">Nomor WhatsApp Aktif</label>
                        <div class="input-box wa-box">
                            <span class="wa-prefix"><i class="fa-brands fa-whatsapp text-success fs-6"></i> +62</span>
                            <input type="number" name="no_hp" placeholder="81234567890" required autocomplete="off">
                        </div>

                        <button type="submit" name="submit_profil" class="btn-main shadow-sm">
                            Selesai & Masuk Sistem <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>

                </div>
            </div>
            
        </div>
    </div>

</body>
</html>