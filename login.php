<?php
session_start();
require 'koneksi.php';

if (isset($_SESSION['login'])) {
    header("Location: dashboard.php");
    exit;
}

// ==========================================
// CONFIG GOOGLE OAUTH
// ==========================================
$client_id = "119645418813-3ang0d77k30hilmpcbv00o1pcfjq30k6.apps.googleusercontent.com"; 
$redirect_uri = "http://localhost/ManagementKost/auth-google-callback.php";

$google_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online'
]);

$error_msg = '';
$success_msg = '';
$active_tab = 'login'; 

// --- LOGIKA LOGIN ---
if (isset($_POST['login'])) {
    $identifier = mysqli_real_escape_string($conn, $_POST['identifier']);
    $password = $_POST['password'];
    $result = mysqli_query($conn, "SELECT * FROM users WHERE username = '$identifier' OR email = '$identifier'");
    
    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($password, $row['password'])) {
            $_SESSION['login'] = true;
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role']; 
            header("Location: dashboard.php");
            exit;
        }
    }
    $error_msg = "Kredensial tidak valid atau akun tidak ditemukan!";
}

// --- LOGIKA REGISTER ---
if (isset($_POST['register'])) {
    $username = mysqli_real_escape_string($conn, strtolower(stripslashes($_POST['reg_username'])));
    $email = mysqli_real_escape_string($conn, $_POST['reg_email']);
    $password = mysqli_real_escape_string($conn, $_POST['reg_password']);
    
    // Normalisasi Nomor WhatsApp (Memastikan format 628...)
    $raw_hp = mysqli_real_escape_string($conn, $_POST['reg_telp']);
    $clean_hp = ltrim($raw_hp, '0'); // Hapus angka 0 di depan jika ada
    $final_hp = '62' . $clean_hp;
    
    $cek_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' OR email = '$email'");
    if (mysqli_num_rows($cek_user) > 0) {
        $error_msg = "Username atau Email sudah terdaftar!";
        $active_tab = 'register';
    } else {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        
        // Sesuaikan dengan struktur tabel users terbaru (tanpa no_hp atau dengan no_hp)
        $query = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$password_hashed', 'user')";
        
        if (mysqli_query($conn, $query)) {
            $success_msg = "Akun berhasil dibuat! Silakan masuk.";
            $active_tab = 'login';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk & Daftar | KostAdmin</title>
    
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

        .form-container { width: 100%; max-width: 420px; }
        
        .form-header h2 { font-weight: 800; color: var(--dark); letter-spacing: -1px; font-size: 1.8rem; margin-bottom: 8px; }
        .form-header p { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 30px; }

        /* --- Form Elements --- */
        .form-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block; }
        
        .input-box { 
            position: relative; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; transition: all 0.2s; margin-bottom: 20px;
        }
        .input-box:focus-within { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1); }
        .input-box i.icon-left { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1rem; }
        .input-box input { width: 100%; border: none; background: transparent; padding: 14px 16px 14px 45px; outline: none; font-weight: 600; color: var(--dark); font-size: 0.95rem; }
        
        /* Modifikasi Spesifik untuk WA Input (+62) */
        .wa-box { display: flex; align-items: center; padding: 0 16px; }
        .wa-prefix { font-weight: 800; color: var(--dark); padding-right: 10px; border-right: 1px solid #e2e8f0; margin-right: 10px; display: flex; align-items: center; gap: 8px; }
        .wa-box input { padding-left: 0; }
        input[type="number"]::-webkit-outer-spin-button, input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type="number"] { -moz-appearance: textfield; }

        /* Password Specific */
        .pass-input { padding-right: 45px !important; }
        .toggle-pass { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; transition: 0.2s; }
        .toggle-pass:hover { color: var(--primary); }

        .btn-main { background: var(--primary); color: white; border: none; border-radius: 14px; padding: 14px; font-weight: 700; width: 100%; transition: 0.3s; margin-top: 5px; font-size: 1rem; }
        .btn-main:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(67, 97, 238, 0.25); }

        /* --- GOOGLE BUTTON & SEPARATOR --- */
        .separator { display: flex; align-items: center; text-align: center; margin: 25px 0; color: #94a3b8; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .separator::before, .separator::after { content: ''; flex: 1; border-bottom: 1.5px solid #e2e8f0; }
        .separator:not(:empty)::before { margin-right: 15px; }
        .separator:not(:empty)::after { margin-left: 15px; }

        .btn-google { background: white; color: #3c4043; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 14px; font-weight: 700; width: 100%; display: flex; align-items: center; justify-content: center; gap: 12px; text-decoration: none; transition: 0.3s; font-size: 0.95rem; }
        .btn-google:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); color: #3c4043; }

        .auth-footer { text-align: center; margin-top: 25px; font-size: 0.9rem; font-weight: 600; color: var(--text-muted); }
        .link-toggle { color: var(--primary); text-decoration: none; font-weight: 800; transition: 0.2s; cursor: pointer; }
        .link-toggle:hover { color: var(--primary-hover); text-decoration: underline; }
        
        .alert-custom { border-radius: 14px; border: none; font-weight: 600; font-size: 0.85rem; padding: 14px 16px; margin-bottom: 25px; }
        .hidden-section { display: none; }
        .fade-in { animation: fadeIn 0.4s ease-in forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Mobile Adjustments */
        @media (max-width: 991.98px) {
            .auth-hero { padding: 40px 20px; text-align: center; justify-content: center; min-height: 30vh; }
            .brand-logo-hero { justify-content: center; width: 100%; margin-bottom: 20px; }
            .hero-title { font-size: 2rem; }
            .hero-desc { margin: 0 auto; font-size: 0.95rem; }
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
                    <div class="hero-tagline">Manajemen Properti Modern</div>
                    <h1 class="hero-title">Mari Bergabung<br>Bersama Kami</h1>
                    <p class="hero-desc">Kesempatan tidak selalu datang dua kali. Temukan kemudahan mengelola dan mencari hunian kost impian Anda dalam satu portal terpadu yang aman dan efisien.</p>
                </div>
                
                <div class="z-1 d-none d-lg-block text-white-50 small fw-medium">
                    &copy; 2026 KostAdmin System. All rights reserved.
                </div>
            </div>

            <div class="col-lg-7 col-xl-6 auth-form-area">
                <div class="form-container">
                    
                    <?php if($error_msg) : ?>
                        <div class="alert alert-danger alert-custom fade-in"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error_msg; ?></div>
                    <?php endif; ?>
                    
                    <?php if($success_msg) : ?>
                        <div class="alert alert-success alert-custom fade-in"><i class="fa-solid fa-circle-check me-2"></i><?= $success_msg; ?></div>
                    <?php endif; ?>

                    <div id="login-section" class="<?= $active_tab == 'login' ? '' : 'hidden-section' ?>">
                        <div class="form-header">
                            <h2>Masuk Akun</h2>
                            <p>Silakan masuk menggunakan email atau username Anda.</p>
                        </div>

                        <form method="POST">
                            <label class="form-label">Email / Username</label>
                            <div class="input-box">
                                <i class="fa-regular fa-user icon-left"></i>
                                <input type="text" name="identifier" placeholder="Masukan Email / Username" required autocomplete="username">
                            </div>

                            <label class="form-label">Kata Sandi</label>
                            <div class="input-box">
                                <i class="fa-solid fa-lock icon-left"></i>
                                <input type="password" name="password" class="pass-input pass-field" placeholder="Masukan Password" required autocomplete="current-password">
                                <i class="fa-regular fa-eye toggle-pass" onclick="togglePassword(this)"></i>
                            </div>

                            <button type="submit" name="login" class="btn-main shadow-sm">Masuk Sekarang</button>
                            
                            <div class="separator">Atau Lanjutkan Dengan</div>

                            <a href="<?= $google_auth_url; ?>" class="btn-google">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20px" height="20px">
                                    <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
                                    <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
                                    <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
                                    <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
                                </svg>
                                Masuk dengan Google
                            </a>

                            <p class="auth-footer">
                                Belum bergabung? <span onclick="switchForm('register')" class="link-toggle">Daftar Akun Baru</span>
                            </p>
                        </form>
                    </div>

                    <div id="register-section" class="<?= $active_tab == 'register' ? 'fade-in' : 'hidden-section' ?>">
                        <div class="form-header">
                            <h2>Buat Akun Baru</h2>
                            <p>Lengkapi formulir di bawah ini untuk bergabung.</p>
                        </div>

                        <form method="POST">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="form-label">Username</label>
                                    <div class="input-box">
                                        <i class="fa-solid fa-at icon-left" style="font-size: 0.9rem;"></i>
                                        <input type="text" name="reg_username" placeholder="Username" required>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Nomor WhatsApp</label>
                                    <div class="input-box wa-box">
                                        <span class="wa-prefix"><i class="fa-brands fa-whatsapp text-success fs-6"></i> +62</span>
                                        <input type="number" name="reg_telp" placeholder="812..." required>
                                    </div>
                                </div>
                            </div>

                            <label class="form-label">Alamat Email</label>
                            <div class="input-box">
                                <i class="fa-regular fa-envelope icon-left"></i>
                                <input type="email" name="reg_email" placeholder="nama@email.com" required>
                            </div>

                            <label class="form-label">Buat Kata Sandi</label>
                            <div class="input-box">
                                <i class="fa-solid fa-key icon-left"></i>
                                <input type="password" name="reg_password" class="pass-input pass-field" placeholder="••••••••" required>
                                <i class="fa-regular fa-eye toggle-pass" onclick="togglePassword(this)"></i>
                            </div>

                            <button type="submit" name="register" class="btn-main shadow-sm">Selesaikan Pendaftaran</button>
                            
                            <div class="separator">Atau Daftar Otomatis</div>

                            <a href="<?= $google_auth_url; ?>" class="btn-google">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20px" height="20px">
                                    <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
                                    <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
                                    <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
                                    <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
                                </svg>
                                Daftar dengan Google
                            </a>

                            <p class="auth-footer">
                                Sudah terdaftar? <span onclick="switchForm('login')" class="link-toggle">Masuk di sini</span>
                            </p>
                        </form>
                    </div>

                </div>
            </div>
            
        </div>
    </div>

    <script>
        function switchForm(type) {
            const login = document.getElementById('login-section');
            const register = document.getElementById('register-section');
            
            if(type === 'register') {
                login.classList.add('hidden-section');
                register.classList.remove('hidden-section');
                register.classList.add('fade-in');
            } else {
                register.classList.add('hidden-section');
                login.classList.remove('hidden-section');
                login.classList.add('fade-in');
            }
        }

        function togglePassword(icon) {
            const input = icon.parentElement.querySelector('.pass-field');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('fa-eye', 'fa-eye-slash');
                icon.style.color = 'var(--primary)';
            } else {
                input.type = "password";
                icon.classList.replace('fa-eye-slash', 'fa-eye');
                icon.style.color = '#94a3b8';
            }
        }
    </script>
</body>
</html>