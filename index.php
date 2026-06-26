<?php
session_start();
require 'koneksi.php'; // Panggil database untuk menampilkan preview kost

// Redirect ke dashboard jika sudah login
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: dashboard.php");
    exit;
}

// Ambil 3 data kost untuk preview di Landing Page
$query_kost = "SELECT ks.id, ks.nama_kost, ks.alamat, ks.foto_bangunan, 
               (SELECT MIN(harga_perbulan) FROM kamar WHERE id_kost = ks.id AND status = 'Tersedia') as harga_mulai,
               (SELECT COUNT(id) FROM kamar WHERE id_kost = ks.id AND status = 'Tersedia') as sisa_kamar
               FROM kost ks LIMIT 3";
$kost_result = mysqli_query($conn, $query_kost);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KostAdmin Pro | Manajemen Properti Modern</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root { 
            --primary: #4F46E5; 
            --primary-hover: #3730A3;
            --dark: #0F172A;
            --light-bg: #F8FAFC;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
        }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: white; 
            color: var(--dark);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Navbar --- */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            padding: 15px 0;
            transition: all 0.3s ease;
        }
        .navbar-brand { font-weight: 800; font-size: 1.35rem; color: var(--dark) !important; letter-spacing: -0.5px; }
        .navbar-brand i { color: var(--primary); margin-right: 8px; }
        
        .nav-link { font-weight: 600; font-size: 0.95rem; color: var(--text-muted) !important; transition: 0.2s; }
        .nav-link:hover { color: var(--primary) !important; }

        .btn-nav-login { font-weight: 700; color: var(--dark); font-size: 0.95rem; padding: 10px 20px; transition: 0.2s; border-radius: 12px; text-decoration: none; }
        .btn-nav-login:hover { background: #F1F5F9; color: var(--primary); }
        .btn-nav-register { background: var(--dark); color: white; font-weight: 700; font-size: 0.95rem; padding: 10px 24px; border-radius: 12px; transition: 0.3s; text-decoration: none; border: 1px solid transparent; }
        .btn-nav-register:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2); color: white; }

        /* --- Hero Section --- */
        .hero-section {
            padding: 140px 0 100px 0;
            background: radial-gradient(120% 120% at 50% -10%, #EEF2FF 0%, #FFFFFF 100%);
            position: relative;
        }
        .hero-title { font-size: 3.8rem; font-weight: 800; line-height: 1.15; letter-spacing: -1.5px; margin-bottom: 24px; color: var(--dark); }
        .hero-title span { background: linear-gradient(135deg, var(--primary), #818CF8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero-subtitle { font-size: 1.15rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 40px; font-weight: 500; max-width: 90%; }
        
        .btn-hero-primary { background: var(--primary); color: white; font-weight: 700; padding: 16px 35px; border-radius: 14px; font-size: 1.05rem; transition: 0.3s; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-hero-primary:hover { background: var(--primary-hover); transform: translateY(-3px); box-shadow: 0 15px 30px rgba(79, 70, 229, 0.3); color: white; }
        
        .btn-hero-secondary { background: white; color: var(--dark); font-weight: 700; padding: 16px 35px; border-radius: 14px; font-size: 1.05rem; transition: 0.3s; border: 1.5px solid var(--border-color); text-decoration: none; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .btn-hero-secondary:hover { border-color: var(--dark); color: var(--dark); background: #F8FAFC; }

        .hero-image-wrapper { position: relative; z-index: 1; }
        .hero-image { width: 100%; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); border: 8px solid white; }
        
        .floating-badge { position: absolute; bottom: -20px; left: -30px; background: white; padding: 16px 24px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 16px; border: 1px solid var(--border-color); z-index: 2; }
        .badge-icon { width: 45px; height: 45px; background: #D1FAE5; color: #10B981; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .badge-text { font-weight: 800; color: var(--dark); font-size: 0.95rem; margin: 0; }
        .badge-sub { font-size: 0.8rem; color: var(--text-muted); margin: 0; font-weight: 600; }

        /* --- Global Sections --- */
        .section-padding { padding: 100px 0; }
        .section-header { text-align: center; margin-bottom: 60px; }
        .section-tag { background: #EEF2FF; color: var(--primary); font-weight: 700; padding: 8px 16px; border-radius: 50px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.5px; display: inline-block; margin-bottom: 15px; }
        .section-title { font-size: 2.5rem; font-weight: 800; color: var(--dark); letter-spacing: -1px; margin-bottom: 15px; }
        .section-desc { color: var(--text-muted); font-size: 1.1rem; max-width: 600px; margin: 0 auto; line-height: 1.6; }
        
        /* --- Preview Katalog Kost Section --- */
        .katalog-section { background: var(--light-bg); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
        .property-card { background: white; border-radius: 20px; overflow: hidden; border: 1px solid var(--border-color); transition: 0.3s; height: 100%; display: flex; flex-direction: column; }
        .property-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08); }
        .property-img-wrapper { position: relative; height: 220px; overflow: hidden; }
        .property-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .property-card:hover .property-img { transform: scale(1.08); }
        .property-badge { position: absolute; top: 15px; left: 15px; background: rgba(255,255,255,0.9); backdrop-filter: blur(4px); padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; color: var(--dark); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .property-body { padding: 25px; display: flex; flex-direction: column; flex-grow: 1; }
        .property-title { font-size: 1.25rem; font-weight: 800; margin-bottom: 8px; color: var(--dark); }
        .property-location { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 8px; }
        .property-footer { margin-top: auto; padding-top: 20px; border-top: 1px dashed var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .price-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .price-value { font-size: 1.15rem; font-weight: 800; color: var(--primary); }

        /* --- Features Section --- */
        .feature-card { background: white; border: 1px solid var(--border-color); border-radius: 24px; padding: 40px; transition: all 0.3s ease; height: 100%; position: relative; overflow: hidden; }
        .feature-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08); border-color: transparent; }
        .feature-icon { width: 55px; height: 55px; background: #EEF2FF; color: var(--primary); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 25px; }
        .feature-title { font-size: 1.25rem; font-weight: 800; margin-bottom: 15px; color: var(--dark); }
        .feature-desc { color: var(--text-muted); line-height: 1.6; margin: 0; font-size: 0.95rem; }

        /* --- CTA Section --- */
        .cta-section { padding: 80px 0; }
        .cta-box { background: var(--dark); border-radius: 30px; padding: 70px 40px; text-align: center; color: white; position: relative; overflow: hidden; }
        .cta-box::after { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(79, 70, 229, 0.15) 0%, transparent 60%); pointer-events: none; }
        .cta-title { font-size: 2.2rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 20px; position: relative; z-index: 2; }
        .cta-desc { font-size: 1.05rem; color: #CBD5E1; max-width: 600px; margin: 0 auto 40px; position: relative; z-index: 2; line-height: 1.6; }

        /* --- Footer --- */
        .footer { background: white; padding: 30px 0; border-top: 1px solid var(--border-color); }
        .footer-text { color: var(--text-muted); font-weight: 500; font-size: 0.9rem; margin: 0; }

        @media (max-width: 991.98px) {
            .hero-title { font-size: 2.5rem; }
            .hero-section { padding: 120px 0 60px 0; text-align: center; }
            .hero-subtitle { margin: 0 auto 30px; }
            .btn-hero-primary, .btn-hero-secondary { width: 100%; margin-bottom: 12px; }
            .hero-image-wrapper { margin-top: 60px; }
            .floating-badge { left: 50%; transform: translateX(-50%); bottom: -25px; width: 85%; justify-content: center; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fa-solid fa-house-chimney-window"></i> KostAdmin Pro
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fa-solid fa-bars fs-4 text-dark"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto mt-3 mt-lg-0">
                    <li class="nav-item"><a class="nav-link px-3" href="#katalog">Katalog Kost</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#fitur">Keunggulan</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2 mt-3 mt-lg-0">
                    <a href="login.php" class="btn-nav-login text-center">Masuk</a>
                    <a href="register.php" class="btn-nav-register text-center">Daftar Akun</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 pe-lg-5" data-aos="fade-right" data-aos-duration="800">
                    <h1 class="hero-title">Manajemen Properti & Pemesanan Kamar <span>Masa Kini.</span></h1>
                    <p class="hero-subtitle">Platform cerdas yang menghubungkan pencari hunian dengan pengelola secara otomatis. Booking instan dan bayar tagihan tanpa repot.</p>
                    
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#katalog" class="btn-hero-primary">
                            Eksplorasi Properti <i class="fa-solid fa-arrow-down ms-2"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 mt-5 mt-lg-0" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                    <div class="hero-image-wrapper">
                        <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1000&q=80" alt="Ilustrasi Kamar Premium" class="hero-image">
                        
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="katalog" class="section-padding katalog-section">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Eksplorasi Area</span>
                <h2 class="section-title">Temukan Hunian Idealmu</h2>
                <p class="section-desc">Pilih dari berbagai properti premium kami yang tersebar di lokasi strategis. Fasilitas lengkap, harga transparan, tanpa biaya tersembunyi.</p>
            </div>

            <div class="row g-4">
                <?php 
                $dummy_img = ["1493809842364-78817add7ffb", "1502672260266-1c1ef2d93688", "1513694203232-719a280e022f"];
                $counter = 0;
                
                if (mysqli_num_rows($kost_result) > 0) : 
                    while($row = mysqli_fetch_assoc($kost_result)) :
                        $foto_id = $dummy_img[$counter % 3];
                        $foto_src = (!empty($row['foto_bangunan']) && file_exists('uploads/kost/' . $row['foto_bangunan'])) 
                                    ? 'uploads/kost/'.$row['foto_bangunan'] 
                                    : "https://images.unsplash.com/photo-{$foto_id}?auto=format&fit=crop&w=800&q=80";
                        
                        $harga_mulai = $row['harga_mulai'] ? "Rp " . number_format($row['harga_mulai'], 0, ',', '.') : "Belum ada harga";
                        $stok_kamar = $row['sisa_kamar'] > 0 ? $row['sisa_kamar'] . " Unit Tersedia" : "Penuh";
                ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= 100 * ($counter + 1); ?>">
                    <div class="property-card">
                        <div class="property-img-wrapper">
                            <span class="property-badge"><i class="fa-solid fa-fire text-danger me-1"></i> Rekomendasi</span>
                            <img src="<?= $foto_src; ?>" class="property-img" alt="<?= $row['nama_kost']; ?>">
                        </div>
                        <div class="property-body">
                            <h3 class="property-title"><?= strtoupper($row['nama_kost']); ?></h3>
                            <div class="property-location">
                                <i class="fa-solid fa-location-dot mt-1 text-danger"></i>
                                <span><?= $row['alamat']; ?></span>
                            </div>
                            
                            <div class="property-footer">
                                <div>
                                    <div class="price-label">Mulai Dari</div>
                                    <div class="price-value"><?= $harga_mulai; ?><span style="font-size: 0.8rem; font-weight: 500; color: #64748B;">/bln</span></div>
                                </div>
                                <div class="text-end">
                                    <div class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                                        <?= $stok_kamar; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                        $counter++;
                    endwhile; 
                else: 
                ?>
                    <div class="col-12 text-center py-5">
                        <i class="fa-solid fa-house-chimney-crack text-muted mb-3" style="font-size: 3rem;"></i>
                        <h4 class="text-muted">Katalog properti sedang disiapkan.</h4>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center mt-5" data-aos="zoom-in">
                <a href="login.php" class="btn btn-dark btn-lg fw-bold px-5 rounded-pill shadow-sm">Lihat Semua Properti <i class="fa-solid fa-arrow-right ms-2"></i></a>
            </div>
        </div>
    </section>

    <section id="fitur" class="section-padding bg-white">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Keunggulan Platform</span>
                <h2 class="section-title">Solusi Terpadu Kebutuhan Hunian</h2>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-door-open"></i></div>
                        <h3 class="feature-title">Eksplorasi Transparan</h3>
                        <p class="feature-desc">Tidak ada harga yang disembunyikan. Semua foto, fasilitas, dan biaya tertera jelas agar Anda bisa membandingkan dengan mudah.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: #ECFDF5; color: #10B981;"><i class="fa-solid fa-wallet"></i></div>
                        <h3 class="feature-title">Pembayaran Instan</h3>
                        <p class="feature-desc">Terintegrasi Payment Gateway modern. Bayar tagihan kapan saja, verifikasi instan tanpa perlu repot kirim bukti transfer ke admin.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: #FFFBEB; color: #F59E0B;"><i class="fa-brands fa-whatsapp"></i></div>
                        <h3 class="feature-title">Pusat Layanan</h3>
                        <p class="feature-desc">Dapatkan notifikasi jatuh tempo otomatis dan kelola keluhan perawatan kamar langsung dari satu dasbor yang komprehensif.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section bg-light-bg">
        <div class="container">
            <div class="cta-box shadow-lg" data-aos="zoom-in-up">
                <h2 class="cta-title">Kamar Terbatas! Jangan Sampai Kehabisan.</h2>
                <p class="cta-desc">Gabung sekarang, jelajahi katalog lengkapnya, dan amankan kamar impianmu hanya dengan beberapa kali klik.</p>
                <a href="login.php" class="btn btn-light btn-lg fw-bold px-5 py-3 rounded-pill" style="color: var(--dark); transition: transform 0.3s; box-shadow: 0 10px 20px rgba(0,0,0,0.2);">
                    Buat Akun
                </a>
            </div>
        </div>
    </section>

    <footer class="footer text-center">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="fw-bold fs-5 text-dark mb-2 mb-md-0">
                    <i class="fa-solid fa-house-chimney-window text-primary me-2"></i> KostAdmin Pro
                </div>
                <p class="footer-text">
                    &copy; <?= date('Y'); ?> KostAdmin System. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        AOS.init({ once: true, offset: 50 });

        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-custom');
            if (window.scrollY > 30) {
                navbar.style.boxShadow = '0 10px 30px rgba(0,0,0,0.05)';
                navbar.style.padding = '10px 0';
            } else {
                navbar.style.boxShadow = 'none';
                navbar.style.padding = '15px 0';
            }
        });
    </script>
</body>
</html>