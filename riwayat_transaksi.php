<?php
session_start();
require 'koneksi.php';

// Pastikan zona waktu sinkron dengan Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

cek_login();

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// ==========================================
// 1. FILTER & PENCARIAN
// ==========================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : '';

// ==========================================
// 2. LOGIKA DATA & STATISTIK BERDASARKAN ROLE
// ==========================================
$total_volume = 0;
$total_transaksi = 0;

if ($role == 'admin') {
    // Statistik Admin (Global)
    $stat_query = mysqli_query($conn, "SELECT SUM(jumlah_bayar) as volume, COUNT(id) as trx FROM pembayaran");
    if($stat = mysqli_fetch_assoc($stat_query)) {
        $total_volume = $stat['volume'] ?? 0;
        $total_transaksi = $stat['trx'] ?? 0;
    }

    // PERBAIKAN: Menambahkan t.created_at AS waktu_transaksi untuk menarik jam akurat
    $query_str = "SELECT p.*, t.jumlah AS jml_tagihan, t.created_at AS waktu_transaksi, pen.nama_penghuni, k.nama_kost, k.kode_cabang
                  FROM pembayaran p
                  JOIN tagihan t ON p.no_invoice = t.no_invoice
                  JOIN penghuni pen ON t.id_penghuni = pen.id
                  JOIN kamar kam ON pen.id_kamar = kam.id
                  JOIN kost k ON kam.id_kost = k.id
                  WHERE (p.no_invoice LIKE '%$search%' OR pen.nama_penghuni LIKE '%$search%')";
    
    if(!empty($filter_bulan)) $query_str .= " AND DATE_FORMAT(p.tanggal_bayar, '%Y-%m') = '$filter_bulan'";
    $query_str .= " ORDER BY t.created_at DESC";

} else {
    // Logika User (Personal)
    $user_res = mysqli_query($conn, "SELECT id_penghuni FROM users WHERE username = '$username'");
    $user_data = mysqli_fetch_assoc($user_res);
    $id_p = $user_data['id_penghuni'];

    if ($id_p) {
        // Statistik User
        $stat_query = mysqli_query($conn, "SELECT SUM(p.jumlah_bayar) as volume, COUNT(p.id) as trx 
                                           FROM pembayaran p JOIN tagihan t ON p.no_invoice = t.no_invoice 
                                           WHERE t.id_penghuni = '$id_p'");
        if($stat = mysqli_fetch_assoc($stat_query)) {
            $total_volume = $stat['volume'] ?? 0;
            $total_transaksi = $stat['trx'] ?? 0;
        }

        // PERBAIKAN: Menambahkan t.created_at AS waktu_transaksi untuk menarik jam akurat
        $query_str = "SELECT p.*, t.jumlah AS jml_tagihan, t.created_at AS waktu_transaksi, pen.nama_penghuni, k.nama_kost, k.kode_cabang
                      FROM pembayaran p
                      JOIN tagihan t ON p.no_invoice = t.no_invoice
                      JOIN penghuni pen ON t.id_penghuni = pen.id
                      JOIN kamar kam ON pen.id_kamar = kam.id
                      JOIN kost k ON kam.id_kost = k.id
                      WHERE t.id_penghuni = '$id_p' AND p.no_invoice LIKE '%$search%'";
        
        if(!empty($filter_bulan)) $query_str .= " AND DATE_FORMAT(p.tanggal_bayar, '%Y-%m') = '$filter_bulan'";
        $query_str .= " ORDER BY t.created_at DESC";
    } else {
        $query_str = "SELECT * FROM pembayaran WHERE id = -1"; 
    }
}

$result = mysqli_query($conn, $query_str);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pembayaran | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root { 
            --primary: #4F46E5; 
            --primary-hover: #4338CA;
            --bg: #F8FAFC; 
            --surface: #ffffff;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border-light: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        
        .container-app { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        .page-brand-logo { width: 45px; height: 45px; background: var(--primary); color: white; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 12px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        .page-title { font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; margin-bottom: 0; color: var(--text-main); }
        .page-subtitle { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin-left: 57px; }
        .gap-section { margin-bottom: 32px; }
        
        .btn-print-lite { background: white; color: var(--text-main); border: 1px solid var(--border-light); border-radius: 10px; padding: 10px 20px; font-weight: 700; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-print-lite:hover { background: #F1F5F9; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }

        /* --- Analytics Mini Dashboard --- */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: var(--surface); border-radius: 20px; padding: 24px;
            border: 1px solid var(--border-light);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            display: flex; align-items: center; gap: 20px; transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border-color: #cbd5e1; }
        .stat-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .icon-wallet { background: #EEF2FF; color: #4F46E5; }
        .icon-receipt { background: #F0FDF4; color: #10B981; }
        
        .stat-info p { margin: 0; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
        .stat-info h3 { margin: 4px 0 0 0; font-weight: 800; font-size: 1.6rem; color: var(--text-main); letter-spacing: -0.5px; }

        /* --- Smart Filter Toolbar --- */
        .filter-toolbar {
            background: var(--surface); border-radius: 20px; padding: 16px;
            border: 1px solid var(--border-light); box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 30px;
        }
        .filter-search { flex-grow: 1; position: relative; min-width: 250px; }
        .filter-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8; }
        .filter-search input { width: 100%; border: 1px solid var(--border-light); border-radius: 14px; padding: 12px 16px 12px 45px; font-size: 0.9rem; font-weight: 500; outline: none; transition: 0.2s; background: #F8FAFC; }
        .filter-search input:focus { background: white; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        
        .filter-select { background: #F8FAFC; border: 1px solid var(--border-light); border-radius: 14px; padding: 12px 16px; font-size: 0.9rem; font-weight: 600; color: var(--text-main); outline: none; cursor: pointer; transition: 0.2s; min-width: 180px; }
        .filter-select:focus { background: white; border-color: var(--primary); }
        
        .btn-filter { background: var(--text-main); color: white; border: none; border-radius: 14px; padding: 12px 28px; font-weight: 700; font-size: 0.9rem; transition: 0.2s; }
        .btn-filter:hover { background: #334155; }

        /* --- Premium Table Design --- */
        .table-card { background: var(--surface); border-radius: 24px; border: 1px solid var(--border-light); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.03); overflow: hidden; }
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table thead th { background: #F8FAFC; color: #64748B; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; padding: 18px 24px; border-bottom: 1px solid var(--border-light); white-space: nowrap; }
        .custom-table tbody tr { transition: 0.2s ease; border-bottom: 1px solid #F1F5F9; }
        .custom-table tbody tr:hover { background: #F8FAFC; }
        .custom-table tbody tr:last-child { border-bottom: none; }
        .custom-table td { padding: 24px; vertical-align: middle; }

        /* --- Elements inside Table --- */
        .inv-badge { font-family: 'Monaco', monospace; background: #F1F5F9; color: var(--text-main); font-weight: 700; font-size: 0.8rem; padding: 6px 12px; border-radius: 8px; border: 1px solid #E2E8F0; display: inline-block; letter-spacing: 0.5px; }
        
        .tenant-box { display: flex; align-items: center; gap: 12px; }
        .tenant-avatar { width: 40px; height: 40px; border-radius: 12px; background: #EEF2FF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; flex-shrink: 0; }
        .tenant-name { font-weight: 800; color: var(--text-main); font-size: 1rem; margin-bottom: 2px; }
        .tenant-prop { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 4px; }
        
        .amount-val { font-weight: 800; color: var(--text-main); font-size: 1.15rem; letter-spacing: -0.3px; margin-bottom: 4px; }
        
        .date-text { font-weight: 700; font-size: 0.9rem; margin-bottom: 2px; }
        .time-text { font-size: 0.75rem; color: #94A3B8; font-weight: 700; letter-spacing: 0.5px; }

        .status-verified { background: #F0FDF4; color: #16A34A; padding: 8px 16px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #BBF7D0; }

        /* Action Button */
        .btn-receipt { background: white; color: var(--primary); border: 1.5px solid #C7D2FE; font-weight: 700; font-size: 0.8rem; padding: 10px 20px; border-radius: 12px; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
        .btn-receipt:hover { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 15px rgba(79, 70, 229, 0.25); transform: translateY(-2px); }

        /* Empty State */
        .empty-state { text-align: center; padding: 80px 20px; }
        .empty-icon { font-size: 4rem; color: #E2E8F0; margin-bottom: 20px; }

        @media print {
            .navbar, .filter-toolbar, .btn-print-lite, .page-subtitle { display: none !important; }
            .container-app { padding: 0; max-width: 100%; }
            .table-card { border: none; box-shadow: none; }
            .custom-table thead th { background: #eee !important; color: #000 !important; }
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="gap-section d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="d-flex align-items-center">
                <div class="page-brand-logo">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <h1 class="page-title"><?= ($role == 'admin') ? 'Riwayat Transaksi' : 'Riwayat Pembayaran'; ?></h1>
                    <p class="page-subtitle m-0">Laporan resmi seluruh dana masuk yang telah diverifikasi sistem.</p>
                </div>
            </div>
            
            <?php if($role == 'admin') : ?>
            <div class="mt-3 mt-md-0">
                <button onclick="window.print()" class="btn-print-lite shadow-sm">
                    <i class="fa-solid fa-print"></i> Cetak Laporan
                </button>
            </div>
            <?php endif; ?>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon icon-wallet"><i class="fa-solid fa-vault"></i></div>
                <div class="stat-info">
                    <p><?= ($role == 'admin') ? 'Total Volume Pendapatan' : 'Total Pengeluaran Sewa'; ?></p>
                    <h3>Rp <?= number_format($total_volume, 0, ',', '.'); ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-receipt"><i class="fa-solid fa-file-circle-check"></i></div>
                <div class="stat-info">
                    <p>Total Transaksi Sukses</p>
                    <h3><?= number_format($total_transaksi, 0, ',', '.'); ?> <span style="font-size: 1rem; color: #94A3B8; font-weight: 600;">Faktur</span></h3>
                </div>
            </div>
        </div>

        <form method="GET" action="">
            <div class="filter-toolbar">
                <div class="filter-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" placeholder="Cari No. Invoice atau Nama..." value="<?= htmlspecialchars($search); ?>">
                </div>
                
                <select name="bulan" class="filter-select">
                    <option value="">Semua Waktu</option>
                    <?php
                    // Generate 6 bulan terakhir untuk dropdown
                    for ($i = 0; $i < 6; $i++) {
                        $val = date('Y-m', strtotime("-$i months"));
                        $label = date('F Y', strtotime("-$i months"));
                        $selected = ($filter_bulan == $val) ? 'selected' : '';
                        echo "<option value='$val' $selected>$label</option>";
                    }
                    ?>
                </select>
                
                <button type="submit" class="btn-filter">Terapkan Filter</button>
                
                <?php if(!empty($search) || !empty($filter_bulan)) : ?>
                    <a href="riwayat_transaksi.php" class="text-danger text-decoration-none fw-bold small ms-2"><i class="fa-solid fa-xmark"></i> Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th class="ps-4">Faktur</th>
                            <th>Informasi Penghuni</th>
                            <th>Detail Nominal</th>
                            <th>Waktu Pelunasan</th>
                            <th>Status Sistem</th>
                            <th class="text-center pe-4">Dokumen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($result)) : 
                            
                            // PERBAIKAN: SEKARANG MENGGUNAKAN waktu_transaksi (Dari t.created_at)
                            $waktu_bayar = strtotime($row['waktu_transaksi']);
                            $hari_ini = strtotime('today');
                            $kemarin = strtotime('yesterday');

                            if ($waktu_bayar >= $hari_ini) {
                                $tgl_display = "<span class='text-primary fw-bolder'>HARI INI</span>";
                            } elseif ($waktu_bayar >= $kemarin && $waktu_bayar < $hari_ini) {
                                $tgl_display = "<span class='text-warning fw-bolder'>KEMARIN</span>";
                            } else {
                                $tgl_display = "<span class='text-dark'>" . date('d M Y', $waktu_bayar) . "</span>";
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <span class="inv-badge">#<?= $row['no_invoice']; ?></span>
                            </td>
                            
                            <td>
                                <div class="tenant-box">
                                    <div class="tenant-avatar">
                                        <?= strtoupper(substr($row['nama_penghuni'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="tenant-name"><?= ucwords(strtolower($row['nama_penghuni'])); ?></div>
                                        <div class="tenant-prop">
                                            <i class="fa-solid fa-building text-primary opacity-75"></i> 
                                            <?= $row['kode_cabang'] ?? 'KST'; ?> &bull; <?= $row['nama_kost']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="amount-val">Rp <?= number_format($row['jumlah_bayar'], 0, ',', '.'); ?></div>
                            </td>
                            
                            <td>
                                <div class="date-text"><?= $tgl_display; ?></div>
                                <div class="time-text"><i class="fa-solid fa-clock-rotate-left me-1"></i> <?= date('H:i:s', $waktu_bayar); ?> WIB</div>
                            </td>
                            
                            <td>
                                <div class="status-verified">
                                    <i class="fa-solid fa-shield-check"></i> Diverifikasi
                                </div>
                            </td>
                            
                            <td class="text-center pe-4">
                                <a href="detail_pembayaran.php?id=<?= $row['id']; ?>" class="btn-receipt">
                                    <i class="fa-solid fa-file-invoice"></i> Kuitansi
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if(mysqli_num_rows($result) == 0) : ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fa-solid fa-file-circle-xmark empty-icon"></i>
                                    <h4 class="fw-extrabold text-dark mb-2">Data Tidak Ditemukan</h4>
                                    <p class="text-muted mb-0">Belum ada riwayat pembayaran yang sesuai dengan kriteria filter Anda.</p>
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