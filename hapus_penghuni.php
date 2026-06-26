<?php
session_start();
require 'koneksi.php';
cek_login();

// Keamanan: Hanya Admin yang boleh menghapus paksa
if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$status = 'error';
$msg = 'Permintaan tidak valid.';

if (isset($_GET['id'])) {
    $id_penghuni = mysqli_real_escape_string($conn, $_GET['id']);

    // Mulai eksekusi berantai (Transaction)
    mysqli_begin_transaction($conn);

    try {
        // 1. KOSONGKAN KAMAR (Jika dia masih menempati kamar)
        $cek_kamar = mysqli_query($conn, "SELECT id_kamar FROM penghuni WHERE id = '$id_penghuni'");
        if ($row = mysqli_fetch_assoc($cek_kamar)) {
            $id_kamar = $row['id_kamar'];
            if (!empty($id_kamar)) {
                mysqli_query($conn, "UPDATE kamar SET status = 'Tersedia' WHERE id = '$id_kamar'");
            }
        }

        // 2. PUTUSKAN RELASI AKUN (Ubah kembali jadi User biasa yang belum punya kamar)
        mysqli_query($conn, "UPDATE users SET id_penghuni = NULL WHERE id_penghuni = '$id_penghuni'");

        // 3. HAPUS PAKSA RIWAYAT KOMPLAIN
        mysqli_query($conn, "DELETE FROM komplain WHERE id_penghuni = '$id_penghuni'");

        // 4. HAPUS PAKSA RIWAYAT PEMBAYARAN & TAGIHAN
        // Cari semua nomor invoice milik penghuni ini untuk menghapus riwayat transaksinya
        $q_tagihan = mysqli_query($conn, "SELECT no_invoice FROM tagihan WHERE id_penghuni = '$id_penghuni'");
        while ($t = mysqli_fetch_assoc($q_tagihan)) {
            $inv = $t['no_invoice'];
            mysqli_query($conn, "DELETE FROM pembayaran WHERE no_invoice = '$inv'");
        }
        // Hapus tagihannya
        mysqli_query($conn, "DELETE FROM tagihan WHERE id_penghuni = '$id_penghuni'");

        // 5. HAPUS DATA PENGHUNI (EKSEKUSI TERAKHIR)
        $q_hapus = mysqli_query($conn, "DELETE FROM penghuni WHERE id = '$id_penghuni'");
        
        if (!$q_hapus) {
            throw new Exception(mysqli_error($conn));
        }

        // Jika semua langkah di atas berhasil, permanenkan perubahan di database!
        mysqli_commit($conn);
        $status = 'success';
        $msg = 'Data penghuni dan seluruh riwayat tagihannya berhasil dibumihanguskan dari sistem.';

    } catch (Exception $e) {
        // Jika ada 1 saja proses yang gagal, batalkan semuanya agar database tidak rusak
        mysqli_rollback($conn);
        $status = 'error';
        $msg = 'Gagal menghapus paksa: ' . $e->getMessage();
    }
} else {
    header("Location: penghuni.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memproses Penghapusan...</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; margin: 0; height: 100vh; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
    <script>
        Swal.fire({
            icon: '<?= $status ?>',
            title: '<?= $status == "success" ? "Terhapus Permanen!" : "Gagal Menghapus" ?>',
            text: '<?= $msg ?>',
            confirmButtonColor: '<?= $status == "success" ? "#0F172A" : "#EF4444" ?>',
            allowOutsideClick: false,
            customClass: { popup: 'rounded-4' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'penghuni.php';
            }
        });
    </script>
</body>
</html>