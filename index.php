<?php
declare(strict_types=1);

session_start();

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rupiah(float|int|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function formatTanggalIndo(?string $dateStr, bool $withDay = false, bool $shortMonth = false): string
{
    if (!$dateStr) {
        return '-';
    }
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) {
        return $dateStr;
    }

    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $monthsLong = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $monthsShort = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];

    $dayNum = (int) date('w', $timestamp);
    $d = date('j', $timestamp);
    $m = (int) date('n', $timestamp);
    $y = date('Y', $timestamp);

    $monthName = $shortMonth ? ($monthsShort[$m] ?? '') : ($monthsLong[$m] ?? '');
    $formatted = "{$d} {$monthName} {$y}";

    if ($withDay) {
        $dayName = $days[$dayNum] ?? '';
        return "{$dayName}, {$formatted}";
    }

    return $formatted;
}

function flowerIcon(string $name): string
{
    $lower = mb_strtolower($name);
    if (str_contains($lower, 'mawar') || str_contains($lower, 'rose')) {
        return '🌹';
    }
    if (str_contains($lower, 'tulip')) {
        return '🌷';
    }
    if (str_contains($lower, 'matahari') || str_contains($lower, 'sunflower')) {
        return '🌻';
    }
    if (str_contains($lower, 'lili') || str_contains($lower, 'lily') || str_contains($lower, 'daisy')) {
        return '🌼';
    }
    if (str_contains($lower, 'anggrek') || str_contains($lower, 'orchid') || str_contains($lower, 'lavender')) {
        return '🪻';
    }
    if (str_contains($lower, 'peony') || str_contains($lower, 'sakura') || str_contains($lower, 'cherry')) {
        return '🌸';
    }
    if (str_contains($lower, 'kaktus') || str_contains($lower, 'succulent')) {
        return '🌵';
    }
    return '💐';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$errorMessage = null;
$dbError = null;
$pdo = null;
$sales = [];
$summary = ['transactions' => 0, 'revenue' => 0, 'bouquets' => 0, 'today' => 0];
$search = trim((string) ($_GET['q'] ?? ''));
$selectedDate = trim((string) ($_GET['date'] ?? ''));
$selectedPayment = trim((string) ($_GET['payment'] ?? ''));
$selectedSort = trim((string) ($_GET['sort'] ?? 'date_desc'));
$editingSale = null;

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $exception) {
    error_log('Florist database connection error: ' . $exception->getMessage());
    $dbError = 'Tidak dapat terhubung ke database. Pastikan layanan MySQL/MariaDB berjalan dan database florist_db tersedia.';
}

