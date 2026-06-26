<?php
session_start();
require 'koneksi.php';
cek_login();

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Search Logic
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// 1. STATISTIK REAL-TIME (INFORMATIF)
$total_kamar = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kamar"));
$kamar_tersedia = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kamar WHERE status = 'Tersedia'"));
$kamar_terisi = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kamar WHERE status = 'Terisi'"));

// Persentase Okupansi
$persen_isi = ($total_kamar > 0) ? round(($kamar_terisi / $total_kamar) * 100) : 0;

// 2. QUERY UTAMA DENGAN SEARCH & JOIN
$query = "SELECT kamar.*, kost.nama_kost, kost.kode_cabang 
          FROM kamar 
          JOIN kost ON kamar.id_kost = kost.id
          WHERE kamar.no_kamar LIKE '%$search%' OR kost.nama_kost LIKE '%$search%'
          ORDER BY kost.nama_kost ASC, kamar.no_kamar ASC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaris Kamar | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root { 
            --primary: #4F46E5; --primary-hover: #4338CA;
            --success: #10B981; --danger: #EF4444; 
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

        /* --- STATISTIC CARDS (KONSISTEN) --- */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; }
        .stat-card-info { background: var(--surface); border-radius: 24px; border: 1px solid var(--border-light); padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; align-items: flex-start; gap: 16px; transition: 0.3s; }
        .stat-card-info:hover { transform: translateY(-4px); box-shadow: 0 12px 25px -5px rgba(0,0,0,0.05); }
        
        .ic-box { width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .ic-primary { background: #EEF2FF; color: var(--primary); }
        .ic-success { background: #ECFDF5; color: var(--success); }
        .ic-danger { background: #FEF2F2; color: var(--danger); }
        
        .stat-text p { margin: 0 0 4px 0; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-text h3 { margin: 0; font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; color: var(--text-main); line-height: 1; }
        
        .rate-bar-bg { width: 100%; height: 6px; background: #F1F5F9; border-radius: 10px; margin-top: 12px; overflow: hidden; }
        .rate-bar-fill { height: 100%; background: var(--primary); border-radius: 10px; transition: width 1s ease; }

        /* --- FILTER BAR --- */
        .unified-filter { background: var(--surface); border: 1px solid var(--border-light); border-radius: 100px; padding: 8px; display: flex; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); width: fit-content; transition: 0.3s; }
        .unified-filter:focus-within { border-color: var(--primary); box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.1); }
        .uf-search { display: flex; align-items: center; padding: 0 20px; min-width: 300px; }
        .uf-search i { color: #94A3B8; }
        .uf-search input { border: none; background: transparent; padding: 10px 15px; font-size: 0.95rem; font-weight: 500; outline: none; width: 100%; }
        .uf-btn { background: var(--text-main); color: white; border: none; border-radius: 50px; padding: 12px 28px; font-weight: 700; font-size: 0.9rem; transition: 0.2s; margin-left: 8px; }
        .uf-btn:hover { background: var(--primary); }

        .btn-add-room { background: var(--primary); color: white; padding: 12px 24px; border-radius: 50px; font-weight: 700; text-decoration: none; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.2); }
        .btn-add-room:hover { background: var(--primary-hover); color: white; transform: translateY(-2px); }

        /* --- TABLE STYLING --- */
        .table-card { background: var(--surface); border-radius: 24px; border: 1px solid var(--border-light); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); overflow: hidden; }
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table thead th { background: #F8FAFC; color: #64748B; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 20px 24px; border-bottom: 1px solid var(--border-light); }
        .custom-table td { padding: 20px 24px; vertical-align: middle; border-bottom: 1px solid #F1F5F9; }

        .room-visual { width: 45px; height: 45px; border-radius: 12px; background: #EEF2FF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .room-name { font-weight: 800; color: var(--text-main); font-size: 1rem; margin-bottom: 2px; }
        .room-prop { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }

        .unit-pill { display: inline-flex; background: #F1F5F9; color: var(--text-main); padding: 5px 12px; border-radius: 8px; font-weight: 800; font-size: 0.8rem; border: 1px solid var(--border-light); }
        .price-text { font-weight: 800; color: var(--text-main); font-size: 1.05rem; letter-spacing: -0.5px; }

        .status-pill { padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .pill-ready { background: #ECFDF5; color: var(--success); border: 1px solid #A7F3D0; }
        .pill-occupied { background: #FEF2F2; color: var(--danger); border: 1px solid #FECACA; }

        .action-group { display: flex; gap: 8px; justify-content: center; }
        .btn-icon { width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border-light); background: #F8FAFC; color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: 0.2s; cursor: pointer; text-decoration: none; }
        .btn-icon:hover { background: white; border-color: var(--primary); color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .btn-icon.delete:hover { border-color: var(--danger); color: var(--danger); }
        
        .empty-state { text-align: center; padding: 80px 20px; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-section">
            <div class="d-flex align-items-center">
                <div class="page-brand-logo"><i class="fa-solid fa-bed"></i></div>
                <div>
                    <h1 class="page-title">Inventaris Unit</h1>
                    <p class="page-subtitle m-0">Kelola harga, fasilitas, dan pantau ketersediaan unit kamar.</p>
                </div>
            </div>
            
            <?php if($role == 'admin') : ?>
            <div class="mt-3 mt-md-0">
                <a href="tambah_aset.php" class="btn-add-room">
                    <i class="fa-solid fa-plus-circle"></i> Tambah Unit Baru
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="stat-grid gap-section">
            <div class="stat-card-info">
                <div class="ic-box ic-primary"><i class="fa-solid fa-layer-group"></i></div>
                <div class="stat-text w-100">
                    <p>Total Unit</p>
                    <h3><?= $total_kamar; ?> <span style="font-size: 1rem; color: #94A3B8; font-weight:600;">Kamar</span></h3>
                    <div class="stat-desc text-muted fw-bold small mt-2">Kapasitas Maksimal</div>
                </div>
            </div>

            <div class="stat-card-info">
                <div class="ic-box ic-success"><i class="fa-solid fa-door-open"></i></div>
                <div class="stat-text w-100">
                    <p>Unit Tersedia</p>
                    <h3><?= $kamar_tersedia; ?> <span style="font-size: 1rem; color: #94A3B8; font-weight:600;">Kosong</span></h3>
                    <div class="stat-desc text-success fw-bold small mt-2"><i class="fa-solid fa-circle-check"></i> Siap Huni</div>
                </div>
            </div>

            <div class="stat-card-info">
                <div class="ic-box ic-danger"><i class="fa-solid fa-user-lock"></i></div>
                <div class="stat-text w-100">
                    <p>Tingkat Okupansi</p>
                    <h3><?= $persen_isi; ?>%</h3>
                    <div class="rate-bar-bg"><div class="rate-bar-fill" style="width: <?= $persen_isi; ?>%;"></div></div>
                </div>
            </div>
        </div>

        <div class="gap-section">
            <form method="GET" action="" class="d-flex flex-wrap align-items-center">
                <div class="unified-filter mb-0">
                    <div class="uf-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Cari No. Kamar atau Gedung..." value="<?= htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="uf-btn">Filter</button>
                </div>
                <?php if(!empty($search)) : ?>
                    <a href="kamar.php" class="ms-3 text-muted fw-bold text-decoration-none small"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th class="ps-4">Informasi Unit & Gedung</th>
                            <th>No. Kamar</th>
                            <th>Harga Perbulan</th>
                            <th class="text-center">Ketersediaan</th>
                            <?php if($role == 'admin') : ?>
                            <th class="text-center pe-4">Manajemen</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result && mysqli_num_rows($result) > 0) : ?>
                            <?php while($row = mysqli_fetch_assoc($result)) : ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="room-visual shadow-sm"><i class="fa-solid fa-door-closed"></i></div>
                                        <div>
                                            <div class="room-name"><?= strtoupper($row['nama_kost']); ?></div>
                                            <div class="room-prop">
                                                <span class="badge bg-light text-muted border"><?= $row['kode_cabang'] ?? 'BRCH'; ?></span>
                                                <span class="ms-2 small"><i class="fa-solid fa-cube me-1"></i> <?= empty($row['fasilitas_kamar']) ? 'Fasilitas Standar' : $row['fasilitas_kamar']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="unit-pill">UNIT-<?= $row['no_kamar']; ?></span>
                                </td>
                                <td>
                                    <div class="price-text">Rp <?= number_format($row['harga_perbulan'], 0, ',', '.'); ?></div>
                                    <div class="small text-muted fw-bold" style="font-size: 0.65rem;">NETTER PRICE</div>
                                </td>
                                <td class="text-center">
                                    <?php if($row['status'] == 'Tersedia') : ?>
                                        <span class="status-pill pill-ready"><i class="fa-solid fa-circle-dot"></i> TERSEDIA</span>
                                    <?php else : ?>
                                        <span class="status-pill pill-occupied"><i class="fa-solid fa-circle-dot"></i> TERISI</span>
                                    <?php endif; ?>
                                </td>
                                <?php if($role == 'admin') : ?>
                                <td class="text-center pe-4">
                                    <div class="action-group">
                                        <a href="edit_kamar.php?id=<?= $row['id']; ?>" class="btn-icon" title="Edit Unit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <a href="hapus_kamar.php?id=<?= $row['id']; ?>" class="btn-icon delete" title="Hapus" onclick="return confirm('Hapus unit ini selamanya?')"><i class="fa-solid fa-trash-can"></i></a>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fa-solid fa-couch mb-3" style="font-size: 3.5rem; color: #CBD5E1;"></i>
                                    <h5 class="fw-bold text-dark mb-1">Unit Tidak Ditemukan</h5>
                                    <p class="text-muted small">Belum ada unit yang didaftarkan atau filter tidak cocok.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>