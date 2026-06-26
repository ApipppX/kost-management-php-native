<?php
session_start();
require 'koneksi.php';
cek_login();

if (!isset($_GET['id'])) {
    header("Location: riwayat_transaksi.php");
    exit;
}

$id_pembayaran = mysqli_real_escape_string($conn, $_GET['id']);
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Query lengkap untuk mengambil data relasi
$query = "SELECT 
            p.*, 
            t.bulan_tagihan, 
            t.id_penghuni as uid_penghuni, 
            pen.nama_penghuni, 
            pen.no_hp,
            pen.email,
            k.no_kamar, 
            ks.nama_kost, 
            ks.kode_cabang,
            ks.nama_bank, 
            ks.no_rekening, 
            ks.atas_nama 
          FROM pembayaran p
          JOIN tagihan t ON p.no_invoice = t.no_invoice
          JOIN penghuni pen ON t.id_penghuni = pen.id
          JOIN kamar k ON pen.id_kamar = k.id
          JOIN kost ks ON k.id_kost = ks.id
          WHERE p.id = '$id_pembayaran'";

$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) die("Data pembayaran tidak ditemukan!");

// PROTEKSI USER: Mencegah user mengintip kuitansi orang lain
if ($role == 'user') {
    $cek_milik = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id_penghuni = '".$data['uid_penghuni']."'");
    if (mysqli_num_rows($cek_milik) == 0) {
        echo "<script>alert('Akses Ditolak!'); window.location='riwayat_transaksi.php';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuitansi #<?= $data['no_invoice']; ?> | KostAdmin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --primary: #4F46E5; 
            --bg: #F1F5F9; 
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border-light: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); padding: 40px 20px; }
        
        .receipt-wrapper { width: 100%; max-width: 1050px; margin: 0 auto; }
        
        /* Kuitansi Paper Style */
        .receipt-paper { 
            background: white; border-radius: 0 0 24px 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); 
            position: relative; overflow: hidden; border: 1px solid var(--border-light); border-top: none;
            height: 100%; display: flex; flex-direction: column;
        }
        
        .receipt-sawtooth {
            height: 14px; background: white; width: 100%;
            background-image: radial-gradient(circle at 10px 0, transparent 10px, white 11px);
            background-size: 20px 14px; background-repeat: repeat-x;
            filter: drop-shadow(0 -4px 4px rgba(0,0,0,0.03));
        }

        .stamp-lunas {
            position: absolute; top: 160px; right: 15px; font-size: 2.8rem; font-weight: 800; 
            color: rgba(34, 197, 94, 0.08); border: 6px solid rgba(34, 197, 94, 0.08); 
            padding: 5px 25px; border-radius: 14px; transform: rotate(-15deg); 
            letter-spacing: 3px; pointer-events: none; z-index: 0;
        }

        .r-brand-header { background: #F8FAFC; padding: 35px 30px 25px; text-align: center; border-bottom: 2px dashed #CBD5E1; }
        .brand-logo-box { width: 60px; height: 60px; background: var(--primary); color: white; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 15px; }
        
        .r-amount-section { padding: 35px; text-align: center; }
        .r-amount { font-size: 2.8rem; font-weight: 800; color: var(--text-main); letter-spacing: -1.5px; }
        .r-status { background: #DCFCE7; color: #16A34A; padding: 6px 16px; border-radius: 50px; font-size: 0.85rem; font-weight: 800; display: inline-block; margin-top: 15px; border: 1px solid #BBF7D0; }

        .r-body { padding: 10px 40px 40px; flex-grow: 1; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .info-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }
        .info-val { font-size: 0.9rem; font-weight: 700; color: var(--text-main); text-align: right; }
        
        .inv-box { background: #F8FAFC; border-radius: 12px; padding: 15px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border-light); }
        .inv-text { font-family: 'Monaco', monospace; font-weight: 800; color: var(--primary); }

        /* Panel Side */
        .side-card { background: white; border-radius: 20px; padding: 25px; border: 1px solid var(--border-light); margin-bottom: 20px; }
        .btn-action-side { padding: 14px; border-radius: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; transition: 0.2s; width: 100%; margin-bottom: 10px; }
        .btn-print { background: var(--primary); color: white; border: none; }
        .btn-back { background: #F8FAFC; color: var(--text-main); border: 1px solid var(--border-light); }

        @media print {
            body { background: white; padding: 0; }
            .side-panel, .receipt-sawtooth, .navbar { display: none !important; }
            .receipt-paper { box-shadow: none; border: 1px solid #000; }
            .receipt-wrapper { max-width: 100%; }
            .col-lg-7 { width: 100%; }
        }
    </style>
</head>
<body>

<div class="receipt-wrapper">
    <div class="row g-4">
        
        <div class="col-lg-7">
            <div class="receipt-sawtooth"></div>
            <div class="receipt-paper">
                <div class="stamp-lunas">LUNAS</div>

                <div class="r-brand-header">
                    <div class="brand-logo-box"><i class="fa-solid fa-house-chimney-window"></i></div>
                    <h4 class="fw-800 mb-1"><?= strtoupper($data['nama_kost']); ?></h4>
                    <div class="small fw-600 text-muted"><?= $data['kode_cabang']; ?> - Bukti Pembayaran Sah</div>
                </div>

                <div class="r-amount-section">
                    <div class="text-muted small fw-bold mb-1">TOTAL DIBAYARKAN</div>
                    <h2 class="r-amount">Rp <?= number_format($data['jumlah_bayar'], 0, ',', '.'); ?></h2>
                    <div class="r-status"><i class="fa-solid fa-circle-check"></i> TRANSAKSI BERHASIL</div>
                </div>

                <div class="r-body">
                    <div class="inv-box">
                        <span class="info-label">No. Invoice</span>
                        <span class="inv-text">#<?= $data['no_invoice']; ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Penyewa</span>
                        <span class="info-val"><?= strtoupper($data['nama_penghuni']); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Unit</span>
                        <span class="info-val">KM. <?= $data['no_kamar']; ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Metode Bayar</span>
                        <span class="info-val"><?= strtoupper($data['metode_bayar']); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Tanggal Bayar</span>
                        <span class="info-val"><?= date('d M Y, H:i', strtotime($data['tanggal_bayar'])); ?></span>
                    </div>

                    <hr class="my-4" style="border-top: 1px dashed #CBD5E1;">
                    
                    <div class="info-row">
                        <span class="info-label">Metode Pembayaran</span>
                        <span class="info-val fw-800 text-primary"><?= $data['metode_bayar']; ?></span>
                    </div>

                    <?php if($data['metode_bayar'] == 'Midtrans Gateway' || $data['metode_bayar'] == 'Midtrans') : ?>
                        <div class="alert alert-info border-0 rounded-3 p-3 mt-2" style="background: #F0F9FF;">
                            <div class="d-flex gap-2">
                                <i class="fa-solid fa-shield-check text-info mt-1"></i>
                                <div class="small text-info fw-600">Terverifikasi otomatis oleh sistem Midtrans. Dana diteruskan ke pengelola secara aman.</div>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="info-row mt-3">
                            <span class="info-label">Rekening Tujuan</span>
                            <span class="info-val"><?= $data['nama_bank']; ?> - <?= $data['no_rekening']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Atas Nama</span>
                            <span class="info-val"><?= strtoupper($data['atas_nama']); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-5">
                        <div class="small text-muted fw-bold italic">*** Simpan kuitansi ini sebagai bukti pembayaran yang sah ***</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 side-panel">
            <div class="side-card">
                <button onclick="window.print()" class="btn-action-side btn-print">
                    <i class="fa-solid fa-print"></i> Cetak Kuitansi (PDF)
                </button>
                <a href="riwayat_transaksi.php" class="btn-action-side btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Riwayat
                </a>
            </div>

            <div class="side-card">
                <h6 class="fw-800 mb-3"><i class="fa-solid fa-user-circle me-2 text-primary"></i>Profil Penyewa</h6>
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 45px; height: 45px; background: #EEF2FF; color: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                        <?= substr($data['nama_penghuni'], 0, 1); ?>
                    </div>
                    <div>
                        <div class="fw-800 small"><?= $data['nama_penghuni']; ?></div>
                        <div class="text-muted" style="font-size: 0.7rem;"><?= $data['email'] ?: 'No Email'; ?></div>
                    </div>
                </div>
                <a href="https://wa.me/<?= preg_replace('/^0/', '62', $data['no_hp']); ?>" target="_blank" class="btn btn-sm btn-success w-100 mt-3 fw-bold rounded-pill">
                    <i class="fa-brands fa-whatsapp me-1"></i> Kirim ke WhatsApp
                </a>
            </div>
        </div>

    </div>
</div>

</body>
</html>