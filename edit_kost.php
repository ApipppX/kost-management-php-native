<?php
session_start();
require 'koneksi.php';
cek_login();

// Keamanan: Hanya admin yang bisa mengedit properti
if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$error_msg = "";
$success_msg = "";

// 1. AMBIL DATA KOST BERDASARKAN ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: kost.php");
    exit;
}

$id_kost = mysqli_real_escape_string($conn, $_GET['id']);
$query_kost = mysqli_query($conn, "SELECT * FROM kost WHERE id = '$id_kost'");

if (mysqli_num_rows($query_kost) == 0) {
    echo "<script>alert('Data properti tidak ditemukan!'); window.location='kost.php';</script>";
    exit;
}

$data_kost = mysqli_fetch_assoc($query_kost);
$foto_lama = $data_kost['foto_bangunan'];

// Normalisasi nomor HP untuk form (MENGGUNAKAN no_telpon sesuai database)
$hp_raw = $data_kost['no_telpon'] ?? '';
$hp_display = preg_replace('/^(0|\+?62)/', '', $hp_raw);

// 2. PROSES UPDATE DATA
if (isset($_POST['update'])) {
    $nama_kost = mysqli_real_escape_string($conn, trim($_POST['nama_kost']));
    $kode_cabang = mysqli_real_escape_string($conn, strtoupper(trim($_POST['kode_cabang'])));
    $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat']));
    
    // Format nomor WhatsApp
    $raw_input_hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $no_hp_baru = '62' . ltrim($raw_input_hp, '0');

    $nama_file_baru = $foto_lama; // Default gunakan foto lama

    // Logika Upload Foto Baru (Jika Ada)
    if (isset($_FILES['foto_bangunan']) && $_FILES['foto_bangunan']['error'] === 0) {
        $file_foto = $_FILES['foto_bangunan'];
        
        if (!is_dir('uploads/kost')) mkdir('uploads/kost', 0777, true);

        $ext = strtolower(pathinfo($file_foto['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($ext, $allowed)) {
            $error_msg = "Format foto tidak valid. Gunakan JPG, PNG, atau WEBP.";
        } elseif ($file_foto['size'] > 3000000) {
            $error_msg = "Ukuran foto maksimal 3MB.";
        } else {
            // Generate nama file unik
            $nama_file_baru = "BLDG_" . $kode_cabang . "_" . time() . "." . $ext;
            
            if (move_uploaded_file($file_foto['tmp_name'], 'uploads/kost/' . $nama_file_baru)) {
                // Hapus foto fisik yang lama jika ada
                if (!empty($foto_lama) && file_exists('uploads/kost/' . $foto_lama)) {
                    unlink('uploads/kost/' . $foto_lama);
                }
            } else {
                $error_msg = "Gagal mengunggah foto bangunan.";
                $nama_file_baru = $foto_lama; // Kembalikan ke foto lama jika gagal
            }
        }
    }

    // Jika tidak ada error upload, lakukan proses UPDATE ke database
    if (empty($error_msg)) {
        // PERBAIKAN: Update ke kolom no_telpon
        $query_update = "UPDATE kost SET 
                            nama_kost = '$nama_kost', 
                            kode_cabang = '$kode_cabang', 
                            alamat = '$alamat', 
                            no_telpon = '$no_hp_baru', 
                            foto_bangunan = '$nama_file_baru' 
                         WHERE id = '$id_kost'";

        if (mysqli_query($conn, $query_update)) {
            $success_msg = "Data properti berhasil diperbarui!";
            // Update data array agar form langsung menampilkan data terbaru
            $data_kost['nama_kost'] = $nama_kost;
            $data_kost['kode_cabang'] = $kode_cabang;
            $data_kost['alamat'] = $alamat;
            $data_kost['foto_bangunan'] = $nama_file_baru;
            $hp_display = ltrim($raw_input_hp, '0');
        } else {
            $error_msg = "Gagal menyimpan ke database: " . mysqli_error($conn);
        }
    }
}

// Menyiapkan Tampilan Foto
$dummy_image = "https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?q=80&w=600&auto=format&fit=crop";
$foto_preview = (!empty($data_kost['foto_bangunan']) && file_exists('uploads/kost/' . $data_kost['foto_bangunan'])) 
                ? 'uploads/kost/' . $data_kost['foto_bangunan'] 
                : $dummy_image;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Properti | KostAdmin Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --primary: #4F46E5; --primary-hover: #4338CA; --bg: #F8FAFC; --text-main: #0F172A; --text-muted: #64748B; --border: #E2E8F0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); }
        
        .container-app { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        
        /* HEADER */
        .page-header { margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .btn-back { background: white; border: 1px solid var(--border); width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 12px; color: var(--text-muted); text-decoration: none; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .btn-back:hover { background: #F1F5F9; color: var(--text-main); }
        .header-title { font-weight: 800; font-size: 1.5rem; letter-spacing: -0.5px; margin: 0; }
        
        /* FORM CARD */
        .form-card { background: white; border-radius: 24px; border: 1px solid var(--border); padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        
        /* PHOTO UPLOADER */
        .photo-uploader { position: relative; width: 100%; height: 220px; border-radius: 16px; overflow: hidden; margin-bottom: 25px; border: 2px dashed var(--border); transition: 0.3s; background: #F8FAFC; }
        .photo-uploader:hover { border-color: var(--primary); }
        .preview-img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .upload-overlay { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.5); display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; cursor: pointer; color: white; }
        .photo-uploader:hover .upload-overlay { opacity: 1; }
        .upload-overlay i { font-size: 2rem; margin-bottom: 10px; }
        .upload-overlay span { font-weight: 700; font-size: 0.9rem; }
        
        /* INPUTS */
        .form-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .form-control { border-radius: 12px; border: 1.5px solid var(--border); padding: 12px 16px; font-weight: 600; color: var(--text-main); transition: 0.2s; background: #F8FAFC; }
        .form-control:focus { background: white; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        
        /* +62 Prefix */
        .input-group-text.wa-prefix { background: white; border-color: var(--border); border-right: none; color: var(--text-main); font-weight: 800; border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .input-group .form-control.wa-input { border-left: none; padding-left: 0; background: white; }
        .input-group:focus-within .wa-prefix { border-color: var(--primary); }
        input[type="number"]::-webkit-outer-spin-button, input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type="number"] { -moz-appearance: textfield; }

        /* BUTTONS */
        .btn-action { background: var(--primary); color: white; border: none; border-radius: 12px; padding: 14px 28px; font-weight: 800; transition: 0.3s; }
        .btn-action:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(79, 70, 229, 0.25); }
        .btn-cancel { background: white; color: var(--text-main); border: 1px solid var(--border); border-radius: 12px; padding: 14px 28px; font-weight: 700; transition: 0.2s; text-decoration: none; }
        .btn-cancel:hover { background: #F1F5F9; color: var(--text-main); }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="page-header">
            <a href="kost.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h1 class="header-title">Edit Detail Properti</h1>
                <p class="text-muted small fw-600 mb-0">Perbarui informasi cabang kost.</p>
            </div>
        </div>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label">Foto Utama Bangunan</label>
                        <div class="photo-uploader" onclick="document.getElementById('fileInput').click();">
                            <img src="<?= $foto_preview; ?>" id="imgPreview" class="preview-img" alt="Preview Bangunan">
                            <div class="upload-overlay">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Klik untuk Ganti Foto</span>
                            </div>
                        </div>
                        <input type="file" name="foto_bangunan" id="fileInput" class="d-none" accept=".jpg, .jpeg, .png, .webp" onchange="previewImage(event)">
                        <small class="text-muted fw-bold d-block text-center" style="font-size: 0.7rem;">Rekomendasi rasio lanskap (16:9). Maks 3MB.</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Kode Cabang <span class="text-danger">*</span></label>
                        <input type="text" name="kode_cabang" class="form-control" value="<?= htmlspecialchars($data_kost['kode_cabang'] ?? ''); ?>" placeholder="Contoh: BSD-01" required>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Nama Properti / Gedung <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kost" class="form-control" value="<?= htmlspecialchars($data_kost['nama_kost']); ?>" placeholder="Contoh: Kost Grha Serpong" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                        <textarea name="alamat" class="form-control" rows="2" placeholder="Masukkan alamat lengkap properti..." required><?= htmlspecialchars($data_kost['alamat']); ?></textarea>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Kontak Pengelola (WhatsApp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text wa-prefix"><i class="fa-brands fa-whatsapp text-success me-2 fs-6"></i> +62</span>
                            <input type="number" name="no_hp" class="form-control wa-input" value="<?= $hp_display; ?>" placeholder="81234567890" required>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-5 pt-4 border-top">
                    <a href="kost.php" class="btn-cancel">Batal</a>
                    <button type="submit" name="update" class="btn-action">
                        <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Perubahan
                    </button>
                </div>

            </form>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Fitur Live Image Preview
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('imgPreview');
                output.src = reader.result;
            };
            if(event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }
    </script>

    <?php if($success_msg != "") : ?>
        <script>
            Swal.fire({ 
                icon: 'success', 
                title: 'Diperbarui!', 
                text: '<?= $success_msg; ?>', 
                confirmButtonColor: '#4F46E5',
                confirmButtonText: 'Kembali ke Daftar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'kost.php';
                }
            });
        </script>
    <?php endif; ?>
    
    <?php if($error_msg != "") : ?>
        <script>
            Swal.fire({ 
                icon: 'error', 
                title: 'Gagal Menyimpan', 
                text: '<?= $error_msg; ?>',
                confirmButtonColor: '#0F172A'
            });
        </script>
    <?php endif; ?>

</body>
</html>