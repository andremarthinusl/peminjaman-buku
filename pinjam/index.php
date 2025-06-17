<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Periksa apakah pengguna memiliki akses ke halaman ini (Hanya Admin dan Pustakawan)
requireAccess([1, 2]);

// Ambil status filter
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    // Count total loans with filter and search
    $count_sql = "SELECT COUNT(*) as total FROM meminjam m
                 JOIN anggota a ON m.id_anggota = a.id_anggota
                 JOIN buku b ON m.id_buku = b.id_buku";
    
    $where_clauses = [];
    
    if(!empty($filter)) {
        $where_clauses[] = "m.status = :filter";
    }
    
    if(!empty($search)) {
        $where_clauses[] = "(a.nama LIKE :search 
                          OR a.kode_anggota LIKE :search 
                          OR b.judul_buku LIKE :search 
                          OR b.kode_buku LIKE :search)";
    }
    
    if(!empty($where_clauses)) {
        $count_sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $count_stmt = $conn->prepare($count_sql);
    
    if(!empty($filter)) {
        $count_stmt->bindParam(':filter', $filter);
    }
    
    if(!empty($search)) {
        $search_param = "%{$search}%";
        $count_stmt->bindParam(':search', $search_param);
    }
    
    $count_stmt->execute();
    $total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $per_page);
      // Ambil data peminjaman dengan paginasi, filter, dan pencarian
    $sql = "SELECT m.*, a.nama as nama_anggota, a.kode_anggota, 
            b.judul_buku, b.kode_buku 
            FROM meminjam m
            JOIN anggota a ON m.id_anggota = a.id_anggota
            JOIN buku b ON m.id_buku = b.id_buku";
    
    if(!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " ORDER BY m.id_pinjam ASC LIMIT :offset, :per_page";
    
    $stmt = $conn->prepare($sql);
    
    if(!empty($filter)) {
        $stmt->bindParam(':filter', $filter);
    }
    
    if(!empty($search)) {
        $search_param = "%{$search}%";
        $stmt->bindParam(':search', $search_param);
    }
    
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil jumlah status peminjaman
    $status_counts_sql = "SELECT status, COUNT(*) as count FROM meminjam GROUP BY status";
    $status_counts_stmt = $conn->query($status_counts_sql);
    $status_counts = [];
    
    while($row = $status_counts_stmt->fetch(PDO::FETCH_ASSOC)) {
        $status_counts[$row['status']] = $row['count'];
    }
    
    $total_dipinjam = $status_counts['dipinjam'] ?? 0;
    $total_kembali = $status_counts['kembali'] ?? 0;
    $total_terlambat = $status_counts['terlambat'] ?? 0;
    $total_loans = $total_dipinjam + $total_kembali + $total_terlambat;
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Include header
include '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-book-reader mr-2 text-indigo-600"></i> Data Peminjaman
        </h1>
        <div class="mt-4 md:mt-0">
            <a href="tambah.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Tambah Peminjaman
            </a>
        </div>
    </div>
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded alert alert-auto-close" role="alert">
            <p><?= $_SESSION['success'] ?></p>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded alert alert-auto-close" role="alert">
            <p><?= $_SESSION['error'] ?></p>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- Status Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <a href="index.php" class="block">
            <div class="bg-white rounded-lg shadow-md p-5 border-t-4 <?= empty($filter) ? 'border-indigo-500' : 'border-gray-200' ?> hover:shadow-lg transition duration-300">
                <div class="flex justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm">Semua Peminjaman</h3>
                        <p class="text-2xl font-bold text-gray-800"><?= $total_loans ?></p>
                    </div>
                    <div class="rounded-full bg-indigo-100 p-3">
                        <i class="fas fa-list text-indigo-500 text-xl"></i>
                    </div>
                </div>
            </div>
        </a>
        
        <a href="?filter=dipinjam<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="block">
            <div class="bg-white rounded-lg shadow-md p-5 border-t-4 <?= $filter === 'dipinjam' ? 'border-yellow-500' : 'border-gray-200' ?> hover:shadow-lg transition duration-300">
                <div class="flex justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm">Sedang Dipinjam</h3>
                        <p class="text-2xl font-bold text-gray-800"><?= $total_dipinjam ?></p>
                    </div>
                    <div class="rounded-full bg-yellow-100 p-3">
                        <i class="fas fa-book-reader text-yellow-500 text-xl"></i>
                    </div>
                </div>
            </div>
        </a>
        
        <a href="?filter=kembali<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="block">
            <div class="bg-white rounded-lg shadow-md p-5 border-t-4 <?= $filter === 'kembali' ? 'border-green-500' : 'border-gray-200' ?> hover:shadow-lg transition duration-300">
                <div class="flex justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm">Sudah Dikembalikan</h3>
                        <p class="text-2xl font-bold text-gray-800"><?= $total_kembali ?></p>
                    </div>
                    <div class="rounded-full bg-green-100 p-3">
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    </div>
                </div>
            </div>
        </a>
        
        <a href="?filter=terlambat<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="block">
            <div class="bg-white rounded-lg shadow-md p-5 border-t-4 <?= $filter === 'terlambat' ? 'border-red-500' : 'border-gray-200' ?> hover:shadow-lg transition duration-300">
                <div class="flex justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm">Terlambat</h3>
                        <p class="text-2xl font-bold text-gray-800"><?= $total_terlambat ?></p>
                    </div>
                    <div class="rounded-full bg-red-100 p-3">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <!-- Search and Filter -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="" method="GET" class="flex flex-col md:flex-row md:items-center">
            <?php if(!empty($filter)): ?>
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <?php endif; ?>
            
            <div class="flex-grow mr-0 md:mr-4 mb-4 md:mb-0">
                <div class="relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari peminjaman..." class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php if(!empty($search)): ?>
                        <a href="index.php<?= !empty($filter) ? '?filter=' . urlencode($filter) : '' ?>" class="absolute right-3 top-2.5 text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                <i class="fas fa-search mr-2"></i> Cari
            </button>
        </form>
    </div>
    
    <!-- Loans Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">                
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Pinjam</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Kembali</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Anggota</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buku</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(count($loans) > 0): ?>
                        <?php 
                        $no = ($page - 1) * $per_page + 1;
                        foreach($loans as $loan): 
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $no++ ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('d/m/Y', strtotime($loan['tgl_pinjam'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $loan['tgl_kembali'] ? date('d/m/Y', strtotime($loan['tgl_kembali'])) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div><?= htmlspecialchars($loan['nama_anggota']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($loan['kode_anggota']) ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div><?= htmlspecialchars($loan['judul_buku']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($loan['kode_buku']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if($loan['status'] == 'dipinjam'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Dipinjam
                                        </span>
                                    <?php elseif($loan['status'] == 'kembali'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Dikembalikan
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Terlambat
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="detail.php?id=<?= $loan['id_pinjam'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if($loan['status'] == 'dipinjam'): ?>
                                        <a href="kembalikan.php?id=<?= $loan['id_pinjam'] ?>" class="text-green-600 hover:text-green-900 mr-3">
                                            <i class="fas fa-check-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                        
                                    <?php if($loan['status'] != 'kembali'): ?>
                                        <a href="edit.php?id=<?= $loan['id_pinjam'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="javascript:void(0);" onclick="confirmDelete(<?= $loan['id_pinjam'] ?>)" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                <?php 
                                if(!empty($search)) {
                                    echo 'Tidak ada hasil yang cocok dengan pencarian Anda.';
                                } elseif(!empty($filter)) {
                                    echo 'Tidak ada data peminjaman dengan status ' . ucfirst($filter) . '.';
                                } else {
                                    echo 'Belum ada data peminjaman.';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-700">
                            Menampilkan
                            <span class="font-medium"><?= min(($page - 1) * $per_page + 1, $total_rows) ?></span>
                            sampai
                            <span class="font-medium"><?= min($page * $per_page, $total_rows) ?></span>
                            dari
                            <span class="font-medium"><?= $total_rows ?></span>
                            hasil
                        </p>
                    </div>
                    <nav class="flex justify-end">
                        <ul class="flex pl-0 list-none rounded my-2">
                            <?php if($page > 1): ?>
                                <li>
                                    <a href="?page=<?= $page - 1 ?><?= !empty($filter) ? '&filter=' . urlencode($filter) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l hover:bg-gray-100 hover:text-gray-700">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if($start_page > 1) {
                                echo '<li><a href="?page=1' . (!empty($filter) ? '&filter=' . urlencode($filter) : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">1</a></li>';
                                if($start_page > 2) {
                                    echo '<li><span class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300">...</span></li>';
                                }
                            }
                            
                            for($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li>';
                                if($i == $page) {
                                    echo '<span class="px-3 py-2 ml-0 leading-tight text-white bg-indigo-600 border border-indigo-600">' . $i . '</span>';
                                } else {
                                    echo '<a href="?page=' . $i . (!empty($filter) ? '&filter=' . urlencode($filter) : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">' . $i . '</a>';
                                }
                                echo '</li>';
                            }
                            
                            if($end_page < $total_pages) {
                                if($end_page < $total_pages - 1) {
                                    echo '<li><span class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300">...</span></li>';
                                }
                                echo '<li><a href="?page=' . $total_pages . (!empty($filter) ? '&filter=' . urlencode($filter) : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <?php if($page < $total_pages): ?>
                                <li>
                                    <a href="?page=<?= $page + 1 ?><?= !empty($filter) ? '&filter=' . urlencode($filter) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r hover:bg-gray-100 hover:text-gray-700">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Data Peminjaman</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">Apakah Anda yakin ingin menghapus data peminjaman ini? Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">                <a id="confirmDeleteBtn" href="#" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Hapus
                </a>
                <button type="button" onclick="closeDeleteModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id) {
        document.getElementById('confirmDeleteBtn').href = 'delete.php?id=' + id;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
</script>

<?php include '../templates/footer.php'; ?>








