if ($pdo) {
    $sortSql = match ($selectedSort) {
        'date_asc' => 'sale_date ASC, id ASC',
        'total_desc' => '(quantity * unit_price) DESC, id DESC',
        'total_asc' => '(quantity * unit_price) ASC, id ASC',
        'name_asc' => 'customer_name ASC, id DESC',
        default => 'sale_date DESC, id DESC',
    };

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $exportConditions = [];
        $exportParameters = [];
        if ($search !== '') {
            $exportConditions[] = '(customer_name LIKE :search_customer OR flower_name LIKE :search_flower OR payment_method LIKE :search_payment)';
            $safeSearch = '%' . addcslashes($search, '%_\\') . '%';
            $exportParameters['search_customer'] = $safeSearch;
            $exportParameters['search_flower'] = $safeSearch;
            $exportParameters['search_payment'] = $safeSearch;
        }
        if ($selectedDate !== '' && DateTime::createFromFormat('Y-m-d', $selectedDate)) {
            $exportConditions[] = 'sale_date = :sale_date';
            $exportParameters['sale_date'] = $selectedDate;
        }
        if ($selectedPayment !== '' && in_array($selectedPayment, ['Tunai', 'Transfer', 'QRIS', 'Kartu'], true)) {
            $exportConditions[] = 'payment_method = :export_payment';
            $exportParameters['export_payment'] = $selectedPayment;
        }
        $exportWhere = $exportConditions ? 'WHERE ' . implode(' AND ', $exportConditions) : '';
        $exportStmt = $pdo->prepare("SELECT * FROM sales {$exportWhere} ORDER BY {$sortSql}");
        $exportStmt->execute($exportParameters);
        $exportRows = $exportStmt->fetchAll();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="laporan_penjualan_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // Add UTF-8 BOM for Microsoft Excel compatibility
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['No', 'No Faktur', 'Tanggal', 'Nama Pelanggan', 'Rangkaian Bunga', 'Jumlah (Item)', 'Harga Satuan (Rp)', 'Total (Rp)', 'Metode Pembayaran', 'Catatan']);

        $no = 1;
        foreach ($exportRows as $row) {
            $total = (int) $row['quantity'] * (int) $row['unit_price'];
            fputcsv($out, [
                $no++,
                'INV-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT),
                $row['sale_date'],
                $row['customer_name'],
                $row['flower_name'],
                $row['quantity'],
                $row['unit_price'],
                $total,
                $row['payment_method'],
                $row['notes'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $submittedToken = (string) ($_POST['csrf_token'] ?? '');
            if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
                http_response_code(403);
                throw new RuntimeException('Sesi formulir tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman, lalu coba kembali.');
            }

            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'delete') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new RuntimeException('Data penjualan yang akan dihapus tidak valid.');
                }
                $statement = $pdo->prepare('DELETE FROM sales WHERE id = :id');
                $statement->execute(['id' => $id]);
                $_SESSION['flash'] = $statement->rowCount() > 0
                    ? ['type' => 'success', 'message' => 'Data penjualan berhasil dihapus.']
                    : ['type' => 'error', 'message' => 'Data penjualan tidak ditemukan.'];
                header('Location: index.php');
                exit;
            } elseif ($action === 'save') {
                $id = trim((string) ($_POST['id'] ?? ''));
                $customer = trim((string) ($_POST['customer_name'] ?? ''));
                $flower = trim((string) ($_POST['flower_name'] ?? ''));
                $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
                $unitPrice = filter_var($_POST['unit_price'] ?? null, FILTER_VALIDATE_INT);
                $saleDate = trim((string) ($_POST['sale_date'] ?? ''));
                $paymentMethod = trim((string) ($_POST['payment_method'] ?? ''));
                $notes = trim((string) ($_POST['notes'] ?? ''));

                $validDate = DateTime::createFromFormat('Y-m-d', $saleDate);
                $allowedPayments = ['Tunai', 'Transfer', 'QRIS', 'Kartu'];
                if ($customer === '' || mb_strlen($customer) > 100
                    || $flower === '' || mb_strlen($flower) > 100
                    || $quantity === false || $quantity < 1 || $quantity > 100000
                    || $unitPrice === false || $unitPrice < 0 || $unitPrice > 1000000000
                    || !$validDate || $validDate->format('Y-m-d') !== $saleDate
                    || !in_array($paymentMethod, $allowedPayments, true)
                    || mb_strlen($notes) > 500
                ) {
                    throw new RuntimeException('Periksa kembali isian formulir. Pastikan semua kolom wajib diisi dengan benar dan catatan maksimal 500 karakter.');
                }

                $values = [
                    'customer_name' => $customer,
                    'flower_name' => $flower,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'sale_date' => $saleDate,
                    'payment_method' => $paymentMethod,
                    'notes' => $notes !== '' ? $notes : null,
                ];

                if ($id !== '') {
                    $validId = filter_var($id, FILTER_VALIDATE_INT);
                    if (!$validId) {
                        throw new RuntimeException('ID penjualan tidak valid.');
                    }
                    $values['id'] = $validId;
                    $statement = $pdo->prepare(
                        'UPDATE sales SET customer_name = :customer_name, flower_name = :flower_name,
                         quantity = :quantity, unit_price = :unit_price, sale_date = :sale_date,
                         payment_method = :payment_method, notes = :notes WHERE id = :id'
                    );
                    $statement->execute($values);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Data penjualan berhasil diperbarui.'];
                } else {
                    $statement = $pdo->prepare(
                        'INSERT INTO sales (customer_name, flower_name, quantity, unit_price, sale_date, payment_method, notes)
                         VALUES (:customer_name, :flower_name, :quantity, :unit_price, :sale_date, :payment_method, :notes)'
                    );
                    $statement->execute($values);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Penjualan baru berhasil ditambahkan.'];
                }

                header('Location: index.php');
                exit;
            } else {
                throw new RuntimeException('Aksi tidak dikenal.');
            }
        } catch (RuntimeException $exception) {
            $errorMessage = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Florist database error: ' . $exception->getMessage());
            $errorMessage = 'Terjadi kesalahan saat memproses data ke database.';
        }
    }

    try {
        $summaryStatement = $pdo->query(
            'SELECT COUNT(*) AS transactions, COALESCE(SUM(quantity * unit_price), 0) AS revenue,
                    COALESCE(SUM(quantity), 0) AS bouquets,
                    COALESCE(SUM(CASE WHEN sale_date = CURDATE() THEN 1 ELSE 0 END), 0) AS today
             FROM sales'
        );
        $summary = $summaryStatement->fetch() ?: $summary;

        $conditions = [];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(customer_name LIKE :search_customer OR flower_name LIKE :search_flower OR payment_method LIKE :search_payment)';
            $safeSearch = '%' . addcslashes($search, '%_\\') . '%';
            $parameters['search_customer'] = $safeSearch;
            $parameters['search_flower'] = $safeSearch;
            $parameters['search_payment'] = $safeSearch;
        }
        if ($selectedDate !== '' && DateTime::createFromFormat('Y-m-d', $selectedDate)) {
            $conditions[] = 'sale_date = :sale_date';
            $parameters['sale_date'] = $selectedDate;
        }
        if ($selectedPayment !== '' && in_array($selectedPayment, ['Tunai', 'Transfer', 'QRIS', 'Kartu'], true)) {
            $conditions[] = 'payment_method = :payment_filter';
            $parameters['payment_filter'] = $selectedPayment;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $statement = $pdo->prepare("SELECT * FROM sales {$where} ORDER BY {$sortSql}");
        $statement->execute($parameters);
        $sales = $statement->fetchAll();

        if (isset($_GET['edit'])) {
            $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
            if ($editId) {
                $statement = $pdo->prepare('SELECT * FROM sales WHERE id = :id');
                $statement->execute(['id' => $editId]);
                $editingSale = $statement->fetch() ?: null;
            }
        }
    } catch (PDOException $exception) {
        error_log('Florist query error: ' . $exception->getMessage());
        $dbError = 'Gagal memuat data penjualan dari database.';
    }
}

