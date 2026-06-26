<?php
session_start();
require 'koneksi.php';
cek_login();

if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$success_msg = '';
$error_msg = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'kost';

// ==========================================
// 1. PROSES TAMBAH KOST (Sinkron Foto Database)
// ==========================================
if (isset($_POST['tambah_kost'])) {
    $nama_kost = mysqli_real_escape_string($conn, $_POST['nama_kost']);
    $kode_cabang = mysqli_real_escape_string($conn, $_POST['kode_cabang']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $no_hpon = mysqli_real_escape_string($conn, $_POST['no_hpon']);
    $fasilitas_umum = mysqli_real_escape_string($conn, $_POST['fasilitas_umum']);
    $nama_bank = mysqli_real_escape_string($conn, $_POST['nama_bank']);
    $no_rekening = mysqli_real_escape_string($conn, $_POST['no_rekening']);
    $atas_nama = mysqli_real_escape_string($conn, $_POST['atas_nama']);

    // Cek duplikasi kode cabang
    $cek = mysqli_query($conn, "SELECT id FROM kost WHERE kode_cabang = '$kode_cabang'");
    if (mysqli_num_rows($cek) > 0) {
        $error_msg = "Gagal: Kode Cabang '$kode_cabang' sudah terdaftar!";
    } else {
        // Query disesuaikan dengan struktur: nama_kost, kode_cabang, total_kamar, alamat, fasilitas_umum, nama_bank, no_rekening, atas_nama, no_hpon
        $query_kost = "INSERT INTO kost (nama_kost, kode_cabang, total_kamar, alamat, fasilitas_umum, nama_bank, no_rekening, atas_nama, no_hpon) 
                       VALUES ('$nama_kost', '$kode_cabang', 0, '$alamat', '$fasilitas_umum', '$nama_bank', '$no_rekening', '$atas_nama', '$no_hpon')";
        
        if (mysqli_query($conn, $query_kost)) {
            $success_msg = "Properti '$nama_kost' berhasil didaftarkan!";
            $active_tab = 'kost';
        } else {
            $error_msg = "Kesalahan Database: " . mysqli_error($conn);
        }
    }
}

// ==========================================
// 2. PROSES TAMBAH KAMAR
// ==========================================
if (isset($_POST['tambah_kamar'])) {
    $id_kost = mysqli_real_escape_string($conn, $_POST['id_kost']);
    $no_kamar = mysqli_real_escape_string($conn, $_POST['no_kamar']);
    $harga = mysqli_real_escape_string($conn, preg_replace('/[^0-9]/', '', $_POST['harga']));
    $fasilitas = mysqli_real_escape_string($conn, $_POST['fasilitas']);

    $cek_kamar = mysqli_query($conn, "SELECT id FROM kamar WHERE id_kost = '$id_kost' AND no_kamar = '$no_kamar'");
    if (mysqli_num_rows($cek_kamar) > 0) {
        $error_msg = "Gagal: Kamar '$no_kamar' sudah ada di properti ini!";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // Tambah Kamar
            mysqli_query($conn, "INSERT INTO kamar (id_kost, no_kamar, harga, fasilitas, status) VALUES ('$id_kost', '$no_kamar', '$harga', '$fasilitas', 'Tersedia')");
            
            // Update jumlah total_kamar di tabel kost secara otomatis
            mysqli_query($conn, "UPDATE kost SET total_kamar = total_kamar + 1 WHERE id = '$id_kost'");
            
            mysqli_commit($conn);
            $success_msg = "Unit Kamar '$no_kamar' berhasil ditambahkan!";
            $active_tab = 'kamar';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = "Gagal menyimpan data kamar.";
        }
    }
}

