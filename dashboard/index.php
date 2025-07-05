<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit();
}

// Get dashboard statistics
$totalUsers = getCount("SELECT COUNT(*) FROM users WHERE isadmin != '1' OR isadmin IS NULL");
$totalTransactions = getCount("SELECT COUNT(*) FROM transactions");
$totalGames = getCount("SELECT COUNT(*) FROM gameresults");
$totalWalletBalance = fetchSingle("SELECT SUM(CAST(amount AS DECIMAL(10,2))) as total FROM wallets");
$totalWalletBalance = $totalWalletBalance ? $totalWalletBalance['total'] : 0;

// Get recent transactions
$recentTransactions = fetchAll("
    SELECT t.*, u.name, u.mobile 
    FROM transactions t 
    JOIN users u ON t.userid = u.id 
    ORDER BY t.created_at DESC 
    LIMIT 10
");

// Get recent users
$recentUsers = fetchAll("
    SELECT u.*, w.amount as wallet_balance 
    FROM users u 
    LEFT JOIN wallets w ON u.id = w.userid 
    WHERE u.isadmin != '1' OR u.isadmin IS NULL 
    ORDER BY u.created_at DESC 
    LIMIT 10
");

// Get game statistics
$gameStats = fetchAll("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as games_count,
        AVG(CAST(result AS DECIMAL(10,2))) as avg_result
    FROM gameresults 
    WHERE result != 'pending'
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 7
");

// Get pending requests
$pendingWithdrawals = getCount("SELECT COUNT(*) FROM transactions WHERE type = 'debit' AND status = '0'");
$pendingDeposits = getCount("SELECT COUNT(*) FROM transactions WHERE type = 'credit' AND status = '0'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Aviator Game Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
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
            <a href="#" class="flex items-center px-4 py-3 text-gray-700 bg-blue-50 border-r-4 border-blue-500">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            <a href="../users/" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100">
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
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="flex items-center text-gray-700 hover:text-gray-900">
                            <i class="fas fa-bell mr-2"></i>
                            <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1">
                                <?php echo $pendingWithdrawals + $pendingDeposits; ?>
                            </span>
                        </button>
                    </div>
                    
                    <div class="relative">
                        <button class="flex items-center text-gray-700 hover:text-gray-900" onclick="toggleProfile()">
                            <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span class="ml-2 font-medium"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                            <i class="fas fa-chevron-down ml-2"></i>
                        </button>
                        
                        <div id="profileDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-2 hidden">
                            <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-2"></i>Settings
                            </a>
                            <hr class="my-2">
                            <a href="../auth/logout.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
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

                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Transactions</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalTransactions); ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-exchange-alt text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Games</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalGames); ?></p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-gamepad text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Wallet Balance</p>
                            <p class="text-3xl font-bold text-gray-800">₹<?php echo number_format($totalWalletBalance, 2); ?></p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <i class="fas fa-wallet text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Requests Alert -->
            <?php if ($pendingWithdrawals > 0 || $pendingDeposits > 0): ?>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-orange-600 mr-3"></i>
                        <div>
                            <p class="text-orange-800 font-medium">Pending Requests</p>
                            <p class="text-orange-600 text-sm">
                                <?php echo $pendingWithdrawals; ?> withdrawal requests and <?php echo $pendingDeposits; ?> deposit requests are pending approval.
                            </p>
                        </div>
                        <a href="../transactions/" class="ml-auto bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors">
                            View All
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Charts and Tables -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Game Statistics Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Game Statistics (Last 7 Days)</h3>
                    <canvas id="gameChart" height="200"></canvas>
                </div>

                <!-- Recent Transactions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Transactions</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-sm text-gray-600">
                                    <th class="pb-3">User</th>
                                    <th class="pb-3">Type</th>
                                    <th class="pb-3">Amount</th>
                                    <th class="pb-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr class="border-t border-gray-100">
                                        <td class="py-3">
                                            <div>
                                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($transaction['name']); ?></p>
                                                <p class="text-gray-600"><?php echo htmlspecialchars($transaction['mobile']); ?></p>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $transaction['type'] == 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo ucfirst($transaction['type']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 font-medium">₹<?php echo number_format($transaction['amount'], 2); ?></td>
                                        <td class="py-3">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $transaction['status'] == '1' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo $transaction['status'] == '1' ? 'Success' : 'Pending'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Users</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-600">
                                <th class="pb-3">Name</th>
                                <th class="pb-3">Email</th>
                                <th class="pb-3">Mobile</th>
                                <th class="pb-3">Wallet Balance</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php foreach ($recentUsers as $user): ?>
                                <tr class="border-t border-gray-100">
                                    <td class="py-3 font-medium text-gray-800"><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td class="py-3 text-gray-600"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="py-3 text-gray-600"><?php echo htmlspecialchars($user['mobile']); ?></td>
                                    <td class="py-3 font-medium">₹<?php echo number_format($user['wallet_balance'] ?? 0, 2); ?></td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $user['status'] == '1' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $user['status'] == '1' ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-gray-600"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
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

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const button = event.target.closest('button');
            
            if (!button || !button.onclick) {
                dropdown.classList.add('hidden');
            }
        });

        // Game Statistics Chart
        const gameData = <?php echo json_encode(array_reverse($gameStats)); ?>;
        const ctx = document.getElementById('gameChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: gameData.map(item => new Date(item.date).toLocaleDateString()),
                datasets: [{
                    label: 'Games Played',
                    data: gameData.map(item => item.games_count),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Average Result',
                    data: gameData.map(item => parseFloat(item.avg_result)),
                    borderColor: 'rgb(168, 85, 247)',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
</body>
</html>