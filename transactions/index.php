<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle transaction actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve_transaction') {
        $transactionId = $_POST['transaction_id'] ?? '';
        
        if ($transactionId) {
            $transaction = fetchSingle("SELECT * FROM transactions WHERE id = ?", [$transactionId]);
            
            if ($transaction) {
                $stmt = executeQuery("UPDATE transactions SET status = '1' WHERE id = ?", [$transactionId]);
                
                if ($stmt && $transaction['type'] === 'credit') {
                    // Add funds to wallet for approved deposits
                    executeQuery("UPDATE wallets SET amount = amount + ? WHERE userid = ?", 
                        [$transaction['amount'], $transaction['userid']]);
                }
                
                if ($stmt) {
                    $success = "Transaction approved successfully!";
                } else {
                    $error = "Failed to approve transaction.";
                }
            }
        }
    } elseif ($action === 'reject_transaction') {
        $transactionId = $_POST['transaction_id'] ?? '';
        
        if ($transactionId) {
            $stmt = executeQuery("UPDATE transactions SET status = '2', remark = 'Rejected by admin' WHERE id = ?", [$transactionId]);
            if ($stmt) {
                $success = "Transaction rejected successfully!";
            } else {
                $error = "Failed to reject transaction.";
            }
        }
    }
}

// Get filter parameters
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build filter query
$whereConditions = [];
$params = [];

if ($type && in_array($type, ['credit', 'debit'])) {
    $whereConditions[] = "t.type = ?";
    $params[] = $type;
}

if ($status !== '' && in_array($status, ['0', '1', '2'])) {
    $whereConditions[] = "t.status = ?";
    $params[] = $status;
}