$isEditMode = ($editingSale !== null) || (!empty($_POST['id']) && ($_POST['action'] ?? '') === 'save');
$showForm = isset($_GET['form']) || $editingSale !== null || ($errorMessage !== null && ($_POST['action'] ?? '') === 'save');
$formValues = $editingSale ?? [
    'id' => '',
    'customer_name' => '',
    'flower_name' => '',
    'quantity' => 1,
    'unit_price' => '',
    'sale_date' => date('Y-m-d'),
    'payment_method' => 'Tunai',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && $errorMessage !== null) {
    $formValues = array_merge($formValues, [
        'id' => $_POST['id'] ?? '',
        'customer_name' => $_POST['customer_name'] ?? '',
        'flower_name' => $_POST['flower_name'] ?? '',
        'quantity' => $_POST['quantity'] ?? 1,
        'unit_price' => $_POST['unit_price'] ?? '',
        'sale_date' => $_POST['sale_date'] ?? date('Y-m-d'),
        'payment_method' => $_POST['payment_method'] ?? 'Tunai',
        'notes' => $_POST['notes'] ?? '',
    ]);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#fbf8f4">
    <title>Petal & Co. — Sistem Informasi Penjualan Bunga</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="index.php" aria-label="Petal and Co, beranda">
                <span class="brand-mark">✿</span>
                <span class="brand-name">petal<span>&co.</span></span>
            </a>
            <div class="store-label">FLORIST MANAGEMENT</div>
            <nav class="side-nav" aria-label="Navigasi utama">
                <a class="nav-link active" href="index.php"><span class="nav-icon">▦</span>Ringkasan penjualan</a>
                <a class="nav-link" href="#sales-table"><span class="nav-icon">▤</span>Data transaksi</a>
            </nav>
            <div class="sidebar-bottom">
                <div class="sidebar-note">
                    <span class="note-flower">✿</span>
                    <p>Merangkai kebahagiaan,<br>seikat demi seikat.</p>
                </div>
                <div class="user-profile">
                    <div class="avatar">P</div>
                    <div><strong>Petal & Co.</strong><small>Administrator</small></div>
                    <span class="profile-menu">···</span>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="breadcrumb">Dashboard <span>/</span> <strong>Penjualan</strong></div>
                <div class="topbar-right">
                    <span class="today-label"><?= e(formatTanggalIndo(date('Y-m-d'), true)) ?></span>
                    <span class="topbar-dot" title="Sistem Aktif"></span>
                </div>
            </header>

            <section class="page-heading">
                <div>
                    <p class="eyebrow">DASHBOARD TOKO BUNGA</p>
                    <h1>Penjualan <em>bunga.</em></h1>
                    <p class="page-subtitle">Pantau pesanan, catat transaksi harian, dan kelola pendapatan tokomu dengan rapi.</p>
                </div>
                <a class="button button-primary" id="btn-tambah" href="index.php?form=1"><span class="button-plus">+</span> Tambah penjualan</a>
            </section>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="status">
                    <?= e($flash['message']) ?>
                    <button class="alert-close" type="button" aria-label="Tutup">&times;</button>
                </div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-error" role="alert">
                    <?= e($errorMessage) ?>
                    <button class="alert-close" type="button" aria-label="Tutup">&times;</button>
                </div>
            <?php endif; ?>
            <?php if ($dbError): ?>
                <div class="alert alert-error" role="alert"><?= e($dbError) ?></div>
            <?php endif; ?>

            <section class="stats-grid" aria-label="Ringkasan penjualan">
                <article class="stat-card">
                    <div class="stat-top"><span>Total pendapatan</span><span class="stat-icon icon-rose">↗</span></div>
                    <div class="stat-value"><?= e(rupiah($summary['revenue'])) ?></div>
                    <div class="stat-foot"><span class="stat-caption">Akumulasi seluruh penjualan</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Total transaksi</span><span class="stat-icon icon-lilac">▤</span></div>
                    <div class="stat-value"><?= number_format((int) $summary['transactions'], 0, ',', '.') ?></div>
                    <div class="stat-foot"><span class="stat-caption">Pesanan yang tercatat</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Buket / item terjual</span><span class="stat-icon icon-green">✿</span></div>
                    <div class="stat-value"><?= number_format((int) $summary['bouquets'], 0, ',', '.') ?></div>
                    <div class="stat-foot"><span class="stat-caption">Total tangkai / rangkaian</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Transaksi hari ini</span><span class="stat-icon icon-peach">☼</span></div>
                    <div class="stat-value"><?= number_format((int) $summary['today'], 0, ',', '.') ?></div>
                    <div class="stat-foot"><span class="stat-caption">Pesanan pada hari ini</span></div>
                </article>
            </section>

            <section class="table-card" id="sales-table">
                <div class="table-heading">
                    <div>
                        <h2>Daftar penjualan</h2>
                        <p>Semua pesanan bunga yang tercatat di tokomu.</p>
                    </div>
                    <form class="filters" method="get" action="index.php">
                        <label class="search-box">
                            <span>⌕</span>
                            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau bunga..." aria-label="Cari penjualan">
                        </label>
                        <label class="date-filter">
                            <span>▦</span>
                            <input type="date" name="date" value="<?= e($selectedDate) ?>" aria-label="Filter tanggal">
                        </label>
                        <select name="payment" class="filter-select" aria-label="Metode pembayaran">
                            <option value="">Semua Pembayaran</option>
                            <?php foreach (['Tunai', 'Transfer', 'QRIS', 'Kartu'] as $m): ?>
                                <option value="<?= e($m) ?>" <?= $selectedPayment === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="sort" class="filter-select" aria-label="Urutkan data">
                            <option value="date_desc" <?= $selectedSort === 'date_desc' ? 'selected' : '' ?>>Tanggal Terbaru</option>
                            <option value="date_asc" <?= $selectedSort === 'date_asc' ? 'selected' : '' ?>>Tanggal Terlama</option>
                            <option value="total_desc" <?= $selectedSort === 'total_desc' ? 'selected' : '' ?>>Total Tertinggi</option>
                            <option value="total_asc" <?= $selectedSort === 'total_asc' ? 'selected' : '' ?>>Total Terendah</option>
                            <option value="name_asc" <?= $selectedSort === 'name_asc' ? 'selected' : '' ?>>Nama (A-Z)</option>
                        </select>
                        <button class="filter-submit" type="submit">Filter</button>
                        <?php if ($search !== '' || $selectedDate !== '' || $selectedPayment !== '' || $selectedSort !== 'date_desc'): ?>
                            <a class="clear-filter" href="index.php">Reset</a>
                        <?php endif; ?>
                        <a class="export-button" href="index.php?export=csv<?= $search !== '' ? '&q=' . urlencode($search) : '' ?><?= $selectedDate !== '' ? '&date=' . urlencode($selectedDate) : '' ?><?= $selectedPayment !== '' ? '&payment=' . urlencode($selectedPayment) : '' ?><?= $selectedSort !== 'date_desc' ? '&sort=' . urlencode($selectedSort) : '' ?>" title="Unduh laporan transaksi dalam format CSV untuk Excel">
                            <span>⤓</span> CSV
                        </a>
                    </form>
                </div>
                <?php if (!$dbError): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>PELANGGAN</th>
                                <th>RANGKAIAN BUNGA</th>
                                <th>TANGGAL</th>
                                <th>JUMLAH</th>
                                <th>PEMBAYARAN</th>
                                <th>TOTAL</th>
                                <th class="action-heading">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$sales): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <span>✿</span>
                                    <strong>Belum ada penjualan ditemukan</strong>
                                    <small><?= ($search !== '' || $selectedDate !== '' || $selectedPayment !== '') ? 'Coba ubah kata kunci pencarian, filter tanggal, atau metode pembayaran.' : 'Tambahkan transaksi pertamamu untuk mulai mencatat penjualan.' ?></small>
                                    <a href="index.php?form=1">+ Tambah penjualan</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td>
                                    <div class="customer-cell">
                                        <span class="customer-avatar"><?= e(mb_strtoupper(mb_substr($sale['customer_name'], 0, 1))) ?></span>
                                        <div>
                                            <strong><?= e($sale['customer_name']) ?></strong>
                                            <small>INV-<?= str_pad((string) $sale['id'], 4, '0', STR_PAD_LEFT) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flower-cell">
                                        <span class="flower-thumb"><?= flowerIcon($sale['flower_name']) ?></span>
                                        <div class="flower-info">
                                            <strong><?= e($sale['flower_name']) ?></strong>
                                            <small><?= e(rupiah($sale['unit_price'])) ?> / item</small>
                                            <?php if (!empty($sale['notes'])): ?>
                                                <span class="cell-note" title="Catatan: <?= e($sale['notes']) ?>">✎ <?= e(mb_strimwidth($sale['notes'], 0, 32, '...')) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e(formatTanggalIndo($sale['sale_date'], false, true)) ?></td>
                                <td><?= e($sale['quantity']) ?> item</td>
                                <td><span class="payment-pill"><?= e($sale['payment_method']) ?></span></td>
                                <td class="total-cell"><?= e(rupiah((int) $sale['quantity'] * (int) $sale['unit_price'])) ?></td>
                                <td>
                                    <div class="row-actions">
                                        <button type="button" class="icon-button invoice-button" 
                                            data-id="<?= e($sale['id']) ?>" 
                                            data-invoice="INV-<?= str_pad((string) $sale['id'], 4, '0', STR_PAD_LEFT) ?>" 
                                            data-customer="<?= e($sale['customer_name']) ?>" 
                                            data-flower="<?= e($sale['flower_name']) ?>" 
                                            data-qty="<?= e($sale['quantity']) ?>" 
                                            data-price="<?= e(rupiah($sale['unit_price'])) ?>" 
                                            data-total="<?= e(rupiah((int) $sale['quantity'] * (int) $sale['unit_price'])) ?>" 
                                            data-date="<?= e(formatTanggalIndo($sale['sale_date'], true)) ?>" 
                                            data-payment="<?= e($sale['payment_method']) ?>" 
                                            data-notes="<?= e($sale['notes'] ?? '') ?>" 
                                            aria-label="Lihat & cetak nota penjualan <?= e($sale['customer_name']) ?>" 
                                            title="Cetak Struk / Nota">🧾</button>
                                        <a class="icon-button edit-button" href="index.php?edit=<?= e($sale['id']) ?>" aria-label="Edit penjualan <?= e($sale['customer_name']) ?>" title="Edit">✎</a>
                                        <form method="post" class="delete-form" data-confirm="Hapus penjualan <?= e($sale['customer_name']) ?>? Tindakan ini tidak dapat dibatalkan.">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= e($sale['id']) ?>">
                                            <button class="icon-button delete-button" type="submit" aria-label="Hapus penjualan <?= e($sale['customer_name']) ?>" title="Hapus">⌫</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span>Menampilkan <strong><?= count($sales) ?></strong> transaksi<?= ($search !== '' || $selectedDate !== '' || $selectedPayment !== '' || $selectedSort !== 'date_desc') ? ' (hasil filter)' : '' ?></span>
                    <span class="footer-flower">✿ &nbsp; Semua bunga, satu cerita.</span>
                </div>
                <?php endif; ?>
            </section>
            <footer class="page-footer">Dibuat dengan <span>♥</span> untuk hari-hari yang lebih berbunga.</footer>
        </main>
    </div>

    <div class="modal-backdrop<?= $showForm ? ' is-visible' : '' ?>" id="sale-modal" aria-hidden="<?= $showForm ? 'false' : 'true' ?>">
        <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <div class="modal-header">
                <div>
                    <p class="eyebrow"><?= $isEditMode ? 'PERBARUI PESANAN' : 'CATAT PESANAN BARU' ?></p>
                    <h2 id="modal-title"><?= $isEditMode ? 'Edit penjualan' : 'Tambah penjualan' ?></h2>
                </div>
                <a class="modal-close" href="index.php" aria-label="Tutup">&times;</a>
            </div>
            <form method="post" action="index.php" class="sale-form" id="sale-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($formValues['id']) ?>">
                <div class="form-grid">
                    <label class="form-field">
                        <span>Nama pelanggan <b>*</b></span>
                        <input type="text" name="customer_name" maxlength="100" value="<?= e($formValues['customer_name']) ?>" placeholder="Contoh: Alya Putri" required>
                    </label>
                    <label class="form-field">
                        <span>Nama bunga / rangkaian <b>*</b></span>
                        <input type="text" name="flower_name" maxlength="100" value="<?= e($formValues['flower_name']) ?>" placeholder="Contoh: Buket Mawar Merah" required>
                    </label>
                    <label class="form-field">
                        <span>Jumlah item <b>*</b></span>
                        <input type="number" id="input-quantity" name="quantity" min="1" max="100000" value="<?= e($formValues['quantity']) ?>" required>
                    </label>
                    <label class="form-field">
                        <span>Harga per item (Rp) <b>*</b></span>
                        <input type="number" id="input-unit-price" name="unit_price" min="0" max="1000000000" step="1" value="<?= e($formValues['unit_price']) ?>" placeholder="150000" required>
                        <div class="price-presets" aria-label="Pilihan cepat harga">
                            <button type="button" class="preset-pill" data-price="75000">75 rb (Mini)</button>
                            <button type="button" class="preset-pill" data-price="150000">150 rb (Standar)</button>
                            <button type="button" class="preset-pill" data-price="250000">250 rb (Spesial)</button>
                            <button type="button" class="preset-pill" data-price="350000">350 rb (Premium)</button>
                        </div>
                    </label>
                    <label class="form-field">
                        <span>Tanggal penjualan <b>*</b></span>
                        <input type="date" name="sale_date" value="<?= e($formValues['sale_date']) ?>" required>
                    </label>
                    <label class="form-field">
                        <span>Metode pembayaran <b>*</b></span>
                        <select name="payment_method" required>
                            <?php foreach (['Tunai', 'Transfer', 'QRIS', 'Kartu'] as $method): ?>
                                <option value="<?= e($method) ?>" <?= $formValues['payment_method'] === $method ? 'selected' : '' ?>><?= e($method) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field form-field-full">
                        <span>Catatan <small>(opsional, maks. 500 karakter)</small></span>
                        <textarea name="notes" rows="3" maxlength="500" placeholder="Contoh: kartu ucapan ulang tahun, pita warna emas"><?= e($formValues['notes']) ?></textarea>
                    </label>
                    <div class="subtotal-banner form-field-full">
                        <span class="subtotal-label">Perkiraan Total Pembayaran:</span>
                        <strong class="subtotal-amount" id="subtotal-preview">Rp 0</strong>
                    </div>
                </div>
                <div class="modal-actions">
                    <a class="button button-quiet" href="index.php">Batal</a>
                    <button class="button button-primary" type="submit"><?= $isEditMode ? 'Simpan perubahan' : 'Simpan penjualan' ?></button>
                </div>
            </form>
        </section>
    </div>

    <!-- Modal Struk / Nota Penjualan -->
    <div class="modal-backdrop" id="invoice-modal" aria-hidden="true">
        <section class="modal invoice-modal-card" role="dialog" aria-modal="true" aria-labelledby="invoice-modal-title">
            <div class="modal-header print-hide">
                <div>
                    <p class="eyebrow">BUKTI TRANSAKSI PENJUALAN</p>
                    <h2 id="invoice-modal-title">Struk Penjualan</h2>
                </div>
                <button type="button" class="modal-close" id="btn-close-invoice-x" aria-label="Tutup">&times;</button>
            </div>
            <div class="invoice-paper" id="invoice-paper">
                <div class="invoice-receipt-header">
                    <div class="receipt-brand">
                        <span class="brand-mark">✿</span>
                        <div>
                            <span class="brand-name">petal<span>&co.</span></span>
                            <small class="receipt-tagline">Florist & Flower Boutique</small>
                        </div>
                    </div>
                    <div class="receipt-meta">
                        <span class="receipt-badge">STRUK PEMBELIAN</span>
                        <strong id="inv-modal-number">INV-0000</strong>
                        <span id="inv-modal-date">-</span>
                    </div>
                </div>
                <div class="receipt-divider"></div>
                <div class="receipt-info-grid">
                    <div>
                        <span class="receipt-label">Nama Pelanggan</span>
                        <strong id="inv-modal-customer">-</strong>
                    </div>
                    <div>
                        <span class="receipt-label">Metode Pembayaran</span>
                        <span class="payment-pill" id="inv-modal-payment">-</span>
                    </div>
                </div>
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Rangkaian Bunga</th>
                            <th class="text-center">Jumlah</th>
                            <th class="text-right">Harga Satuan</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong id="inv-modal-flower">-</strong>
                                <div class="receipt-item-note" id="inv-modal-notes"></div>
                            </td>
                            <td class="text-center" id="inv-modal-qty">1</td>
                            <td class="text-right" id="inv-modal-price">Rp 0</td>
                            <td class="text-right"><strong id="inv-modal-total">Rp 0</strong></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Total Pembayaran</strong></td>
                            <td class="text-right"><strong class="receipt-grand-total" id="inv-modal-grand-total">Rp 0</strong></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="receipt-footer">
                    <p class="receipt-thank-you">Terima kasih atas pesanan Anda di <strong>Petal & Co.</strong> ✿</p>
                    <p class="receipt-small-note">Setiap kelopak bunga dirangkai dengan penuh cinta dan ketulusan hati.</p>
                </div>
            </div>
            <div class="modal-actions invoice-actions print-hide">
                <button type="button" class="button button-quiet" id="btn-close-invoice">Tutup</button>
                <button type="button" class="button button-primary" id="btn-print-invoice"><span>🖨</span> Cetak Struk</button>
            </div>
        </section>
    </div>
    <script src="assets/app.js"></script>
</body>
</html>

