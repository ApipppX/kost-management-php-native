<?php
session_start();
require 'koneksi.php';
cek_login();
proteksi_admin();

// Query SQL
$query = "SELECT 
            kost.*, 
            COUNT(kamar.id) as kapasitas_total,
            SUM(CASE WHEN kamar.status = 'Tersedia' THEN 1 ELSE 0 END) as kamar_ready
          FROM kost 
          LEFT JOIN kamar ON kost.id = kamar.id_kost
          GROUP BY kost.id 
          ORDER BY kost.nama_kost ASC";

$result = mysqli_query($conn, $query);

// Statistik Global
$total_gedung = mysqli_num_rows($result);
$global_ready = 0;
$global_total = 0;

mysqli_data_seek($result, 0);
while($st = mysqli_fetch_assoc($result)) {
    $global_ready += (int)$st['kamar_ready'];
    $global_total += (int)$st['kapasitas_total'];
}
mysqli_data_seek($result, 0);

// Kalkulasi Okupansi Global
$global_terisi = $global_total - $global_ready;
$persen_okupansi = ($global_total > 0) ? round(($global_terisi / $global_total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Properti | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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

        /* --- BRAND HEADER --- */
        .page-brand-logo { width: 45px; height: 45px; background: var(--primary); color: white; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 12px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        .page-title { font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; margin-bottom: 0; }
        .page-subtitle { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin-left: 57px; }
        .gap-section { margin-bottom: 32px; }

        .btn-add-property { background: var(--primary); color: white; padding: 12px 24px; border-radius: 50px; font-weight: 700; text-decoration: none; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.2); font-size: 0.95rem; }
        .btn-add-property:hover { background: var(--primary-hover); color: white; transform: translateY(-2px); }

        /* --- STATISTIC CARDS --- */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
        .stat-card-info { background: var(--surface); border-radius: 24px; border: 1px solid var(--border-light); padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; align-items: flex-start; gap: 16px; transition: 0.3s; }
        .stat-card-info:hover { transform: translateY(-4px); box-shadow: 0 12px 25px -5px rgba(0,0,0,0.05); border-color: #cbd5e1; }
        
        .ic-box { width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .ic-primary { background: #EEF2FF; color: var(--primary); }
        .ic-success { background: #ECFDF5; color: var(--success); }
        .ic-dark { background: #F1F5F9; color: var(--text-main); }
        
        .stat-text { width: 100%; }
        .stat-text p { margin: 0 0 6px 0; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-text h3 { margin: 0; font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; color: var(--text-main); line-height: 1; }
        
        .progress-stat { height: 6px; border-radius: 10px; background: #F1F5F9; margin-top: 12px; margin-bottom: 8px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 10px; transition: width 1s ease; }
        .stat-footer-text { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); display: flex; justify-content: space-between; }

        /* --- LISTING ROW --- */
        .listing-header { padding: 0 30px 15px; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .kost-item { background: var(--surface); border-radius: 20px; margin-bottom: 16px; padding: 20px 30px; border: 1px solid var(--border-light); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); transition: 0.3s ease; display: flex; align-items: center; }
        .kost-item:hover { transform: translateY(-3px); border-color: var(--primary); box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.1); }
        
        .img-kost { width: 100px; height: 75px; border-radius: 14px; object-fit: cover; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        
        .col-main { flex-grow: 1; padding-left: 25px; }
        .branch-badge { background: var(--text-main); color: white; font-size: 0.65rem; font-weight: 800; padding: 4px 8px; border-radius: 8px; letter-spacing: 0.5px; }
        .col-main h6 { font-weight: 800; margin: 0; color: var(--text-main); font-size: 1.05rem; }
        .col-main p { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0; margin-top: 4px; font-weight: 500; }
        
        .col-occ { width: 180px; text-align: center; border-left: 1px solid var(--border-light); border-right: 1px solid var(--border-light); margin: 0 25px; padding: 0 15px; }
        .col-occ .num { font-weight: 800; font-size: 1.15rem; display: block; color: var(--text-main); }
        .col-occ .label { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

        .col-contact { width: 170px; }
        
        .action-group { display: flex; gap: 8px; }
        .btn-icon { width: 40px; height: 40px; border-radius: 12px; border: 1px solid var(--border-light); background: #F8FAFC; color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: 0.2s; cursor: pointer; text-decoration: none; font-size: 0.95rem; }
        .btn-icon:hover { background: white; border-color: var(--primary); color: var(--primary); box-shadow: 0 4px 10px rgba(0,0,0,0.05); transform: translateY(-2px); }
        .btn-icon.delete:hover { border-color: var(--danger); color: var(--danger); }
        
        .empty-state { text-align: center; padding: 60px 20px; background: var(--surface); border-radius: 24px; border: 1px dashed var(--border-light); }
    </style>
</head>
<body>
    
    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-section">
            <div class="d-flex align-items-center">
                <div class="page-brand-logo"><i class="fa-solid fa-city"></i></div>
                <div>
                    <h1 class="page-title">Properti Cabang</h1>
                    <p class="page-subtitle m-0">Pantau performa dan ketersediaan unit di seluruh cabang kost.</p>
                </div>
            </div>
            
            <div class="mt-3 mt-md-0">
                <a href="tambah_aset.php" class="btn-add-property">
                    <i class="fa-solid fa-plus-circle"></i> Tambah Properti
                </a>
            </div>
        </div>

        <div class="stat-grid gap-section">
            
            <div class="stat-card-info">
                <div class="ic-box ic-primary"><i class="fa-solid fa-building"></i></div>
                <div class="stat-text">
                    <p>Total Cabang Aktif</p>
                    <h3><?= $total_gedung; ?> <span style="font-size: 1rem; color: #94A3B8; font-weight:600;">Gedung</span></h3>
                    <div class="progress-stat">
                        <div class="progress-bar-fill bg-primary" style="width: 100%"></div>
                    </div>
                    <div class="stat-footer-text">
                        <span class="text-primary"><i class="fa-solid fa-check-circle me-1"></i> Sistem Normal</span>
                    </div>
                </div>
            </div>

            <div class="stat-card-info">
                <div class="ic-box ic-success"><i class="fa-solid fa-door-open"></i></div>
                <div class="stat-text">
                    <p>Stok Kamar Ready</p>
                    <h3><?= $global_ready; ?> <span style="font-size: 1rem; color: #94A3B8; font-weight:600;">/ <?= $global_total; ?></span></h3>
                    <?php $persen_ready = ($global_total > 0) ? ($global_ready / $global_total) * 100 : 0; ?>
                    <div class="progress-stat">
                        <div class="progress-bar-fill bg-success" style="width: <?= $persen_ready; ?>%"></div>
                    </div>
                    <div class="stat-footer-text">
                        <span><?= round($persen_ready); ?>% Kosong</span>
                        <span class="text-success"><i class="fa-solid fa-arrow-trend-up"></i> Disewakan</span>
                    </div>
                </div>
            </div>

            <div class="stat-card-info">
                <div class="ic-box <?= ($persen_okupansi >= 80) ? 'ic-success' : 'ic-dark'; ?>"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="stat-text">
                    <p>Tingkat Okupansi</p>
                    <h3><?= $persen_okupansi; ?>%</h3>
                    <div class="progress-stat">
                        <div class="progress-bar-fill <?= ($persen_okupansi >= 80) ? 'bg-success' : 'bg-dark'; ?>" style="width: <?= $persen_okupansi; ?>%"></div>
                    </div>
                    <div class="stat-footer-text">
                        <?php if($persen_okupansi >= 80): ?>
                            <span class="text-success"><i class="fa-solid fa-fire me-1"></i> Performa Baik!</span>
                        <?php else: ?>
                            <span class="text-muted"><i class="fa-solid fa-bullhorn me-1"></i> Perlu Pemasaran</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </div>

        <div class="listing-header d-none d-lg-flex">
            <div style="width: 100px;">Visual</div>
            <div class="flex-grow-1" style="padding-left: 25px;">Nama & Alamat Gedung</div>
            <div style="width: 180px; text-align: center; margin: 0 25px;">Status Unit</div>
            <div style="width: 170px;">Kontak Pengelola</div>
            <div style="width: 98px; text-align: center;">Aksi</div>
        </div>

        <?php 
        $counter = 0;
        while($row = mysqli_fetch_assoc($result)) : 
            $ready = (int)$row['kamar_ready']; 
            $total = (int)$row['kapasitas_total'];
            $terisi = $total - $ready;
            
            // Foto Dummy
            $dummy_url = "https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?q=80&w=200&auto=format&fit=crop";
            if($counter % 2 == 0) $dummy_url = "https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?q=80&w=200&auto=format&fit=crop";
            
            $src = (!empty($row['foto_bangunan']) && file_exists('uploads/kost/' . $row['foto_bangunan'])) 
                   ? 'uploads/kost/'.$row['foto_bangunan'] 
                   : $dummy_url;
            $counter++;
        ?>
        <div class="kost-item">
            <img src="<?= $src; ?>" class="img-kost" alt="Kost">

            <div class="col-main">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="branch-badge">
                        <?= $row['kode_cabang'] ?? 'KST'; ?>
                    </span>
                    <h6><?= strtoupper($row["nama_kost"]); ?></h6>
                </div>
                <p><i class="fa-solid fa-location-dot text-danger me-1 opacity-75"></i> <?= $row["alamat"]; ?></p>
            </div>

            <div class="col-occ d-none d-lg-block">
                <span class="num <?= ($ready == 0 && $total > 0) ? 'text-danger' : ''; ?>"><?= $terisi; ?> / <?= $total; ?></span>
                <span class="label">Unit Terisi</span>
                <div class="progress-stat mx-auto mt-2" style="height: 5px; width: 80px; margin-bottom:0;">
                    <div class="progress-bar-fill <?= ($ready == 0 && $total > 0) ? 'bg-danger' : 'bg-primary'; ?>" style="width: <?= ($total > 0) ? ($terisi/$total)*100 : 0; ?>%"></div>
                </div>
            </div>

            <div class="col-contact d-none d-md-block">
                <?php 
                    $no_hp_kost = $row['no_telpon'] ?? ''; 
                    $wa_link = !empty($no_hp_kost) ? "https://wa.me/" . preg_replace('/^0/', '62', $no_hp_kost) : "#";
                ?>
                <a href="<?= $wa_link; ?>" target="<?= !empty($no_hp_kost) ? '_blank' : '_self'; ?>" class="text-success text-decoration-none fw-bold" style="font-size: 0.85rem;">
                    <i class="fa-brands fa-whatsapp fs-5 me-1"></i> <?= !empty($no_hp_kost) ? $no_hp_kost : 'Belum diatur'; ?>
                </a>
                <div class="text-muted mt-1" style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">WhatsApp Admin</div>
            </div>
            
            <div class="action-group">
                <a href="edit_kost.php?id=<?= $row['id']; ?>" class="btn-icon" title="Edit Properti">
                    <i class="fa-solid fa-pen-nib"></i>
                </a>
                <a href="hapus_kost.php?id=<?= $row['id']; ?>" class="btn-icon delete" onclick="return confirm('Yakin ingin menghapus properti ini? Semua kamar terkait mungkin akan terpengaruh.')" title="Hapus">
                    <i class="fa-solid fa-trash-can"></i>
                </a>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if(mysqli_num_rows($result) == 0) : ?>
            <div class="empty-state">
                <i class="fa-solid fa-city fs-1 text-muted opacity-25 mb-3"></i>
                <h5 class="fw-bold text-dark mb-1">Belum Ada Properti</h5>
                <p class="text-muted small mb-0">Silakan tambahkan cabang properti/gedung pertama Anda.</p>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>