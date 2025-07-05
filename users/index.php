<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_wallet') {
        $userId = $_POST['user_id'] ?? '';
        $amount = $_POST['amount'] ?? '';
        
        if ($userId && is_numeric($amount)) {
            $stmt = executeQuery("UPDATE wallets SET amount = ? WHERE userid = ?", [$amount, $userId]);
            if ($stmt) {
                $success = "Wallet updated successfully!";
            } else {
                $error = "Failed to update wallet.";
            }
        }
    } elseif ($action === 'update_status') {
        $userId = $_POST['user_id'] ?? '';
        $status = $_POST['status'] ?? '';
        
        if ($userId && in_array($status, ['0', '1'])) {
            $stmt = executeQuery("UPDATE users SET status = ? WHERE id = ?", [$status, $userId]);
            if ($stmt) {
                $success = "User status updated successfully!";
            } else {
                $error = "Failed to update user status.";
            }
        }
    } elseif ($action === 'add_funds') {
        $userId = $_POST['user_id'] ?? '';
        $amount = $_POST['amount'] ?? '';
        
        if ($userId && is_numeric($amount) && $amount > 0) {
            $stmt = executeQuery("UPDATE wallets SET amount = amount + ? WHERE userid = ?", [$amount, $userId]);
            if ($stmt) {
                $success = "Funds added successfully!";
            } else {
                $error = "Failed to add funds.";
            }
        }
    } elseif ($action === 'deduct_funds') {
        $userId = $_POST['user_id'] ?? '';
        $amount = $_POST['amount'] ?? '';
        
        if ($userId && is_numeric($amount) && $amount > 0) {
            $stmt = executeQuery("UPDATE wallets SET amount = GREATEST(0, amount - ?) WHERE userid = ?", [$amount, $userId]);
            if ($stmt) {
                $success = "Funds deducted successfully!";
            } else {
                $error = "Failed to deduct funds.";
            }
        }
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build search query
$searchQuery = '';
$searchParams = [];

if ($search) {
    $searchQuery = "WHERE (u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?) AND (u.isadmin != '1' OR u.isadmin IS NULL)";
    $searchParams = ["%$search%", "%$search%", "%$search%"];
} else {
    $searchQuery = "WHERE u.isadmin != '1' OR u.isadmin IS NULL";
}

// Get users with pagination
$users = fetchAll("
    SELECT u.*, w.amount as wallet_balance,
           (SELECT COUNT(*) FROM transactions WHERE userid = u.id) as total_transactions,
           (SELECT COUNT(*) FROM userbits WHERE userid = u.id) as total_bets
    FROM users u 
    LEFT JOIN wallets w ON u.id = w.userid 
    $searchQuery
    ORDER BY u.created_at DESC 
    LIMIT $limit OFFSET $offset
", $searchParams);

// Get total count for pagination
$totalUsers = getCount("SELECT COUNT(*) FROM users u $searchQuery", $searchParams);
$totalPages = ceil($totalUsers / $limit);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Aviator Admin Panel</title>
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
            <a href="#" class="flex items-center px-4 py-3 text-gray-700 bg-blue-50 border-r-4 border-blue-500">
                <i class="fas fa-users mr-3"></i>
                User Management
            </a>
            <a href="../transactions/" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100">
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
                    <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Users</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalUsers); ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Active Users</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo getCount("SELECT COUNT(*) FROM users WHERE status = '1' AND (isadmin != '1' OR isadmin IS NULL)"); ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-user-check text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Wallet Balance</p>
                            <p class="text-3xl font-bold text-gray-800">₹<?php echo number_format(fetchSingle("SELECT SUM(CAST(amount AS DECIMAL(10,2))) as total FROM wallets")['total'] ?? 0, 2); ?></p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-wallet text-purple-600 text-xl"></i>
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

            <!-- Search and Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, email, or mobile..."
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <a href="?" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors text-center">
                        <i class="fas fa-refresh mr-2"></i>Reset
                    </a>
                </form>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wallet</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stats</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                                                    <span class="text-white font-medium">
                                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div class="text-sm text-gray-500">ID: <?php echo $user['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['mobile']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">₹<?php echo number_format($user['wallet_balance'] ?? 0, 2); ?></div>
                                        <div class="text-sm text-gray-500">Current Balance</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <div><?php echo $user['total_transactions']; ?> Transactions</div>
                                            <div><?php echo $user['total_bets']; ?> Bets</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['status'] == '1' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $user['status'] == '1' ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="openWalletModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo $user['wallet_balance'] ?? 0; ?>')" 
                                                class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-wallet"></i>
                                        </button>
                                        <button onclick="toggleUserStatus('<?php echo $user['id']; ?>', '<?php echo $user['status']; ?>')" 
                                                class="text-yellow-600 hover:text-yellow-900 mr-3">
                                            <i class="fas fa-user-cog"></i>
                                        </button>
                                        <button onclick="viewUserDetails('<?php echo $user['id']; ?>')" 
                                                class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-eye"></i>
                                        </button>
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
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                    <span class="font-medium"><?php echo min($offset + $limit, $totalUsers); ?></span> of 
                                    <span class="font-medium"><?php echo $totalUsers; ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
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

    <!-- Wallet Management Modal -->
    <div id="walletModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Manage Wallet</h3>
                <div class="mb-4">
                    <p class="text-sm text-gray-600">User: <span id="modalUserName" class="font-medium"></span></p>
                    <p class="text-sm text-gray-600">Current Balance: ₹<span id="modalCurrentBalance" class="font-medium"></span></p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                    <select id="walletAction" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="set">Set Balance</option>
                        <option value="add">Add Funds</option>
                        <option value="deduct">Deduct Funds</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                    <input type="number" id="walletAmount" step="0.01" min="0" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeWalletModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button onclick="updateWallet()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update User Status</h3>
                <p class="text-sm text-gray-600 mb-4">Are you sure you want to <span id="statusAction"></span> this user?</p>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeStatusModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button onclick="confirmStatusUpdate()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentUserId = '';
        let currentUserStatus = '';

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

        // Wallet Modal Functions
        function openWalletModal(userId, userName, currentBalance) {
            currentUserId = userId;
            document.getElementById('modalUserName').textContent = userName;
            document.getElementById('modalCurrentBalance').textContent = parseFloat(currentBalance).toFixed(2);
            document.getElementById('walletAmount').value = '';
            document.getElementById('walletModal').classList.remove('hidden');
        }

        function closeWalletModal() {
            document.getElementById('walletModal').classList.add('hidden');
        }

        function updateWallet() {
            const action = document.getElementById('walletAction').value;
            const amount = document.getElementById('walletAmount').value;
            
            if (!amount || parseFloat(amount) < 0) {
                alert('Please enter a valid amount');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="${action === 'set' ? 'update_wallet' : (action === 'add' ? 'add_funds' : 'deduct_funds')}">
                <input type="hidden" name="user_id" value="${currentUserId}">
                <input type="hidden" name="amount" value="${amount}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Status Modal Functions
        function toggleUserStatus(userId, currentStatus) {
            currentUserId = userId;
            currentUserStatus = currentStatus;
            const action = currentStatus === '1' ? 'deactivate' : 'activate';
            document.getElementById('statusAction').textContent = action;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        function confirmStatusUpdate() {
            const newStatus = currentUserStatus === '1' ? '0' : '1';
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="user_id" value="${currentUserId}">
                <input type="hidden" name="status" value="${newStatus}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function viewUserDetails(userId) {
            window.open(`user_details.php?id=${userId}`, '_blank');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const walletModal = document.getElementById('walletModal');
            const statusModal = document.getElementById('statusModal');
            
            if (event.target === walletModal) {
                closeWalletModal();
            }
            if (event.target === statusModal) {
                closeStatusModal();
            }
        });
    </script>
</body>
</html>