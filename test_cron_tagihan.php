<?php
// cron_tagihan.php
// File ini dijalankan oleh server (Cron Job) setiap jam 00:01 malam
require 'koneksi.php';

$bulan_ini = date('F Y'); // Contoh: April 2026
$total_dibuat = 0;

// 1. Ambil semua penghuni aktif
$query_penghuni = "SELECT p.id as id_penghuni, p.tanggal_masuk, k.harga_perbulan, ks.kode_cabang
                   FROM penghuni p
                   JOIN kamar k ON p.id_kamar = k.id
                   JOIN kost ks ON k.id_kost = ks.id
                   WHERE p.status_penghuni = 'Aktif'";
$result_penghuni = mysqli_query($conn, $query_penghuni);

mysqli_begin_transaction($conn);
try {
    while ($penghuni = mysqli_fetch_assoc($result_penghuni)) {
        $id_p = $penghuni['id_penghuni'];
        $harga = $penghuni['harga_perbulan'];
        $kode = $penghuni['kode_cabang'] ?: 'KST';
        
        // 2. Cek apakah invoice bulan ini sudah dibuat untuk penghuni ini?
        $cek_tagihan = mysqli_query($conn, "SELECT id FROM tagihan WHERE id_penghuni = '$id_p' AND bulan_tagihan = '$bulan_ini'");

        if (mysqli_num_rows($cek_tagihan) == 0) {
            // Belum ada tagihan bulan ini, saatnya Generate!
            
            // Ambil tanggal (misal dia masuk tanggal 15)
            $tgl_rutin = date('d', strtotime($penghuni['tanggal_masuk']));
            $jatuh_tempo = date("Y-m-$tgl_rutin 23:59:59");
            
            // Jika tanggal rutin sudah lewat (misal bulan Februari ga ada tgl 30), paskan ke akhir bulan
            if (date('m', strtotime($jatuh_tempo)) != date('m')) {
                $jatuh_tempo = date("Y-m-t 23:59:59"); 
            }

            $no_inv = "INV-" . $kode . "-" . date('Ym') . "-" . $id_p . "-" . rand(10,99);

            $insert = "INSERT INTO tagihan (no_invoice, id_penghuni, bulan_tagihan, jumlah, jatuh_tempo, status, created_at) 
                       VALUES ('$no_inv', '$id_p', '$bulan_ini', '$harga', '$jatuh_tempo', 'Belum Lunas', NOW())";
            mysqli_query($conn, $insert);
            $total_dibuat++;
        }
    }
    mysqli_commit($conn);
    echo "Sistem selesai mengecek. $total_dibuat tagihan baru diterbitkan untuk bulan $bulan_ini.";
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "Error: " . $e->getMessage();
}
?>