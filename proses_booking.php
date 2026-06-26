<?php
session_start();
require 'koneksi.php';
cek_login();

if (!isset($_GET['id_kamar'])) {
    header("Location: pilih_kamar.php");
    exit;
}

$id_kamar = mysqli_real_escape_string($conn, $_GET['id_kamar']);
$username = $_SESSION['username'];

$is_success = false;
$error_message = "";

// 1. Ambil data User
$user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, email FROM users WHERE username = '$username'"));
$id_user = $user_data['id'];
$email = $user_data['email'];

// 1.5 Ambil detail kamar & kost
$query_kamar = "SELECT k.harga_perbulan, ks.kode_cabang, k.no_kamar, ks.nama_kost 
                FROM kamar k 
                JOIN kost ks ON k.id_kost = ks.id 
                WHERE k.id = '$id_kamar' AND k.status = 'Tersedia'";
$kamar_result = mysqli_query($conn, $query_kamar);
$kamar_data = mysqli_fetch_assoc($kamar_result);

if (!$kamar_data) {
    $error_message = "Maaf, unit kamar tersebut baru saja dibooking oleh orang lain atau tidak tersedia.";
} else {
    $harga = $kamar_data['harga_perbulan'];
    $nama_kost = $kamar_data['nama_kost'];
    $kode_cabang = strtoupper($kamar_data['kode_cabang'] ?: 'KST'); 
    $no_kamar_booking = $kamar_data['no_kamar'];
    $tgl_masuk = date('Y-m-d');

    mysqli_begin_transaction($conn);
    try {
        // ================================================================
        // 2. PERBAIKAN LOGIKA SINKRONISASI PENGHUNI (CEK BERDASARKAN EMAIL)
        // ================================================================
        $cek_penghuni = mysqli_query($conn, "SELECT id FROM penghuni WHERE email = '$email'");
        
        if (mysqli_num_rows($cek_penghuni) > 0) {
            // JIKA USER SUDAH ADA DI TABEL PENGHUNI (Via Google / Profil) -> UPDATE SAJA
            $data_penghuni = mysqli_fetch_assoc($cek_penghuni);
            $id_penghuni_target = $data_penghuni['id'];
            
            mysqli_query($conn, "UPDATE penghuni SET id_kamar = '$id_kamar', tanggal_masuk = '$tgl_masuk', status_penghuni = 'Aktif' WHERE id = '$id_penghuni_target'");
            
            // Sinkronkan juga id_penghuni di tabel users agar database makin rapi
            mysqli_query($conn, "UPDATE users SET id_penghuni = '$id_penghuni_target' WHERE id = '$id_user'");
            
        } else {
            // JIKA USER BENAR-BENAR BARU -> INSERT
            mysqli_query($conn, "INSERT INTO penghuni (nama_penghuni, email, no_hp, id_kamar, tanggal_masuk, status_penghuni) 
                                 VALUES (UPPER('$username'), '$email', '-', '$id_kamar', '$tgl_masuk', 'Aktif')");
            $id_penghuni_target = mysqli_insert_id($conn);
            mysqli_query($conn, "UPDATE users SET id_penghuni = '$id_penghuni_target' WHERE id = '$id_user'");
        }

        // 3. Update Status Kamar
        mysqli_query($conn, "UPDATE kamar SET status = 'Terisi' WHERE id = '$id_kamar'");

        // 4. Terbitkan Tagihan Baru
        $no_inv = "INV-" . $kode_cabang . "-" . date('Ymd') . "-" . $id_penghuni_target;
        $deadline = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $bulan_sekarang = date('F Y');

        mysqli_query($conn, "INSERT INTO tagihan (no_invoice, id_penghuni, bulan_tagihan, jumlah, jatuh_tempo, status, created_at) 
                             VALUES ('$no_inv', '$id_penghuni_target', '$bulan_sekarang', '$harga', '$deadline', 'Belum Lunas', NOW())");

        mysqli_commit($conn);
        $is_success = true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = "Gangguan Database: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Securing Your Room | KostAdmin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #4F46E5; --bg: #F8FAFC; --text: #0F172A; }
        body { 
            margin: 0; background-color: var(--bg); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            display: flex; align-items: center; justify-content: center; 
            height: 100vh; overflow: hidden; 
        }

        /* Container Card */
        .booking-card { 
            background: white; width: 90%; max-width: 420px; 
            padding: 45px; border-radius: 40px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.05); 
            text-align: center; border: 1px solid #EEF2FF; 
            position: relative; z-index: 10;
        }

        /* Animated Loader */
        .loader-viz { position: relative; width: 100px; height: 100px; margin: 0 auto 30px auto; }
        .spinner { 
            position: absolute; width: 100%; height: 100%; 
            border: 6px solid #F1F5F9; border-top: 6px solid var(--primary); 
            border-radius: 50%; animation: spin 1s linear infinite; 
        }
        .inner-icon { 
            position: absolute; top: 50%; left: 50%; 
            transform: translate(-50%, -50%); font-size: 2rem; color: var(--text); 
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* Stepper Logic UI */
        .stepper { margin-top: 35px; text-align: left; }
        .step { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; opacity: 0.25; transition: 0.4s; }
        .step.active { opacity: 1; transform: scale(1.03); }
        .step.done { opacity: 0.8; color: #10B981; }
        
        .indicator { width: 10px; height: 10px; border-radius: 50%; background: #CBD5E1; transition: 0.3s; }
        .active .indicator { background: var(--primary); box-shadow: 0 0 12px rgba(79, 70, 229, 0.5); }
        .done .indicator { background: #10B981; }

        .step-label { font-size: 0.9rem; font-weight: 700; }

        h3 { font-weight: 800; font-size: 1.5rem; color: var(--text); margin: 0 0 10px 0; letter-spacing: -0.5px; }
        p.status-desc { color: #64748B; font-size: 0.85rem; line-height: 1.6; margin: 0; }

        /* Mencegah pergeseran saat Swal muncul */
        body.swal2-shown { padding-right: 0 !important; }
        .swal2-backdrop-show { backdrop-filter: blur(5px); }
    </style>
</head>
<body>

    <div class="booking-card">
        <div class="loader-viz">
            <div class="spinner"></div>
            <div class="inner-icon"><i class="fa-solid fa-file-contract"></i></div>
        </div>

        <h3 id="txt-header">Mengamankan Unit</h3>
        <p class="status-desc" id="txt-sub">Mohon tunggu, kami sedang memproses data Anda ke sistem kost.</p>

        <div class="stepper">
            <div class="step" id="step1">
                <div class="indicator"></div>
                <div class="step-label">Validasi Status Kamar</div>
            </div>
            <div class="step" id="step2">
                <div class="indicator"></div>
                <div class="step-label">Sinkronisasi Data User</div>
            </div>
            <div class="step" id="step3">
                <div class="indicator"></div>
                <div class="step-label">Menyiapkan Invoice Digital</div>
            </div>
        </div>
    </div>

    <script>
        const success = <?= $is_success ? 'true' : 'false'; ?>;
        const errMsg = "<?= addslashes($error_message); ?>";
        const roomNo = "<?= $no_kamar_booking ?? '' ?>";

        async function runAnimation() {
            const steps = [
                { id: 'step1', sub: 'Mengecek ketersediaan fisik...' },
                { id: 'step2', sub: 'Menghubungkan profil penghuni...' },
                { id: 'step3', sub: 'Membangun rincian tagihan...' }
            ];

            for (let i = 0; i < steps.length; i++) {
                document.getElementById(steps[i].id).classList.add('active');
                document.getElementById('txt-sub').innerText = steps[i].sub;
                
                await new Promise(r => setTimeout(r, 800));
                
                document.getElementById(steps[i].id).classList.remove('active');
                document.getElementById(steps[i].id).classList.add('done');
            }

            if (success) {
                Swal.fire({
                    title: 'Berhasil Dipesan!',
                    html: `<p style="font-size: 0.9rem; color: #64748B;">Unit <b>Kamar ${roomNo}</b> telah resmi diamankan.<br>Selesaikan pembayaran dalam 24 jam ke depan.</p>`,
                    icon: 'success',
                    iconColor: '#4F46E5',
                    confirmButtonText: 'Buka Dashboard Bayar',
                    confirmButtonColor: '#0F172A',
                    allowOutsideClick: false,
                    scrollbarPadding: false,
                    customClass: { popup: 'rounded-5 px-4', confirmButton: 'rounded-pill px-5 py-3 fw-bold' }
                }).then(() => {
                    window.location.href = 'tagihan.php';
                });
            } else {
                Swal.fire({
                    title: 'Pemesanan Gagal',
                    text: errMsg,
                    icon: 'error',
                    confirmButtonText: 'Kembali ke Katalog',
                    confirmButtonColor: '#EF4444',
                    scrollbarPadding: false,
                    customClass: { popup: 'rounded-5' }
                }).then(() => {
                    window.location.href = 'pilih_kamar.php';
                });
            }
        }

        window.onload = runAnimation;
    </script>
</body>
</html>