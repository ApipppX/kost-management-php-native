<?php
session_start();
require 'koneksi.php';
cek_login();


if (!isset($_GET['invoice'])) {
    header("Location: tagihan.php");
    exit;
}

$no_invoice = mysqli_real_escape_string($conn, $_GET['invoice']);

// 1. QUERY DATA LENGKAP (Tagihan + Penghuni + Kamar + Kost)
$query = "SELECT t.*, p.nama_penghuni, p.email, p.no_hp, k.no_kamar, ks.nama_kost, ks.nama_bank, ks.atas_nama 
          FROM tagihan t 
          JOIN penghuni p ON t.id_penghuni = p.id 
          JOIN kamar k ON p.id_kamar = k.id
          JOIN kost ks ON k.id_kost = ks.id
          WHERE t.no_invoice = '$no_invoice'";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) { die("Data tagihan tidak ditemukan!"); }
if ($data['status'] == 'Lunas') { header("Location: tagihan.php"); exit; }

// 2. LOGIKA REUSE SNAP TOKEN (Agar tidak duplikat di Midtrans)
$snap_token = $data['snap_token'] ?? '';
$error_midtrans = "";

if (empty($snap_token)) {
    // Jika token belum ada di database, buat request baru ke Midtrans
    $payload = [
        'transaction_details' => [
            'order_id' => $data['no_invoice'] . '-' . time(),
            'gross_amount' => (int)$data['jumlah'],
        ],
        'item_details' => [
            [
                'id'       => $data['no_kamar'],
                'price'    => (int)$data['jumlah'],
                'quantity' => 1,
                'name'     => "Sewa Kamar " . $data['no_kamar'] . " (" . $data['nama_kost'] . ")",
            ]
        ],
        'customer_details' => [
            'first_name' => $data['nama_penghuni'],
            'email'      => $data['email'] ?? 'customer@mail.com',
            'phone'      => $data['no_hp'],
        ],
        'callbacks' => [
            'finish' => "http://localhost/ManagementKost/pembayaran_selesai.php"
        ]

    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://app.sandbox.midtrans.com/snap/v1/transactions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
    ]);

    $response = curl_exec($ch);
    $res_data = json_decode($response, true);
    curl_close($ch);

    if (isset($res_data['token'])) {
        $snap_token = $res_data['token'];
        // Simpan token ke database untuk penggunaan ulang
        mysqli_query($conn, "UPDATE tagihan SET snap_token = '$snap_token' WHERE no_invoice = '$no_invoice'");
    } else {
        $error_midtrans = $res_data['error_messages'][0] ?? "Gagal mendapatkan respon dari Midtrans.";
    }
}

