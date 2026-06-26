<?php
session_start();
require 'koneksi.php';
cek_login();

// Proteksi Keamanan: Hanya Admin
if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php"); // Atau arahkan ke halaman daftar kamar jika kamu punya (misal: kamar.php)
    exit;
}

$id_kamar = mysqli_real_escape_string($conn, $_GET['id']);
$success_msg = "";
$error_msg = "";

// ==========================================
// 1. PROSES UPDATE DATA KAMAR
// ==========================================
if (isset($_POST['update_kamar'])) {
    $id_kost = mysqli_real_escape_string($conn, $_POST['id_kost']);
    $no_kamar = mysqli_real_escape_string($conn, trim($_POST['no_kamar']));
    // Bersihkan format titik/koma jika ada input manual
    $harga_perbulan = preg_replace("/[^0-9]/", "", $_POST['harga_perbulan']); 
    $fasilitas_kamar = mysqli_real_escape_string($conn, trim($_POST['fasilitas_kamar']));
    $status_kamar = mysqli_real_escape_string($conn, $_POST['status']);

    // Cek apakah nomor kamar sudah dipakai di properti yang sama (kecuali ID ini sendiri)
    $cek_duplikat = mysqli_query($conn, "SELECT id FROM kamar WHERE id_kost = '$id_kost' AND no_kamar = '$no_kamar' AND id != '$id_kamar'");
    
    if (mysqli_num_rows($cek_duplikat) > 0) {
        $error_msg = "Nomor Kamar '$no_kamar' sudah terdaftar di properti ini. Silakan gunakan nomor lain.";
    } else {
        $update_query = "UPDATE kamar SET 
                            id_kost = '$id_kost', 
                            no_kamar = '$no_kamar', 
                            harga_perbulan = '$harga_perbulan', 
                            fasilitas_kamar = '$fasilitas_kamar', 
                            status = '$status_kamar' 
                         WHERE id = '$id_kamar'";
                         
        if (mysqli_query($conn, $update_query)) {
            $success_msg = "Data kamar berhasil diperbarui.";
        } else {
            $error_msg = "Gagal memperbarui data: " . mysqli_error($conn);
        }
    }
}

// ==========================================
// 2. AMBIL DATA KAMAR SAAT INI
// ==========================================
$query_kamar = mysqli_query($conn, "SELECT * FROM kamar WHERE id = '$id_kamar'");
if (mysqli_num_rows($query_kamar) == 0) {
    echo "<script>alert('Data kamar tidak ditemukan!'); window.location='dashboard.php';</script>";
    exit;
}
$kamar = mysqli_fetch_assoc($query_kamar);