$list_kost = mysqli_query($conn, "SELECT id, nama_kost, kode_cabang FROM kost ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Aset | KostAdmin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary: #4F46E5; --bg: #F4F7F9; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: #0F172A; }
        .container-app { max-width: 1100px; margin: 0 auto; padding: 40px 20px; }
        
        .tab-nav { background: white; padding: 8px; border-radius: 100px; display: inline-flex; border: 1px solid #E2E8F0; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .tab-btn { padding: 10px 25px; border-radius: 100px; border: none; background: transparent; color: #64748B; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .tab-btn.active { background: #0F172A; color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }

        .form-card { background: white; border-radius: 24px; padding: 35px; border: 1px solid #E2E8F0; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        .form-section { display: none; }
        .form-section.active { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .input-box { margin-bottom: 20px; }
        .label-custom { font-size: 0.75rem; font-weight: 800; color: #64748B; text-transform: uppercase; margin-bottom: 8px; display: block; letter-spacing: 0.5px; }
        .input-custom { width: 100%; padding: 12px 15px; border-radius: 12px; border: 1.5px solid #E2E8F0; background: #F8FAFC; font-weight: 600; outline: none; transition: 0.2s; color: #0F172A; }
        .input-custom:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        
        .btn-submit { background: var(--primary); color: white; border: none; padding: 15px; border-radius: 14px; font-weight: 800; width: 100%; transition: 0.3s; }
        .btn-submit:hover { background: #4338CA; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3); }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        <div class="text-center mb-4">
            <h1 class="fw-800">Manajemen Aset</h1>
            <p class="text-muted">Kelola ekspansi properti dan inventaris unit kamar Anda.</p>
        </div>

        <div class="text-center">
            <div class="tab-nav">
                <button class="tab-btn <?= $active_tab == 'kost' ? 'active' : ''; ?>" id="btn-kost" onclick="switchTab('kost')"><i class="fa-solid fa-building me-2"></i>Cabang Kost</button>
                <button class="tab-btn <?= $active_tab == 'kamar' ? 'active' : ''; ?>" id="btn-kamar" onclick="switchTab('kamar')"><i class="fa-solid fa-bed me-2"></i>Unit Kamar</button>
            </div>
        </div>

        <div id="section-kost" class="form-section <?= $active_tab == 'kost' ? 'active' : ''; ?>">
            <div class="form-card">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h5 class="fw-800 mb-4 text-primary">Informasi Properti</h5>
                            <div class="input-box">
                                <label class="label-custom">Nama Kost</label>
                                <input type="text" name="nama_kost" class="input-custom" placeholder="Contoh: Grha Serpong BSD" required>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="input-box">
                                        <label class="label-custom">Kode Cabang</label>
                                        <input type="text" name="kode_cabang" class="input-custom" placeholder="GSP01" maxlength="10" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="input-box">
                                        <label class="label-custom">No. Telepon Kost</label>
                                        <input type="number" name="no_hpon" class="input-custom" placeholder="0812..." required>
                                    </div>
                                </div>
                            </div>
                            <div class="input-box">
                                <label class="label-custom">Alamat Lengkap</label>
                                <textarea name="alamat" class="input-custom" rows="2" placeholder="Jl. Raya No. 123..." required></textarea>
                            </div>
                            <div class="input-box">
                                <label class="label-custom">Fasilitas Umum</label>
                                <textarea name="fasilitas_umum" class="input-custom" rows="2" placeholder="Dapur, Parkir, WiFi..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6 ps-md-4">
                            <h5 class="fw-800 mb-4 text-primary">Rekening Pembayaran</h5>
                            <div class="alert alert-warning py-2 small fw-bold mb-4">
                                <i class="fa-solid fa-circle-info me-2"></i>Data ini akan digunakan untuk Virtual Account & Instruksi Transfer Penghuni.
                            </div>
                            <div class="input-box">
                                <label class="label-custom">Nama Bank</label>
                                <input type="text" name="nama_bank" class="input-custom" placeholder="BCA / Mandiri / BRI" required>
                            </div>
                            <div class="input-box">
                                <label class="label-custom">Nomor Rekening</label>
                                <input type="number" name="no_rekening" class="input-custom" placeholder="123456789" required>
                            </div>
                            <div class="input-box">
                                <label class="label-custom">Atas Nama</label>
                                <input type="text" name="atas_nama" class="input-custom" placeholder="Nama Pemilik Rekening" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="tambah_kost" class="btn-submit mt-3 shadow">Daftarkan Properti Baru</button>
                </form>
            </div>
        </div>

        <div id="section-kamar" class="form-section <?= $active_tab == 'kamar' ? 'active' : ''; ?>">
            <div class="form-card">
                <form method="POST">
                    <div class="row justify-content-center">
                        <div class="col-md-7">
                            <h5 class="fw-800 mb-4 text-center text-primary">Buka Unit Kamar Baru</h5>
                            <div class="input-box">
                                <label class="label-custom">Pilih Cabang Lokasi</label>
                                <select name="id_kost" class="input-custom" required>
                                    <option value="">-- Pilih Lokasi Properti --</option>
                                    <?php 
                                    mysqli_data_seek($list_kost, 0);
                                    while($k = mysqli_fetch_assoc($list_kost)) : ?>
                                        <option value="<?= $k['id']; ?>"><?= $k['kode_cabang']; ?> - <?= $k['nama_kost']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="input-box">
                                        <label class="label-custom">Nomor Kamar</label>
                                        <input type="text" name="no_kamar" class="input-custom" placeholder="101 / A-01" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="input-box">
                                        <label class="label-custom">Harga Sewa / Bulan</label>
                                        <input type="text" name="harga" class="input-custom" id="hargaIn" onkeyup="formatRupiah(this)" placeholder="1.500.000" required>
                                    </div>
                                </div>
                            </div>
                            <div class="input-box">
                                <label class="label-custom">Fasilitas Unit</label>
                                <textarea name="fasilitas" class="input-custom" rows="3" placeholder="AC, Kasur, Lemari, KM Dalam..."></textarea>
                            </div>
                            <button type="submit" name="tambah_kamar" class="btn-submit shadow">Aktifkan Unit Kamar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(type) {
            document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            if(type === 'kost') {
                document.getElementById('section-kost').classList.add('active');
                document.getElementById('btn-kost').classList.add('active');
            } else {
                document.getElementById('section-kamar').classList.add('active');
                document.getElementById('btn-kamar').classList.add('active');
            }
            window.history.replaceState(null, null, "?tab=" + type);
        }

        function formatRupiah(el) {
            let val = el.value.replace(/[^,\d]/g, '').toString();
            let split = val.split(','), sisa = split[0].length % 3, rupiah = split[0].substr(0, sisa), ribuan = split[0].substr(sisa).match(/\d{3}/gi);
            if (ribuan) { let sep = sisa ? '.' : ''; rupiah += sep + ribuan.join('.'); }
            el.value = rupiah;
        }
    </script>

    <?php if($success_msg) : ?>
        <script>Swal.fire({icon: 'success', title: 'Berhasil!', text: '<?= $success_msg; ?>', confirmButtonColor: '#4F46E5'});</script>
    <?php endif; ?>
    <?php if($error_msg) : ?>
        <script>Swal.fire({icon: 'error', title: 'Gagal!', text: '<?= $error_msg; ?>', confirmButtonColor: '#4F46E5'});</script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>