if ($search) {
    $whereConditions[] = "(t.amount LIKE ? OR t.transactionno LIKE ? OR u.mobile LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_from) {
    $whereConditions[] = "DATE(t.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $whereConditions[] = "DATE(t.created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get transactions with pagination
$transactions = fetchAll("
    SELECT t.*, u.name, u.mobile, u.email 
    FROM transactions t 
    JOIN users u ON t.userid = u.id 
    $whereClause
    ORDER BY t.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

// Get total count for pagination
$totalTransactions = getCount("
    SELECT COUNT(*) 
    FROM transactions t 
    JOIN users u ON t.userid = u.id 
    $whereClause
", $params);

$totalPages = ceil($totalTransactions / $limit);

// Get statistics
$totalAmount = fetchSingle("SELECT SUM(CAST(amount AS DECIMAL(10,2))) as total FROM transactions WHERE status = '1'");
$totalAmount = $totalAmount ? $totalAmount['total'] : 0;

$pendingCount = getCount("SELECT COUNT(*) FROM transactions WHERE status = '0'");
$approvedCount = getCount("SELECT COUNT(*) FROM transactions WHERE status = '1'");
$rejectedCount = getCount("SELECT COUNT(*) FROM transactions WHERE status = '2'");

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management - Aviator Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            transition: transform 0.3s ease;
        }
        .sidebar-hidden {
            transform: translateX(-100%);
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 1000;
                height: 100vh;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(10px);
            }
            .ml-64 {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg sidebar" id="sidebar">
        <div class="flex items-center justify-between h-16 px-4 border-b">
            <div class="flex items-center">
                <div class="flex items-center justify-center w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg">
                    <i class="fas fa-plane text-white"></i>
                </div>
                <span class="ml-3 text-xl font-bold text-gray-800">Aviator Admin</span>
            </div>
            <button class="md:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times text-gray-500"></i>
            </button>
        </div>
        
        <nav class="mt-4">
            <a href="../dashboard/" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            <a href="../users/" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-users mr-3"></i>
                User Management
            </a>
            <a href="#" class="flex items-center px-4 py-3 text-gray-700 bg-blue-50 border-r-4 border-blue-500">
                <i class="fas fa-exchange-alt mr-3"></i>
                Transactions
            </a>
            <a href="../games/" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-gamepad mr-3"></i>
                Game Results
            </a>
            <a href="../bank/" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-university mr-3"></i>
                Bank Details
            </a>
            <a href="../settings/" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-cog mr-3"></i>
                Settings
            </a>
            <a href="../reports/" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-chart-bar mr-3"></i>
                Reports
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="ml-64 min-h-screen">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm border-b">
            <div class="flex items-center justify-between px-6 py-4">
                <div class="flex items-center">
                    <button class="md:hidden mr-4" onclick="toggleSidebar()">
                        <i class="fas fa-bars text-gray-500"></i>
                    </button>
                    <h1 class="text-2xl font-bold text-gray-800">Transaction Management</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="flex items-center text-gray-700 hover:text-gray-900" onclick="toggleProfile()">
                            <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span class="ml-2 font-medium"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                        </button>
                        
                        <div id="profileDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-2 hidden">
                            <a href="../auth/logout.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Amount</p>
                            <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($totalAmount, 2); ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-rupee-sign text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Pending</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo number_format($pendingCount); ?></p>
                        </div>
                        <div class="bg-orange-100 p-3 rounded-full">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Approved</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo number_format($approvedCount); ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-check text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Rejected</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo number_format($rejectedCount); ?></p>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full">
                            <i class="fas fa-times text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-3"></i>
                        <p class="text-green-800"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-600 mr-3"></i>
                        <p class="text-red-800"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Types</option>
                            <option value="credit" <?php echo $type === 'credit' ? 'selected' : ''; ?>>Deposit</option>
                            <option value="debit" <?php echo $type === 'debit' ? 'selected' : ''; ?>>Withdrawal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Status</option>
                            <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Pending</option>
                            <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Approved</option>
                            <option value="2" <?php echo $status === '2' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Amount, mobile, name..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="?" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-refresh"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($transaction['name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($transaction['mobile']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $transaction['type'] == 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo $transaction['type'] == 'credit' ? 'Deposit' : 'Withdrawal'; ?>
                                            </span>
                                            <?php if ($transaction['transactionno']): ?>
                                                <div class="text-xs text-gray-500 mt-1">TXN: <?php echo htmlspecialchars($transaction['transactionno']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">₹<?php echo number_format($transaction['amount'], 2); ?></div>
                                        <?php if ($transaction['category']): ?>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($transaction['category']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($transaction['platform'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = '';
                                        $statusText = '';
                                        switch ($transaction['status']) {
                                            case '0':
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                $statusText = 'Pending';
                                                break;
                                            case '1':
                                                $statusClass = 'bg-green-100 text-green-800';
                                                $statusText = 'Approved';
                                                break;
                                            case '2':
                                                $statusClass = 'bg-red-100 text-red-800';
                                                $statusText = 'Rejected';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($transaction['status'] === '0'): ?>
                                            <button onclick="approveTransaction('<?php echo $transaction['id']; ?>')" 
                                                    class="text-green-600 hover:text-green-900 mr-3">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="rejectTransaction('<?php echo $transaction['id']; ?>')" 
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                    <span class="font-medium"><?php echo min($offset + $limit, $totalTransactions); ?></span> of 
                                    <span class="font-medium"><?php echo $totalTransactions; ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <?php
                                        $params = $_GET;
                                        $params['page'] = $i;
                                        ?>
                                        <a href="?<?php echo http_build_query($params); ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Confirm Action</h3>
                <p class="text-sm text-gray-600 mb-4" id="modalMessage">Are you sure you want to perform this action?</p>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button onclick="confirmAction()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700" id="confirmButton">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentAction = '';
        let currentTransactionId = '';

        // Toggle sidebar for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('sidebar-hidden');
        }

        // Toggle profile dropdown
        function toggleProfile() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('hidden');
        }

        function approveTransaction(transactionId) {
            currentAction = 'approve_transaction';
            currentTransactionId = transactionId;
            
            document.getElementById('modalTitle').textContent = 'Approve Transaction';
            document.getElementById('modalMessage').textContent = 'Are you sure you want to approve this transaction?';
            document.getElementById('confirmButton').textContent = 'Approve';
            document.getElementById('confirmButton').className = 'px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700';
            document.getElementById('confirmModal').classList.remove('hidden');
        }

        function rejectTransaction(transactionId) {
            currentAction = 'reject_transaction';
            currentTransactionId = transactionId;
            
            document.getElementById('modalTitle').textContent = 'Reject Transaction';
            document.getElementById('modalMessage').textContent = 'Are you sure you want to reject this transaction?';
            document.getElementById('confirmButton').textContent = 'Reject';
            document.getElementById('confirmButton').className = 'px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700';
            document.getElementById('confirmModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('confirmModal').classList.add('hidden');
        }

        function confirmAction() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="${currentAction}">
                <input type="hidden" name="transaction_id" value="${currentTransactionId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>