// ==========================================
// 3. AMBIL DATA PROPERTI (KOST) UNTUK DROPDOWN
// ==========================================
$q_kost = mysqli_query($conn, "SELECT id, nama_kost, kode_cabang FROM kost ORDER BY nama_kost ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Unit Kamar | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --primary: #4F46E5; --primary-hover: #4338CA;
            --bg: #F8FAFC; --surface: #ffffff;
            --text-main: #0F172A; --text-muted: #64748B;
            --border-light: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        .container-app { max-width: 900px; margin: 0 auto; padding: 40px 20px; }

        /* --- HEADER BANNER --- */
        .page-header-wrapper { display: flex; align-items: center; gap: 20px; margin-bottom: 35px; }
        .brand-icon-box { 
            width: 56px; height: 56px; background: #1E293B; color: white; 
            border-radius: 16px; display: flex; align-items: center; 
            justify-content: center; font-size: 1.5rem; flex-shrink: 0;
            box-shadow: 0 10px 20px rgba(0,0,0,0.06);
        }
        .page-title { font-weight: 800; font-size: 1.8rem; letter-spacing: -1px; margin-bottom: 2px; color: var(--text-main); }
        .page-subtitle { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin: 0; }

        /* --- FORM CARD PREMIUM --- */
        .form-card { 
            background: var(--surface); border-radius: 24px; border: 1px solid var(--border-light); 
            padding: 40px; box-shadow: 0 15px 35px -10px rgba(0,0,0,0.04); 
        }
        
        .form-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B; margin-bottom: 8px; display: block; }
        
        /* Input Group Custom */
        .input-group-premium { position: relative; display: flex; align-items: stretch; width: 100%; border-radius: 14px; overflow: hidden; border: 1.5px solid var(--border-light); transition: 0.3s; background: #F8FAFC; }
        .input-group-premium:focus-within { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); background: white; }
        
        .ig-icon { display: flex; align-items: center; justify-content: center; padding: 0 18px; color: #94A3B8; background: transparent; font-size: 1.1rem; }
        .input-group-premium:focus-within .ig-icon { color: var(--primary); }
        
        .form-control-custom, .form-select-custom { border: none; padding: 14px 16px 14px 0; font-weight: 600; font-size: 0.95rem; background: transparent; width: 100%; color: var(--text-main); outline: none; }
        .form-select-custom { padding-left: 16px; }

        /* Textarea khusus */
        .textarea-custom { border-radius: 14px; border: 1.5px solid var(--border-light); padding: 16px; font-weight: 500; font-size: 0.95rem; background: #F8FAFC; width: 100%; outline: none; transition: 0.3s; color: var(--text-main); }
        .textarea-custom:focus { background: white; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }

        .form-text { font-size: 0.75rem; font-weight: 600; color: #94A3B8; margin-top: 6px; }

        /* --- BUTTONS --- */
        .btn-action-container { display: flex; gap: 15px; margin-top: 40px; padding-top: 30px; border-top: 1px dashed var(--border-light); }
        .btn-save { background: var(--text-main); color: white; border: none; border-radius: 14px; padding: 16px 30px; font-weight: 800; font-size: 1rem; transition: 0.3s; flex-grow: 1; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-save:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(79, 70, 229, 0.25); }
        
        .btn-back { background: #F1F5F9; color: var(--text-main); border: 1px solid var(--border-light); border-radius: 14px; padding: 16px 25px; font-weight: 800; font-size: 1rem; transition: 0.3s; text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-back:hover { background: #E2E8F0; color: var(--text-main); }

        .warning-box { background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 12px; padding: 15px; display: flex; gap: 15px; align-items: flex-start; margin-bottom: 25px; }
        .warning-box i { color: #D97706; font-size: 1.2rem; }
        .warning-box p { margin: 0; font-size: 0.85rem; color: #92400E; font-weight: 600; line-height: 1.5; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="page-header-wrapper">
            <div class="brand-icon-box">
                <i class="fa-solid fa-pen-ruler"></i>
            </div>
            <div>
                <h1 class="page-title">Edit Unit Kamar</h1>
                <p class="page-subtitle">Modifikasi spesifikasi kamar, harga, dan ketersediaan unit properti.</p>
            </div>
        </div>

        <div class="form-card">
            
            <?php if($kamar['status'] == 'Terisi') : ?>
                <div class="warning-box">
                    <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                    <p><b>Perhatian:</b> Kamar ini sedang berstatus <b>Terisi</b>. Mengubah harga atau fasilitas di sini tidak akan mengubah tagihan yang sudah terbit sebelumnya, tapi akan berlaku untuk tagihan siklus bulan berikutnya.</p>
                </div>
            <?php endif; ?>

            <form method="POST" id="formEditKamar">
                <div class="row g-4">
                    
                    <div class="col-md-6">
                        <label class="form-label">Lokasi Properti</label>
                        <div class="input-group-premium">
                            <div class="ig-icon"><i class="fa-solid fa-building"></i></div>
                            <select name="id_kost" class="form-select-custom" required>
                                <?php while($k = mysqli_fetch_assoc($q_kost)) : ?>
                                    <option value="<?= $k['id']; ?>" <?= ($k['id'] == $kamar['id_kost']) ? 'selected' : ''; ?>>
                                        <?= $k['kode_cabang'] ?? 'KST'; ?> - <?= $k['nama_kost']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nomor Unit / Kamar</label>
                        <div class="input-group-premium">
                            <div class="ig-icon"><i class="fa-solid fa-door-open"></i></div>
                            <input type="text" name="no_kamar" class="form-control-custom" value="<?= htmlspecialchars($kamar['no_kamar']); ?>" required autocomplete="off">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Harga Sewa per Bulan</label>
                        <div class="input-group-premium">
                            <div class="ig-icon fw-bold text-dark">Rp</div>
                            <input type="number" name="harga_perbulan" class="form-control-custom fw-bold text-primary fs-5" value="<?= htmlspecialchars($kamar['harga_perbulan']); ?>" required autocomplete="off">
                        </div>
                        <div class="form-text">Masukkan angka bulat tanpa titik (Misal: 1500000)</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status Ketersediaan</label>
                        <div class="input-group-premium">
                            <div class="ig-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                            <select name="status" class="form-select-custom" required>
                                <option value="Tersedia" <?= ($kamar['status'] == 'Tersedia') ? 'selected' : ''; ?>>Tersedia (Bisa Disewa)</option>
                                <option value="Terisi" <?= ($kamar['status'] == 'Terisi') ? 'selected' : ''; ?>>Terisi (Ada Penghuni)</option>
                                <option value="Perbaikan" <?= ($kamar['status'] == 'Perbaikan') ? 'selected' : ''; ?>>Dalam Perbaikan (Maintenance)</option>
                            </select>
                        </div>
                        <div class="form-text text-danger">Hati-hati mengubah status kamar secara manual!</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Fasilitas Unit (Dipisah Koma)</label>
                        <textarea name="fasilitas_kamar" class="textarea-custom" rows="4" required><?= htmlspecialchars($kamar['fasilitas_kamar']); ?></textarea>
                        <div class="form-text"><i class="fa-solid fa-circle-info me-1"></i> Contoh format: Kasur Springbed, Lemari Pakaian, Meja Kerja, AC, Jendela Luar</div>
                    </div>

                </div>

                <div class="btn-action-container">
                    <a href="dashboard.php" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" name="update_kamar" class="btn-save">
                        Simpan Pembaruan <i class="fa-solid fa-floppy-disk"></i>
                    </button>
                </div>
            </form>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form Submit Confirmation
        document.getElementById('formEditKamar').addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Simpan Perubahan?',
                text: "Pastikan data kamar sudah benar.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0F172A',
                cancelButtonColor: '#F1F5F9',
                confirmButtonText: 'Ya, Simpan',
                cancelButtonText: '<span class="text-dark fw-bold">Batal</span>',
                reverseButtons: true,
                customClass: { confirmButton: 'rounded-4 px-4 py-2 fw-bold', cancelButton: 'rounded-4 px-4 py-2' }
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    </script>

    <?php if($success_msg != "") : ?>
        <script>
            Swal.fire({ 
                icon: 'success', 
                title: 'Pembaruan Sukses', 
                text: '<?= $success_msg; ?>', 
                confirmButtonColor: '#0F172A', 
                customClass: { popup: 'rounded-4' }
            }).then(() => {
                window.location.href = window.location.href; // Refresh agar data terbaru tampil
            });
        </script>
    <?php endif; ?>
    
    <?php if($error_msg != "") : ?>
        <script>
            Swal.fire({ 
                icon: 'error', 
                title: 'Terjadi Kesalahan', 
                text: '<?= $error_msg; ?>', 
                confirmButtonColor: '#EF4444', 
                customClass: { popup: 'rounded-4' }
            });
        </script>
    <?php endif; ?>

</body>
</html>