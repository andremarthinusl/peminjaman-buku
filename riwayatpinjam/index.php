<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// All roles can access riwayat peminjaman, but Anggota can only see their own data

// Ambil status filter
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    // Base query parts
    $select_query = "SELECT m.id_pinjam, m.tgl_pinjam, m.tgl_kembali, m.status, m.id_anggota,
                   a.nama as nama_anggota, a.kode_anggota,
                   b.judul_buku, b.kode_buku, b.id_buku
                   FROM meminjam m
                   JOIN anggota a ON m.id_anggota = a.id_anggota
                   JOIN buku b ON m.id_buku = b.id_buku";
    
    $count_query = "SELECT COUNT(*) as total FROM meminjam m
                   JOIN anggota a ON m.id_anggota = a.id_anggota
                   JOIN buku b ON m.id_buku = b.id_buku";
                   
    $where_clauses = [];
    
    // Apply filter if provided
    if(!empty($filter)) {
        $where_clauses[] = "m.status = :filter";
    }
    
    // Apply search if provided
    if(!empty($search)) {
        $where_clauses[] = "(a.nama LIKE :search 
                          OR b.judul_buku LIKE :search 
                          OR b.kode_buku LIKE :search
                          OR a.kode_anggota LIKE :search)";
    }    // For Anggota role, show only their own data
    if($_SESSION['role'] == 3) { // Anggota
        // Use name matching instead of user_id
        $nama = $_SESSION['nama'];
        
        // Ambil ID anggota untuk pengguna ini
        $anggota_query = "SELECT id_anggota FROM anggota WHERE nama = :nama";
        $anggota_stmt = $conn->prepare($anggota_query);
        $anggota_stmt->bindParam(':nama', $nama);
        $anggota_stmt->execute();
        $anggota_result = $anggota_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($anggota_result) {
            $anggota_id = $anggota_result['id_anggota'];
            $where_clauses[] = "m.id_anggota = :id_anggota";
        } else {
            // If the user doesn't have an anggota record, show no results
            $where_clauses[] = "1 = 0";
        }
    }
    
    // Combine WHERE clauses if any
    if(count($where_clauses) > 0) {
        $select_query .= " WHERE " . implode(" AND ", $where_clauses);
        $count_query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // Finalize queries
    $select_query .= " ORDER BY m.tgl_pinjam DESC LIMIT :offset, :per_page";
      // Prepare and execute count query
    $count_stmt = $conn->prepare($count_query);
    if(!empty($filter)) {
        $count_stmt->bindParam(':filter', $filter);
    }
    if(!empty($search)) {
        $search_param = "%{$search}%";
        $count_stmt->bindParam(':search', $search_param);
    }
    if($_SESSION['role'] == 3 && isset($anggota_id)) {
        $count_stmt->bindParam(':id_anggota', $anggota_id, PDO::PARAM_INT);
    }
    $count_stmt->execute();
    $total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $per_page);
    
    // Prepare and execute select query with pagination
    $stmt = $conn->prepare($select_query);
    if(!empty($filter)) {
        $stmt->bindParam(':filter', $filter);
    }
    if(!empty($search)) {
        $search_param = "%{$search}%";
        $stmt->bindParam(':search', $search_param);
    }    if($_SESSION['role'] == 3 && isset($anggota_id)) {
        $stmt->bindParam(':id_anggota', $anggota_id, PDO::PARAM_INT);
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $riwayat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Include header
include '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4 md:mb-0">
            <i class="fas fa-history mr-2 text-indigo-600"></i> 
            <?= $_SESSION['role'] == 3 ? 'Riwayat Peminjaman Anda' : 'Riwayat Peminjaman' ?>
        </h1>
        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2 w-full md:w-auto">
            <div class="flex-grow">
                <form method="get" class="flex">
                    <select name="filter" class="rounded-l-lg border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        <option value="" <?= $filter == '' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="dipinjam" <?= $filter == 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                        <option value="kembali" <?= $filter == 'kembali' ? 'selected' : '' ?>>Dikembalikan</option>
                        <option value="terlambat" <?= $filter == 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                    </select>
                    <div class="relative flex-grow">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari..." class="w-full rounded-r-lg border border-l-0 border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        <button type="submit" class="absolute right-0 top-0 h-full px-3 bg-gray-100 border-l border-gray-300 rounded-r-lg text-gray-600 hover:bg-gray-200">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php if($_SESSION['role'] != 3): // Not for regular members ?>
            <a href="../pinjam/tambah.php" class="bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 text-center">
                <i class="fas fa-plus mr-1"></i> Tambah Peminjaman
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Peminjaman Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <?php if(isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?= $error ?></p>
            </div>
        <?php endif; ?>
        
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-4 text-left">ID</th>
                        <th class="py-3 px-4 text-left">Tgl Pinjam</th>
                        <th class="py-3 px-4 text-left">Tgl Kembali</th>
                        <?php if($_SESSION['role'] != 3): // Not for regular members ?>
                        <th class="py-3 px-4 text-left">Anggota</th>
                        <?php endif; ?>
                        <th class="py-3 px-4 text-left">Buku</th>
                        <th class="py-3 px-4 text-left">Status</th>
                        <?php if(checkAccess([1, 2])): // Admin and Pustakawan only ?>
                        <th class="py-3 px-4 text-center">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm">
                    <?php if(count($riwayat_list) > 0): ?>
                        <?php foreach($riwayat_list as $row): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4"><?= $row['id_pinjam'] ?></td>
                                <td class="py-3 px-4"><?= date('d/m/Y', strtotime($row['tgl_pinjam'])) ?></td>
                                <td class="py-3 px-4">
                                    <?= $row['tgl_kembali'] ? date('d/m/Y', strtotime($row['tgl_kembali'])) : '-' ?>
                                </td>
                                <?php if($_SESSION['role'] != 3): // Not for regular members ?>
                                <td class="py-3 px-4">
                                    <span class="font-medium"><?= $row['kode_anggota'] ?></span>
                                    <div class="text-xs text-gray-500"><?= $row['nama_anggota'] ?></div>
                                </td>
                                <?php endif; ?>
                                <td class="py-3 px-4">
                                    <span class="font-medium"><?= $row['kode_buku'] ?></span>
                                    <div class="text-xs text-gray-500"><?= $row['judul_buku'] ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if($row['status'] == 'dipinjam'): ?>
                                        <span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i> Dipinjam
                                        </span>
                                    <?php elseif($row['status'] == 'kembali'): ?>
                                        <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i> Kembali
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-800">
                                            <i class="fas fa-exclamation-circle mr-1"></i> Terlambat
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <?php if(checkAccess([1, 2])): // Admin and Pustakawan only ?>
                                <td class="py-3 px-4 text-center">
                                    <?php if($row['status'] != 'kembali'): ?>
                                    <a href="../pinjam/kembali.php?id=<?= $row['id_pinjam'] ?>" class="bg-green-500 text-white px-3 py-1 rounded text-xs hover:bg-green-600">
                                        <i class="fas fa-check mr-1"></i> Kembalikan
                                    </a>
                                    <?php endif; ?>
                                    <a href="../pinjam/detail.php?id=<?= $row['id_pinjam'] ?>" class="bg-blue-500 text-white px-3 py-1 rounded text-xs hover:bg-blue-600">
                                        <i class="fas fa-eye mr-1"></i> Detail
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $_SESSION['role'] != 3 ? '7' : '5' ?>" class="py-4 px-4 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-search text-gray-400 text-4xl mb-3"></i>
                                    <p class="text-gray-500">Tidak ada data peminjaman ditemukan</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="px-4 py-3 bg-white border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="hidden sm:block">
                    <p class="text-sm text-gray-700">
                        Menampilkan <?= min(($page - 1) * $per_page + 1, $total_rows) ?> - <?= min($page * $per_page, $total_rows) ?> dari <?= $total_rows ?> data
                    </p>
                </div>
                <div class="flex-1 flex justify-center sm:justify-end">
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm">
                        <?php if($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if($start_page > 1): ?>
                        <a href="?page=1&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            1
                        </a>
                        <?php if($start_page > 2): ?>
                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                            ...
                        </span>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i == $page ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if($end_page < $total_pages): ?>
                        <?php if($end_page < $total_pages - 1): ?>
                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                            ...
                        </span>
                        <?php endif; ?>
                        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <?= $total_pages ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../templates/footer.php'; ?>








































