<?php
session_start();
require 'koneksi.php';
cek_login();

if ($_SESSION['role'] != 'user') {
    header("Location: dashboard.php");
    exit;
}

$username = $_SESSION['username'];

// Ambil ID Penghuni
$q_u = mysqli_query($conn, "SELECT id_penghuni FROM users WHERE username = '$username'");
$u = mysqli_fetch_assoc($q_u);
$id_p = $u['id_penghuni'];

// FITUR: Tandai Semua Sebagai Dibaca
if (isset($_GET['action']) && $_GET['action'] == 'read_all' && $id_p) {
    mysqli_query($conn, "UPDATE notifikasi SET is_read = '1' WHERE id_penghuni = '$id_p'");
    header("Location: notifikasi.php");
    exit;
}

$notif_data = [];
$unread_total = 0;
if ($id_p) {
    $q_notif = mysqli_query($conn, "SELECT * FROM notifikasi WHERE id_penghuni = '$id_p' ORDER BY created_at DESC");
    if ($q_notif) {
        while ($row = mysqli_fetch_assoc($q_notif)) {
            if ($row['is_read'] == '0') $unread_total++;
            $row['is_new'] = ($row['is_read'] == '0');
            $notif_data[] = $row;
        }
    }
    // Update status dibaca saat halaman dibuka
    mysqli_query($conn, "UPDATE notifikasi SET is_read = '1' WHERE id_penghuni = '$id_p' AND is_read = '0'");
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "Baru saja";
    if ($diff < 3600) return floor($diff / 60) . " mnt lalu";
    if ($diff < 86400) return floor($diff / 3600) . " jam lalu";
    return date('d M Y', $time);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi | <?= $_SESSION['username']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #4F46E5; --bg: #F8FAFC; --surface: #ffffff; --text-main: #0F172A; --text-muted: #64748B; --border: #E2E8F0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-main); }
        .container-app { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        /* HEADER SECTION (SELARAS DENGAN BROADCAST) */
        .page-header-wrapper { display: flex; align-items: center; gap: 20px; margin-bottom: 35px; }
        .brand-icon-box { 
            width: 56px; height: 56px; background: #1E293B; color: white; 
            border-radius: 16px; display: flex; align-items: center; 
            justify-content: center; font-size: 1.5rem; flex-shrink: 0;
            box-shadow: 0 10px 20px rgba(0,0,0,0.06);
        }
        .page-title { font-weight: 800; font-size: 1.8rem; letter-spacing: -1px; margin-bottom: 2px; color: var(--text-main); }
        .page-subtitle { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin: 0; }

        /* ACTION BAR & FILTER */
        .action-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
        .search-box { position: relative; flex-grow: 1; max-width: 400px; }
        .search-box i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-input { width: 100%; border-radius: 14px; border: 1.5px solid var(--border); padding: 11px 20px 11px 48px; font-weight: 600; font-size: 0.9rem; background: white; transition: 0.3s; }
        .search-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }

        .btn-filter-pill { background: white; border: 1px solid var(--border); color: var(--text-muted); font-size: 0.8rem; font-weight: 700; padding: 10px 20px; border-radius: 50px; transition: 0.3s; }
        .btn-filter-pill.active { background: var(--text-main); color: white; border-color: var(--text-main); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15); }

        /* LIST NOTIFIKASI */
        .notif-list { display: flex; flex-direction: column; gap: 16px; }
        .msg-card { 
            background: var(--surface); border-radius: 24px; border: 1px solid var(--border); 
            padding: 24px; transition: 0.3s; display: flex; gap: 20px; position: relative;
        }
        .msg-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px -10px rgba(0,0,0,0.05); border-color: #CBD5E1; }
        .msg-card.is-new { border-left: 5px solid var(--primary); background: linear-gradient(to right, #ffffff, #F8FAFF); }

        .icon-wrapper { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .ic-tagihan { background: #FFFBEB; color: #D97706; }
        .ic-pengumuman { background: #EEF2FF; color: var(--primary); }
        .ic-sistem { background: #F1F5F9; color: #475569; }

        .msg-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; }
        .msg-title { font-weight: 700; font-size: 1.05rem; color: var(--text-main); margin: 0; }
        .msg-time { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); }
        .msg-body { font-size: 0.92rem; color: #475569; line-height: 1.6; margin: 0; white-space: pre-wrap; }

        .btn-read-all { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 8px 16px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; transition: 0.2s; text-decoration: none; }
        .btn-read-all:hover { background: #EEF2FF; color: var(--primary); border-color: var(--primary); }

        .empty-state { text-align: center; padding: 80px 20px; border: 2px dashed var(--border); border-radius: 24px; }
    </style>
</head>
<body>
    
    <?php include 'navbar.php'; ?>

    <div class="container-app">
        
        <div class="page-header-wrapper">
            <div class="brand-icon-box">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div class="flex-grow-1">
                <h1 class="page-title">Notifikasi & Pesan</h1>
                <p class="page-subtitle">Informasi terbaru mengenai unit dan tagihan dari Manajemen Properti.</p>
            </div>
            <div class="d-none d-md-block">
                <a href="notifikasi.php?action=read_all" class="btn-read-all">
                    <i class="fa-solid fa-check-double me-1"></i> Bersihkan
                </a>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchNotif" class="search-input" placeholder="Cari isi pesan...">
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-pill active" data-filter="all">Semua</button>
                <button class="btn-filter-pill" data-filter="Tagihan">Tagihan</button>
                <button class="btn-filter-pill" data-filter="Pengumuman">Pengumuman</button>
            </div>
        </div>

        <div class="notif-list" id="notifContainer">
            <?php if(count($notif_data) > 0): ?>
                <?php foreach($notif_data as $n): 
                    $ic_class = 'ic-' . strtolower($n['tipe']);
                    $ic_fa = $n['tipe'] == 'Tagihan' ? 'fa-file-invoice-dollar' : ($n['tipe'] == 'Sistem' ? 'fa-shield-halved' : 'fa-bullhorn');
                ?>
                    <div class="msg-card <?= $n['is_new'] ? 'is-new' : ''; ?>" data-category="<?= $n['tipe']; ?>">
                        <div class="icon-wrapper <?= $ic_class; ?>">
                            <i class="fa-solid <?= $ic_fa; ?>"></i>
                        </div>

                        <div class="msg-content w-100">
                            <div class="msg-top">
                                <h4 class="msg-title"><?= htmlspecialchars($n['judul']); ?></h4>
                                <span class="msg-time"><?= timeAgo($n['created_at']); ?></span>
                            </div>
                            <p class="msg-body"><?= htmlspecialchars($n['pesan']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-envelope-open-text fs-1 text-muted opacity-20 mb-3"></i>
                    <h5 class="fw-bold text-dark">Belum ada pesan</h5>
                    <p class="text-muted small">Semua pemberitahuan akan muncul di sini.</p>
                </div>
            <?php endif; ?>
            
            <div id="filterEmpty" class="empty-state" style="display: none;">
                <i class="fa-solid fa-filter-circle-xmark fs-1 text-muted opacity-20 mb-3"></i>
                <h5 class="fw-bold text-dark">Tidak ditemukan</h5>
                <p class="text-muted small">Pesan dikategori ini tidak tersedia.</p>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filterBtns = document.querySelectorAll('.btn-filter-pill');
            const searchInput = document.getElementById('searchNotif');
            const cards = document.querySelectorAll('.msg-card');
            const emptyHint = document.getElementById('filterEmpty');

            function applyFilter() {
                const searchValue = searchInput.value.toLowerCase();
                const activeFilter = document.querySelector('.btn-filter-pill.active').getAttribute('data-filter');
                let count = 0;

                cards.forEach(card => {
                    const text = card.innerText.toLowerCase();
                    const category = card.getAttribute('data-category');
                    
                    const matchSearch = text.includes(searchValue);
                    const matchFilter = activeFilter === 'all' || category === activeFilter;

                    if(matchSearch && matchFilter) {
                        card.style.display = 'flex';
                        count++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                emptyHint.style.display = (count === 0) ? 'block' : 'none';
            }

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    applyFilter();
                });
            });

            searchInput.addEventListener('input', applyFilter);
        });
    </script>
</body>
</html>