<?php
session_start();
require 'koneksi.php';
cek_login();

// ========================================================
// 1. LOGIKA STATUS USER (SINKRONISASI MUTASI & KELUAR)
// ========================================================
$username = $_SESSION['username'];

// Ambil data user beserta statusnya di tabel penghuni
$cek_user = mysqli_query($conn, "
    SELECT u.id_penghuni, p.status_penghuni 
    FROM users u 
    LEFT JOIN penghuni p ON u.id_penghuni = p.id 
    WHERE u.username = '$username'
");
$u = mysqli_fetch_assoc($cek_user);

$is_readonly = false;
// Kunci tombol booking jika user sudah aktif menyewa
if (!empty($u['id_penghuni']) && isset($u['status_penghuni']) && $u['status_penghuni'] == 'Aktif') {
    $is_readonly = true;
}

// ========================================================
// 2. PARAMETER PENCARIAN
// ========================================================
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// ========================================================
// 3. QUERY DAFTAR KAMAR TERSEDIA
// ========================================================
$sql = "SELECT kamar.id as id_kamar, kamar.no_kamar, kamar.harga_perbulan, kamar.fasilitas_kamar, 
               kost.id as id_kost, kost.nama_kost, kost.alamat, kost.foto_bangunan, kost.fasilitas_umum 
        FROM kamar 
        JOIN kost ON kamar.id_kost = kost.id 
        WHERE kamar.status = 'Tersedia' ";

if (!empty($search_query)) {
    $sql .= " AND (kost.nama_kost LIKE '%$search_query%' OR kost.alamat LIKE '%$search_query%') ";
}
$sql .= " ORDER BY kost.nama_kost ASC, kamar.no_kamar ASC";

$result = mysqli_query($conn, $sql);

// ========================================================
// 4. KELOMPOKKAN DATA BERDASARKAN GEDUNG KOST
// ========================================================
$daftar_kost = [];
while($row = mysqli_fetch_assoc($result)) {
    $id_k = $row['id_kost'];
    if(!isset($daftar_kost[$id_k])) {
        $daftar_kost[$id_k] = [
            'nama_kost' => $row['nama_kost'],
            'alamat' => $row['alamat'],
            'foto_bangunan' => $row['foto_bangunan'],
            'fasilitas_umum' => $row['fasilitas_umum'],
            'kamar' => [] 
        ];
    }
    $daftar_kost[$id_k]['kamar'][] = [
        'id_kamar' => $row['id_kamar'],
        'no_kamar' => $row['no_kamar'],
        'harga_perbulan' => $row['harga_perbulan'],
        'fasilitas_kamar' => $row['fasilitas_kamar']
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eksplorasi Properti | KostAdmin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --primary: #4F46E5; 
            --primary-hover: #3730A3;
            --bg: #F4F7F9; 
            --text-main: #0F172A;
            --text-muted: #64748B;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        
        /* --- Hero Section --- */
        .hero-section {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            padding: 80px 0 100px 0;
            position: relative;
            overflow: hidden;
            margin-bottom: -50px;
        }
        .hero-section::after {
            content: ''; position: absolute; top: 0; right: 0; bottom: 0; left: 0;
            background: url('https://www.transparenttextures.com/patterns/cubes.png');
            opacity: 0.05; pointer-events: none;
        }
        .hero-title { font-weight: 800; font-size: 2.8rem; color: white; letter-spacing: -1px; margin-bottom: 15px; }
        .hero-subtitle { color: #94A3B8; font-size: 1.1rem; font-weight: 500; max-width: 600px; margin: 0 auto; }

        /* --- Search Bar --- */
        .search-wrapper { max-width: 850px; margin: 0 auto; position: relative; z-index: 10; }
        .search-box {
            background: white; border-radius: 20px; padding: 12px 12px 12px 25px;
            display: flex; align-items: center; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.15);
            transition: 0.3s;
        }
        .search-box:focus-within { box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.25); transform: translateY(-2px); }
        .search-input { border: none; outline: none; width: 100%; font-size: 1.05rem; font-weight: 500; color: var(--text-main); }
        .btn-search { background: var(--primary); color: white; border-radius: 14px; padding: 16px 35px; font-weight: 700; border: none; transition: 0.2s; }

        /* --- Alerts & Badges --- */
        .readonly-alert {
            background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 16px; padding: 20px 25px; margin: 0 auto 40px auto; max-width: 850px;
            display: flex; align-items: center; gap: 20px; backdrop-filter: blur(10px);
        }
        .readonly-icon { width: 50px; height: 50px; background: #FFFBEB; color: #F59E0B; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        /* --- Property Card --- */
        .property-card {
            background: white; border-radius: 24px; border: none;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
            margin-bottom: 30px; transition: 0.3s ease; overflow: hidden;
        }
        .prop-img-container { position: relative; height: 100%; min-height: 280px; }
        .prop-img { width: 100%; height: 100%; object-fit: cover; }
        .badge-glass { position: absolute; top: 20px; left: 20px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(8px); padding: 8px 16px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; }
        .badge-stock { position: absolute; bottom: 20px; right: 20px; background: var(--primary); color: white; padding: 8px 16px; border-radius: 12px; font-size: 0.8rem; font-weight: 800; }
        .prop-body { padding: 35px; display: flex; flex-direction: column; height: 100%; }
        .btn-outline-premium { background: white; border: 2px solid #E2E8F0; font-weight: 700; padding: 14px 20px; border-radius: 16px; flex: 1; transition: 0.2s; }
        .btn-outline-premium:hover { border-color: var(--primary); color: var(--primary); }

        /* --- Room Section --- */
        .room-section { background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 40px; }
        .room-card { background: white; border: 1px solid #E2E8F0; border-radius: 20px; padding: 25px; display: flex; flex-direction: column; height: 100%; }
        .room-price { font-size: 1.8rem; font-weight: 800; color: var(--text-main); }
        .btn-book-premium { background: var(--text-main); color: white; font-weight: 700; padding: 14px; border-radius: 14px; border: none; width: 100%; }
        .btn-book-premium:hover { background: var(--primary); }

        /* --- Custom SweetAlert --- */
        .swal-custom-zindex { z-index: 9999 !important; }
        .swal2-backdrop-show { backdrop-filter: blur(4px); }
        body.swal2-shown { overflow: hidden !important; }

        .filter-chips { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
        .chip { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 6px 16px; border-radius: 50px; font-size: 0.8rem; cursor: pointer; }
    </style>
</head>
<body>
    
    <?php include 'navbar.php'; ?>

    <div class="hero-section">
        <div class="container text-center position-relative z-1">
            <h1 class="hero-title">Temukan Kenyamanan Sempurna.</h1>
            <p class="hero-subtitle">Jelajahi katalog properti eksklusif kami. Lokasi strategis, fasilitas lengkap, dan proses booking instan.</p>
            
            <div class="filter-chips">
                <span class="chip"><i class="fa-solid fa-wifi me-1"></i> Free Wi-Fi</span>
                <span class="chip"><i class="fa-solid fa-wind me-1"></i> Full AC</span>
                <span class="chip"><i class="fa-solid fa-shield-halved me-1"></i> Keamanan 24 Jam</span>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        
        <div class="search-wrapper mb-5">
            <form method="GET" action="">
                <div class="search-box">
                    <i class="fa-solid fa-location-crosshairs text-primary fs-5 ms-2 me-3"></i>
                    <input type="text" name="search" class="search-input" placeholder="Cari berdasarkan nama gedung atau alamat..." value="<?= htmlspecialchars($search_query); ?>">
                    <button type="submit" class="btn-search">Temukan Unit</button>
                </div>
            </form>
        </div>

        <?php if($is_readonly) : ?>
            <div class="readonly-alert shadow-sm">
                <div class="readonly-icon"><i class="fa-solid fa-glasses"></i></div>
                <div>
                    <h5 class="fw-bold text-dark mb-1">Mode Eksplorasi Aktif</h5>
                    <p class="mb-0 text-muted small">Anda sedang memiliki sewa aktif. Untuk pindah kamar, silakan hubungi Admin atau cek Dashboard Anda.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if(empty($daftar_kost)) : ?>
                    <div class="text-center py-5 mt-4" style="background: white; border-radius: 24px; border: 2px dashed #E2E8F0;">
                        <i class="fa-solid fa-building-circle-xmark mb-4" style="font-size: 5rem; color: #CBD5E1;"></i>
                        <h3 class="fw-bold text-dark">Properti Tidak Ditemukan</h3>
                        <p class="text-muted">Maaf, kami tidak menemukan properti yang sesuai.</p>
                        <a href="pilih_kamar.php" class="btn btn-outline-dark rounded-pill px-4">Tampilkan Semua</a>
                    </div>
                <?php else : ?>
                    
                    <?php 
                    $counter = 0;
                    $dummy_img = ["1522708323590-d24dbb6b0267", "1493809842364-78817add7ffb", "1502672260266-1c1ef2d93688"];
                    foreach($daftar_kost as $id_kost => $kost) : 
                        $stok = count($kost['kamar']);
                        $foto_id = $dummy_img[$counter % count($dummy_img)];
                        $foto_src = (!empty($kost['foto_bangunan']) && file_exists('uploads/kost/' . $kost['foto_bangunan'])) 
                                    ? 'uploads/kost/'.$kost['foto_bangunan'] 
                                    : "https://images.unsplash.com/photo-{$foto_id}?auto=format&fit=crop&w=800&q=80";
                        $counter++;
                    ?>
                    
                    <div class="property-card">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <div class="prop-img-container">
                                    <span class="badge-glass">Rekomendasi</span>
                                    <img src="<?= $foto_src; ?>" class="prop-img" alt="Gedung">
                                    <span class="badge-stock"><?= $stok; ?> Unit Tersedia</span>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="prop-body">
                                    <h2 class="fw-bold"><?= strtoupper($kost['nama_kost']); ?></h2>
                                    <p class="text-muted mb-4"><i class="fa-solid fa-location-dot text-danger me-2"></i><?= $kost['alamat']; ?></p>
                                    
                                    <div class="d-flex gap-2 mt-auto">
                                        <button class="btn-outline-premium" data-bs-toggle="modal" data-bs-target="#infoModal<?= $id_kost; ?>">Info Gedung</button>
                                        <button class="btn-outline-premium" data-bs-toggle="collapse" data-bs-target="#kamarKost<?= $id_kost; ?>">Lihat Kamar</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="collapse" id="kamarKost<?= $id_kost; ?>">
                            <div class="room-section">
                                <div class="row g-4">
                                    <?php foreach($kost['kamar'] as $kmr) : ?>
                                    <div class="col-md-4">
                                        <div class="room-card">
                                            <div class="d-flex justify-content-between mb-3">
                                                <span class="fw-bold">Kamar <?= $kmr['no_kamar']; ?></span>
                                                <i class="fa-regular fa-heart text-muted"></i>
                                            </div>
                                            <div class="room-price">Rp <?= number_format($kmr['harga_perbulan'], 0, ',', '.'); ?></div>
                                            <div class="text-muted small mb-3">per bulan</div>
                                            
                                            <div class="flex-grow-1">
                                                <?php foreach(explode(',', $kmr['fasilitas_kamar']) as $f) : ?>
                                                    <div class="small mb-1"><i class="fa-solid fa-check text-success me-2"></i><?= trim($f); ?></div>
                                                <?php endforeach; ?>
                                            </div>

                                            <?php if($is_readonly) : ?>
                                                <button class="btn btn-light w-100 mt-3" disabled>Sewa Aktif</button>
                                            <?php else : ?>
                                                <button onclick="konfirmasiBooking(<?= $kmr['id_kamar']; ?>, '<?= addslashes($kost['nama_kost']); ?>', '<?= $kmr['no_kamar']; ?>')" class="btn-book-premium mt-3">Booking</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="infoModal<?= $id_kost; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content rounded-4 border-0">
                                <div class="modal-body p-4">
                                    <h5 class="fw-bold"><?= $kost['nama_kost']; ?></h5>
                                    <hr>
                                    <p class="small text-muted"><?= $kost['alamat']; ?></p>
                                    <h6 class="fw-bold mt-3">Fasilitas Umum:</h6>
                                    <div class="row">
                                        <?php foreach(explode(',', $kost['fasilitas_umum']) as $fu) : ?>
                                            <div class="col-6 small mb-2"><i class="fa-solid fa-circle-check text-primary me-2"></i><?= trim($fu); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="btn btn-dark w-100 mt-4 rounded-3" data-bs-dismiss="modal">Tutup</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function konfirmasiBooking(idKamar, namaKost, noKamar) {
        Swal.fire({
            title: 'Konfirmasi Reservasi',
            html: `
                <div class="text-start mt-3 p-4" style="background: #FFFFFF; border-radius: 20px; border: 1.5px solid #F1F5F9;">
                    <div class="d-flex align-items-center mb-3">
                        <div style="width: 40px; height: 40px; background: #EEF2FF; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #4F46E5;" class="me-3">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold">PROPERTI</div>
                            <div class="text-dark fw-bold">${namaKost}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; background: #F0FDF4; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #16A34A;" class="me-3">
                            <i class="fa-solid fa-door-open"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold">UNIT</div>
                            <div class="text-primary fw-bold">Kamar ${noKamar}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 p-3 rounded-4 bg-light text-start small">
                    <i class="fa-solid fa-circle-info text-warning me-1"></i> Batas waktu pembayaran adalah <b>24 Jam</b> setelah reservasi dibuat.
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0F172A',
            cancelButtonColor: '#FFFFFF',
            confirmButtonText: 'Lanjutkan',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                container: 'swal-custom-zindex',
                popup: 'rounded-5 border-0',
                confirmButton: 'rounded-4 px-4 py-3 fw-bold shadow-sm',
                cancelButton: 'rounded-4 px-4 py-3 fw-bold text-dark border'
            },
            didOpen: () => { document.body.style.paddingRight = '0px'; }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `proses_booking.php?id_kamar=${idKamar}`;
            }
        });
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>