// Persiapkan Redirect URL (Sandbox Mode)
$redirect_url = !empty($snap_token) ? "https://app.sandbox.midtrans.com/snap/v2/vtweb/" . $snap_token : "";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | KostAdmin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #4F46E5; --bg: #F8FAFC; --text-dark: #0F172A; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-dark); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .checkout-card { background: white; border-radius: 30px; overflow: hidden; display: flex; width: 100%; max-width: 1050px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); border: 1px solid #E2E8F0; }
        
        .side-info { width: 40%; background: #0F172A; color: white; padding: 50px; position: relative; display: flex; flex-direction: column; justify-content: space-between; }
        .side-info::after { content: ''; position: absolute; bottom: 0; right: 0; width: 100px; height: 100px; background: var(--primary); filter: blur(80px); opacity: 0.4; }
        
        .inv-label { font-size: 0.7rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 5px; }
        .inv-val { font-size: 1.1rem; font-weight: 700; color: #F8FAFC; margin-bottom: 30px; }
        .price-val { font-size: 2.8rem; font-weight: 800; color: #38BDF8; letter-spacing: -1.5px; }

        .main-checkout { width: 60%; padding: 60px; background: white; text-align: center; }
        .gateway-logo { width: 140px; margin-bottom: 30px; opacity: 0.8; }
        .secure-badge { display: inline-flex; align-items: center; gap: 8px; background: #F0FDF4; color: #16A34A; padding: 8px 16px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; margin-bottom: 20px; border: 1px solid #BBF7D0; }
        
        .btn-pay { background: var(--text-dark); color: white; border: none; width: 100%; padding: 20px; border-radius: 18px; font-weight: 800; font-size: 1.1rem; transition: 0.3s; margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 12px; text-decoration: none; }
        .btn-pay:hover { background: var(--primary); transform: translateY(-3px); box-shadow: 0 15px 30px rgba(79, 70, 229, 0.3); color: white; }
        
        .cancel-link { display: inline-block; margin-top: 25px; color: #64748B; font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: 0.2s; }
        .cancel-link:hover { color: #EF4444; }

        @media (max-width: 850px) {
            .checkout-card { flex-direction: column; border-radius: 20px; }
            .side-info, .main-checkout { width: 100%; padding: 40px 30px; }
            .side-info { order: 2; }
            .main-checkout { order: 1; }
        }
    </style>
</head>
<body>

<div class="checkout-card">
    <div class="side-info">
        <div>
            <div class="inv-label">Nomor Invoice</div>
            <div class="inv-val">#<?= $data['no_invoice']; ?></div>
            
            <div class="inv-label">Penyewa</div>
            <div class="inv-val"><?= strtoupper($data['nama_penghuni']); ?></div>
            
            <div class="inv-label">Unit Properti</div>
            <div class="inv-val"><?= $data['nama_kost']; ?> - KM. <?= $data['no_kamar']; ?></div>

            <div class="mt-4 p-3 rounded-4" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                <div class="inv-label" style="font-size: 0.6rem;">Rekening Pengelola</div>
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 35px; height: 35px; font-size: 1rem; background: var(--primary); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; font-weight: 700; color: #F8FAFC;"><?= $data['nama_bank'] ?? 'Bank Transfer'; ?></div>
                        <div style="font-size: 0.75rem; color: #94A3B8;">A.N <?= strtoupper($data['atas_nama'] ?? 'Admin Kost'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="price-display mt-5">
            <div class="inv-label">Total Pembayaran</div>
            <div class="price-val">Rp <?= number_format($data['jumlah'], 0, ',', '.'); ?></div>
        </div>
    </div>

    <div class="main-checkout">
        <div class="secure-badge">
            <i class="fa-solid fa-shield-check"></i> TRANSAKSI TERENKRIPSI
        </div>
        
        <h2 class="fw-800 mb-2">Portal Pembayaran</h2>
        <p class="text-muted small mb-5">Anda akan menggunakan sistem Midtrans untuk memilih metode (VA, QRIS, E-Wallet, dll).</p>


        <?php if(!empty($redirect_url)): ?>
            <a href="<?= $redirect_url; ?>" class="btn-pay">
                <span>Buka Portal Pembayaran</span>
                <i class="fa-solid fa-arrow-right-long"></i>
            </a>
        <?php else: ?>
            <div class="alert alert-danger rounded-4 fw-bold">
                <i class="fa-solid fa-circle-exclamation me-2"></i> <?= $error_midtrans; ?>
            </div>
        <?php endif; ?>

        <a href="tagihan.php" class="cancel-link">
            <i class="fa-solid fa-xmark me-1"></i> Batal & Kembali
        </a>

        <div class="mt-5 pt-4 border-top">
            <p class="text-muted small mb-3">Keamanan didukung oleh:</p>
            <div class="d-flex justify-content-center gap-3 opacity-25" style="filter: grayscale(1);">
                <i class="fa-brands fa-cc-visa fs-3"></i>
                <i class="fa-brands fa-cc-mastercard fs-3"></i>
                <i class="fa-solid fa-qrcode fs-3"></i>
                <i class="fa-solid fa-building-columns fs-3"></i>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>