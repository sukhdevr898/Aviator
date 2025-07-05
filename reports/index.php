<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit();
}

// Get date range for reports
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today

// User Statistics
$totalUsers = getCount("SELECT COUNT(*) FROM users WHERE (isadmin != '1' OR isadmin IS NULL)");
$newUsersToday = getCount("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE() AND (isadmin != '1' OR isadmin IS NULL)");
$activeUsers = getCount("SELECT COUNT(*) FROM users WHERE status = '1' AND (isadmin != '1' OR isadmin IS NULL)");

// Transaction Statistics
$totalTransactions = getCount("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo]);
$totalDeposits = fetchSingle("SELECT SUM(CAST(amount AS DECIMAL(10,2))) as total FROM transactions WHERE type = 'credit' AND status = '1' AND DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo]);
$totalWithdrawals = fetchSingle("SELECT SUM(CAST(amount AS DECIMAL(10,2))) as total FROM transactions WHERE type = 'debit' AND status = '1' AND DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo]);

$totalDeposits = $totalDeposits ? $totalDeposits['total'] : 0;
$totalWithdrawals = $totalWithdrawals ? $totalWithdrawals['total'] : 0;

// Game Statistics
$totalGames = getCount("SELECT COUNT(*) FROM gameresults WHERE DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo]);
$totalBets = getCount("SELECT COUNT(*) FROM userbits WHERE DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo]);
$totalBetAmount = fetchSingle("SELECT SUM(CAST(amount AS DECIMAL(10,2))) as total FROM userbits WHERE DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo]);
$totalBetAmount = $totalBetAmount ? $totalBetAmount['total'] : 0;

// Daily transactions for chart
$dailyTransactions = fetchAll("
    SELECT 
        DATE(created_at) as date,
        SUM(CASE WHEN type = 'credit' AND status = '1' THEN CAST(amount AS DECIMAL(10,2)) ELSE 0 END) as deposits,
        SUM(CASE WHEN type = 'debit' AND status = '1' THEN CAST(amount AS DECIMAL(10,2)) ELSE 0 END) as withdrawals,
        COUNT(*) as total_transactions
    FROM transactions 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
", [$dateFrom, $dateTo]);

// Top users by transactions
$topUsers = fetchAll("
    SELECT 
        u.name, 
        u.mobile,
        u.email,
        COUNT(t.id) as transaction_count,
        SUM(CASE WHEN t.type = 'credit' AND t.status = '1' THEN CAST(t.amount AS DECIMAL(10,2)) ELSE 0 END) as total_deposits,
        SUM(CASE WHEN t.type = 'debit' AND t.status = '1' THEN CAST(t.amount AS DECIMAL(10,2)) ELSE 0 END) as total_withdrawals,
        w.amount as current_balance
    FROM users u
    LEFT JOIN transactions t ON u.id = t.userid AND DATE(t.created_at) BETWEEN ? AND ?
    LEFT JOIN wallets w ON u.id = w.userid
    WHERE (u.isadmin != '1' OR u.isadmin IS NULL)
    GROUP BY u.id
    HAVING transaction_count > 0
    ORDER BY transaction_count DESC
    LIMIT 10
", [$dateFrom, $dateTo]);

// Game performance
$gamePerformance = fetchAll("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as games_played,
        AVG(CAST(result AS DECIMAL(10,2))) as avg_result,
        MAX(CAST(result AS DECIMAL(10,2))) as max_result,
        MIN(CAST(result AS DECIMAL(10,2))) as min_result
    FROM gameresults 
    WHERE result != 'pending' AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
", [$dateFrom, $dateTo]);

// Recent activities
$recentActivities = fetchAll("
    SELECT 
        'transaction' as type,
        CONCAT(u.name, ' made a ', t.type, ' of ₹', t.amount) as description,
        t.created_at as timestamp
    FROM transactions t
    JOIN users u ON t.userid = u.id
    WHERE DATE(t.created_at) BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'user' as type,
        CONCAT('New user registered: ', u.name) as description,
        u.created_at as timestamp
    FROM users u
    WHERE DATE(u.created_at) BETWEEN ? AND ? AND (u.isadmin != '1' OR u.isadmin IS NULL)
    
    ORDER BY timestamp DESC
    LIMIT 20
", [$dateFrom, $dateTo, $dateFrom, $dateTo]);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Aviator Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="#" class="flex items-center px-4 py-3 text-gray-700 bg-blue-50 border-r-4 border-blue-500">
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
                    <h1 class="text-2xl font-bold text-gray-800">Reports & Analytics</h1>
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
            <!-- Date Range Filter -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-filter mr-2"></i>Apply Filter
                    </button>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Users</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalUsers); ?></p>
                            <p class="text-green-600 text-sm mt-1">+<?php echo $newUsersToday; ?> today</p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Deposits</p>
                            <p class="text-3xl font-bold text-green-600">₹<?php echo number_format($totalDeposits, 2); ?></p>
                            <p class="text-gray-600 text-sm mt-1"><?php echo $dateFrom; ?> to <?php echo $dateTo; ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-arrow-up text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Withdrawals</p>
                            <p class="text-3xl font-bold text-red-600">₹<?php echo number_format($totalWithdrawals, 2); ?></p>
                            <p class="text-gray-600 text-sm mt-1"><?php echo $dateFrom; ?> to <?php echo $dateTo; ?></p>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full">
                            <i class="fas fa-arrow-down text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Net Revenue</p>
                            <p class="text-3xl font-bold text-purple-600">₹<?php echo number_format($totalDeposits - $totalWithdrawals, 2); ?></p>
                            <p class="text-gray-600 text-sm mt-1">Profit/Loss</p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Daily Transactions Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Daily Transactions</h3>
                    <canvas id="transactionChart" height="300"></canvas>
                </div>

                <!-- Game Performance Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Game Performance</h3>
                    <canvas id="gameChart" height="300"></canvas>
                </div>
            </div>

            <!-- Top Users & Recent Activities -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Top Users -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Top Users (by Transactions)</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-sm text-gray-600">
                                    <th class="pb-3">User</th>
                                    <th class="pb-3">Transactions</th>
                                    <th class="pb-3">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php foreach ($topUsers as $user): ?>
                                    <tr class="border-t border-gray-100">
                                        <td class="py-3">
                                            <div>
                                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['name']); ?></p>
                                                <p class="text-gray-600"><?php echo htmlspecialchars($user['mobile']); ?></p>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <div class="text-gray-900"><?php echo $user['transaction_count']; ?></div>
                                            <div class="text-xs text-green-600">↑₹<?php echo number_format($user['total_deposits'], 2); ?></div>
                                            <div class="text-xs text-red-600">↓₹<?php echo number_format($user['total_withdrawals'], 2); ?></div>
                                        </td>
                                        <td class="py-3 font-medium">₹<?php echo number_format($user['current_balance'] ?? 0, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Activities</h3>
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-<?php echo $activity['type'] === 'transaction' ? 'exchange-alt' : 'user'; ?> text-blue-600 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-6">Summary Statistics</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600"><?php echo number_format($totalTransactions); ?></div>
                        <div class="text-sm text-gray-600">Total Transactions</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600"><?php echo number_format($totalGames); ?></div>
                        <div class="text-sm text-gray-600">Games Played</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-purple-600"><?php echo number_format($totalBets); ?></div>
                        <div class="text-sm text-gray-600">Total Bets</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-orange-600">₹<?php echo number_format($totalBetAmount, 2); ?></div>
                        <div class="text-sm text-gray-600">Total Bet Amount</div>
                    </div>
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

        // Daily Transactions Chart
        const transactionData = <?php echo json_encode($dailyTransactions); ?>;
        const transactionCtx = document.getElementById('transactionChart').getContext('2d');
        
        new Chart(transactionCtx, {
            type: 'line',
            data: {
                labels: transactionData.map(item => new Date(item.date).toLocaleDateString()),
                datasets: [{
                    label: 'Deposits',
                    data: transactionData.map(item => parseFloat(item.deposits)),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Withdrawals',
                    data: transactionData.map(item => parseFloat(item.withdrawals)),
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
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
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Game Performance Chart
        const gameData = <?php echo json_encode($gamePerformance); ?>;
        const gameCtx = document.getElementById('gameChart').getContext('2d');
        
        new Chart(gameCtx, {
            type: 'bar',
            data: {
                labels: gameData.map(item => new Date(item.date).toLocaleDateString()),
                datasets: [{
                    label: 'Games Played',
                    data: gameData.map(item => parseInt(item.games_played)),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Average Result',
                    data: gameData.map(item => parseFloat(item.avg_result)),
                    type: 'line',
                    borderColor: 'rgb(168, 85, 247)',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
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
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>