<?php
session_start();
require 'koneksi.php';
cek_login();

$order_id_full = $_GET['order_id'] ?? '';
$status_code = $_GET['status_code'] ?? '';
$transaction_status = $_GET['transaction_status'] ?? '';

$res_status = 'pending';
$no_invoice = '';
$jumlah_bayar = 0;
$data_invoice = []; 

if ($status_code == '200' || $transaction_status == 'settlement' || $transaction_status == 'capture') {
    $parts = explode('-', $order_id_full);
    array_pop($parts); 
    $no_invoice = implode('-', $parts);

    mysqli_begin_transaction($conn);
    try {
        $cek = mysqli_query($conn, "SELECT jumlah FROM tagihan WHERE no_invoice = '$no_invoice'");
        if(mysqli_num_rows($cek) > 0) {
            $dt = mysqli_fetch_assoc($cek);
            $jumlah_bayar = $dt['jumlah'];

            mysqli_query($conn, "UPDATE tagihan SET status = 'Lunas' WHERE no_invoice = '$no_invoice'");
            $tgl = date('Y-m-d H:i:s');
            
            $cek_bayar = mysqli_query($conn, "SELECT id FROM pembayaran WHERE no_invoice = '$no_invoice'");
            if (mysqli_num_rows($cek_bayar) == 0) {
                mysqli_query($conn, "INSERT INTO pembayaran (no_invoice, tanggal_bayar, jumlah_bayar, metode_bayar, bukti_transfer) 
                                     VALUES ('$no_invoice', '$tgl', '$jumlah_bayar', 'Midtrans Gateway', 'Verified-Digital')");
            }
            
            mysqli_commit($conn);
            $res_status = 'success';

            $query_detail = "SELECT t.*, p.tanggal_bayar, p.metode_bayar, ph.nama_penghuni, k.no_kamar, ks.nama_kost 
                             FROM tagihan t
                             LEFT JOIN pembayaran p ON t.no_invoice = p.no_invoice
                             JOIN penghuni ph ON t.id_penghuni = ph.id
                             JOIN kamar k ON ph.id_kamar = k.id
                             JOIN kost ks ON k.id_kost = ks.id
                             WHERE t.no_invoice = '$no_invoice'";
            $detail_result = mysqli_query($conn, $query_detail);
            $data_invoice = mysqli_fetch_assoc($detail_result);
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $res_status = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success | KostAdmin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --primary: #4F46E5; 
            --primary-hover: #3730A3;
            --success: #10B981; 
            --bg: #F8FAFC; 
            --dark: #0F172A;
            --text-muted: #64748B; 
            --border: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--dark); margin: 0; padding-bottom: 50px; }
        
        /* =========================================
           TAMPILAN MONITOR UTAMA (SPLIT LAYOUT)
           ========================================= */
        .screen-area { animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        .split-container {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            max-width: 950px;
            width: 100%;
            margin: 40px auto 0;
            display: flex;
            min-height: 550px;
        }

        /* Sisi Kiri: Visual Status */
        .left-panel {
            flex: 1;
            background: var(--dark);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
            position: relative;
        }
        .left-panel::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(79, 70, 229, 0.12) 0%, transparent 60%);
        }

        .icon-circle {
            width: 80px; height: 80px;
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        .main-title { font-weight: 800; font-size: 1.8rem; letter-spacing: -0.5px; margin-bottom: 12px; position: relative; z-index: 2; }
        .sub-title { color: #94A3B8; font-size: 0.95rem; line-height: 1.6; max-width: 320px; margin: 0 auto; position: relative; z-index: 2; }

        /* Sisi Kanan: Detail & Konfigurasi Tombol */
        .right-panel {
            flex: 1.2;
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .section-header { font-weight: 800; font-size: 1.1rem; color: var(--dark); margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px; }

        .details-box {
            background: #F8FAFC;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 30px;
            border: 1px solid #f1f5f9;
        }
        
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-label { color: var(--text-muted); font-weight: 600; font-size: 0.9rem; }
        .detail-value { color: var(--dark); font-weight: 800; font-size: 0.95rem; }

        .btn-custom {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 14px; border-radius: 12px;
            font-weight: 700; text-decoration: none; transition: 0.2s;
            margin-bottom: 12px; font-size: 0.95rem; border: none; cursor: pointer;
        }
        
        .btn-print { background: var(--dark); color: white; }
        .btn-print:hover { background: var(--primary); color: white; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(79, 70, 229, 0.15); }
        
        .btn-wa { background: white; color: var(--success); border: 2px solid #dcfce7; }
        .btn-wa:hover { background: #f0fdf4; color: var(--success); border-color: #bbf7d0; }

        /* Responsif Perangkat Mobile */
        @media (max-width: 991.98px) {
            .split-container { flex-direction: column; max-width: 500px; min-height: auto; margin: 20px auto 0; }
            .left-panel { padding: 50px 20px; }
            .right-panel { padding: 40px 25px; }
        }

        /* =========================================
           TAMPILAN PRINTER (PREVIEW MODE ONLY)
           ========================================= */
        .print-area { display: none; }
        
        @media print {
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .screen-area, .navbar { display: none !important; }
            .print-area { display: block !important; padding: 20px; color: black; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
            
            .print-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
            .print-title { font-size: 24px; font-weight: bold; margin: 0; }
            .print-subtitle { color: #555; margin-top: 5px; font-size: 14px; }
            .print-badge { background: #10B981; color: white !important; -webkit-print-color-adjust: exact; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 14px; }
            
            .print-info-grid { display: flex; justify-content: space-between; margin-bottom: 40px; }
            .info-item { margin-bottom: 15px; }
            .info-label { font-size: 12px; color: #666; text-transform: uppercase; font-weight: bold; margin-bottom: 4px; }
            .info-value { font-size: 16px; font-weight: bold; }
            
            .print-list-header { display: flex; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; font-weight: bold; font-size: 13px; text-transform: uppercase; }
            .print-list-row { display: flex; border-bottom: 1px solid #ddd; padding-bottom: 15px; margin-bottom: 15px; }
            .col-desc { flex: 2; }
            .col-period { flex: 1; }
            .col-amount { flex: 1; text-align: right; font-weight: bold; }
            
            .print-total { display: flex; justify-content: flex-end; align-items: center; margin-top: 20px; font-size: 18px; font-weight: bold; }
            .print-total-label { margin-right: 20px; font-size: 14px; color: #555; text-transform: uppercase; }
            
            .print-footer { margin-top: 60px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eee; padding-top: 20px; }
        }
    </style>
</head>
<body>

    <div class="screen-area">
        <?php include 'navbar.php'; ?>
        
        <div class="container px-3">
            <?php if($res_status == 'success'): ?>
            <div class="split-container">
                
                <div class="left-panel">
                    <div class="icon-circle">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h1 class="main-title">Pembayaran Berhasil</h1>
                    <p class="sub-title">Sistem otomasi KostAdmin telah memverifikasi pelunasan transaksi Anda.</p>
                </div>

                <div class="right-panel">
                    <div class="section-header">Rincian Pembayaran</div>
                    
                    <div class="details-box">
                        <div class="detail-row">
                            <span class="detail-label">Nomor Invoice</span>
                            <span class="detail-value text-primary">#<?= $no_invoice ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Nominal</span>
                            <span class="detail-value">Rp <?= number_format($jumlah_bayar, 0, ',', '.') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Saluran Pembayaran</span>
                            <span class="detail-value">Midtrans Gateway</span>
                        </div>
                    </div>

                    <button onclick="window.print()" class="btn-custom btn-print">
                        <i class="fa-solid fa-print"></i> Pratinjau & Cetak Kuitansi
                    </button>
                    
                    <a href="https://wa.me/6281234567890?text=Halo%20Admin,%20saya%20sudah%20melakukan%20pembayaran%20untuk%20invoice%20*<?= $no_invoice; ?>*%20sebesar%20*Rp%20<?= number_format($jumlah_bayar, 0, ',', '.'); ?>*.%20Mohon%20dicek.%20Terima%20kasih." target="_blank" class="btn-custom btn-wa">
                        <i class="fa-brands fa-whatsapp fs-5"></i> Kirim Notifikasi ke Admin
                    </a>

                    <div class="text-center mt-3">
                        <a href="tagihan.php" class="text-muted fw-bold text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Menu Tagihan</a>
                    </div>
                </div>

            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if($res_status == 'success' && !empty($data_invoice)): ?>
    <div class="print-area">
        <div class="print-header">
            <div>
                <h1 class="print-title">KostAdmin Pro</h1>
                <div class="print-subtitle">Tanda Terima Pembayaran Digital</div>
            </div>
            <div class="print-badge">LUNAS</div>
        </div>

        <div class="print-info-grid">
            <div>
                <div class="info-item">
                    <div class="info-label">Nama Penghuni</div>
                    <div class="info-value"><?= strtoupper($data_invoice['nama_penghuni']); ?></div>
                    <div style="font-size: 14px; color: #555; margin-top: 4px;"><?= $data_invoice['nama_kost']; ?> — Kamar <?= $data_invoice['no_kamar']; ?></div>
                </div>
            </div>
            <div style="text-align: right;">
                <div class="info-item">
                    <div class="info-label">No. Invoice</div>
                    <div class="info-value"><?= $no_invoice; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Waktu Pembayaran</div>
                    <div class="info-value"><?= !empty($data_invoice['tanggal_bayar']) ? date('d F Y, H:i', strtotime($data_invoice['tanggal_bayar'])) : '-'; ?></div>
                </div>
            </div>
        </div>

        <div class="print-list-header">
            <div class="col-desc">Deskripsi Pemesanan</div>
            <div class="col-period">Periode Buku</div>
            <div class="col-amount">Subtotal</div>
        </div>
        
        <div class="print-list-row">
            <div class="col-desc">Sewa Kamar Hunian (Unit <?= $data_invoice['no_kamar']; ?> — <?= $data_invoice['nama_kost']; ?>)</div>
            <div class="col-period"><?= $data_invoice['bulan_tagihan']; ?></div>
            <div class="col-amount">Rp <?= number_format($jumlah_bayar, 0, ',', '.'); ?></div>
        </div>
        
        <div class="print-total">
            <div class="print-total-label">Total Dana yang Diterima</div>
            <div>Rp <?= number_format($jumlah_bayar, 0, ',', '.'); ?></div>
        </div>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 40px; display: inline-block;">
            <div class="info-label">Metode Pembayaran</div>
            <div class="info-value" style="font-size: 14px;"><?= !empty($data_invoice['metode_bayar']) ? $data_invoice['metode_bayar'] : 'Midtrans Payment Gateway'; ?></div>
        </div>

        <div class="print-footer">
            Lembar ini diterbitkan secara sah melalui sistem komputerisasi KostAdmin Pro dan berlaku sebagai tanda terima mutlak.<br>
            © <?= date('Y'); ?> KostAdmin Pro. All rights reserved.
        </div>
    </div>
    <?php endif; ?>

    <script>
        window.onload = function() {
            <?php if($transaction_status == 'pending'): ?>
                Swal.fire({ 
                    icon: 'warning', title: 'Status Pending', text: 'Sistem sedang menunggu penyelesaian pembayaran Anda...', 
                    confirmButtonColor: '#F59E0B', customClass: { popup: 'rounded-4' }
                }).then(() => { window.location.href = 'tagihan.php'; });
            <?php elseif($res_status == 'error'): ?>
                Swal.fire({ 
                    icon: 'error', title: 'Verifikasi Gagal', text: 'Terjadi kegagalan sinkronisasi data internal database.', 
                    confirmButtonColor: '#0F172A', customClass: { popup: 'rounded-4' }
                }).then(() => { window.location.href = 'tagihan.php'; });
            <?php elseif($res_status != 'success' && $res_status != 'error' && $transaction_status != 'pending'): ?>
                window.location.href = 'tagihan.php';
            <?php endif; ?>
        };
    </script>
</body>
</html>