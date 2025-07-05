<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $settings = $_POST['settings'] ?? [];
        $updated = 0;
        
        foreach ($settings as $category => $value) {
            $stmt = executeQuery("UPDATE settings SET value = ?, updated_at = NOW() WHERE category = ?", [$value, $category]);
            if ($stmt) {
                $updated++;
            }
        }
        
        if ($updated > 0) {
            $success = "Settings updated successfully! ($updated settings changed)";
        } else {
            $error = "No settings were updated.";
        }
    } elseif ($action === 'add_setting') {
        $category = $_POST['category'] ?? '';
        $value = $_POST['value'] ?? '';
        $status = $_POST['status'] ?? '1';
        
        if ($category && $value) {
            // Check if setting already exists
            $existing = fetchSingle("SELECT id FROM settings WHERE category = ?", [$category]);
            
            if (!$existing) {
                $stmt = executeQuery("INSERT INTO settings (category, value, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())", 
                    [$category, $value, $status]);
                
                if ($stmt) {
                    $success = "New setting added successfully!";
                } else {
                    $error = "Failed to add setting.";
                }
            } else {
                $error = "Setting with this category already exists.";
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    }
}

// Get all settings
$settings = fetchAll("SELECT * FROM settings ORDER BY category");

// Group settings by type for better organization
$gameSettings = [];
$amountSettings = [];
$timerSettings = [];
$commissionSettings = [];
$otherSettings = [];

foreach ($settings as $setting) {
    $category = $setting['category'];
    
    if (strpos($category, 'game') !== false || strpos($category, 'timer') !== false) {
        if (strpos($category, 'timer') !== false || strpos($category, 'time') !== false) {
            $timerSettings[] = $setting;
        } else {
            $gameSettings[] = $setting;
        }
    } elseif (strpos($category, 'amount') !== false || strpos($category, 'withdrawal') !== false || 
              strpos($category, 'recharge') !== false || strpos($category, 'bet') !== false || 
              strpos($category, 'bonus') !== false) {
        $amountSettings[] = $setting;
    } elseif (strpos($category, 'commission') !== false) {
        $commissionSettings[] = $setting;
    } else {
        $otherSettings[] = $setting;
    }
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings Management - Aviator Admin Panel</title>
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
            <a href="#" class="flex items-center px-4 py-3 text-gray-700 bg-blue-50 border-r-4 border-blue-500">
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
                    <h1 class="text-2xl font-bold text-gray-800">Settings Management</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Setting
                    </button>
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

            <form method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <!-- Game Settings -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-gamepad text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Game Settings</h2>
                            <p class="text-gray-600">Configure game-related settings</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($gameSettings as $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <?php echo ucwords(str_replace('_', ' ', $setting['category'])); ?>
                                </label>
                                <input type="text" name="settings[<?php echo $setting['category']; ?>]" 
                                       value="<?php echo htmlspecialchars($setting['value']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Current: <?php echo htmlspecialchars($setting['value']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Amount & Limits Settings -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Amount & Limits</h2>
                            <p class="text-gray-600">Configure betting limits and amounts</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($amountSettings as $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <?php echo ucwords(str_replace('_', ' ', $setting['category'])); ?>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₹</span>
                                    <input type="number" name="settings[<?php echo $setting['category']; ?>]" 
                                           value="<?php echo htmlspecialchars($setting['value']); ?>"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Current: ₹<?php echo htmlspecialchars($setting['value']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Timer Settings -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Timer Settings</h2>
                            <p class="text-gray-600">Configure game timing and intervals</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($timerSettings as $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <?php echo ucwords(str_replace('_', ' ', $setting['category'])); ?>
                                </label>
                                <div class="relative">
                                    <input type="number" name="settings[<?php echo $setting['category']; ?>]" 
                                           value="<?php echo htmlspecialchars($setting['value']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">sec</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Current: <?php echo htmlspecialchars($setting['value']); ?> seconds</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Commission Settings -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-orange-100 p-3 rounded-full mr-4">
                            <i class="fas fa-percentage text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Commission Settings</h2>
                            <p class="text-gray-600">Configure referral commission rates</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($commissionSettings as $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <?php echo ucwords(str_replace('_', ' ', $setting['category'])); ?>
                                </label>
                                <div class="relative">
                                    <input type="number" step="0.01" name="settings[<?php echo $setting['category']; ?>]" 
                                           value="<?php echo htmlspecialchars($setting['value']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">%</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Current: <?php echo htmlspecialchars($setting['value']); ?>%</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Other Settings -->
                <?php if (!empty($otherSettings)): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                        <div class="flex items-center mb-6">
                            <div class="bg-gray-100 p-3 rounded-full mr-4">
                                <i class="fas fa-cogs text-gray-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Other Settings</h2>
                                <p class="text-gray-600">Additional system configurations</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($otherSettings as $setting): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <?php echo ucwords(str_replace('_', ' ', $setting['category'])); ?>
                                    </label>
                                    <input type="text" name="settings[<?php echo $setting['category']; ?>]" 
                                           value="<?php echo htmlspecialchars($setting['value']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <p class="text-xs text-gray-500 mt-1">Current: <?php echo htmlspecialchars($setting['value']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Save Button -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Save Changes</h3>
                            <p class="text-gray-600">Click the button below to apply all changes</p>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg transition-colors font-medium">
                            <i class="fas fa-save mr-2"></i>Save All Settings
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <!-- Add Setting Modal -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Setting</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_setting">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                        <input type="text" name="category" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., max_daily_limit" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Value *</label>
                        <input type="text" name="value" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., 10000" required>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Add Setting
                        </button>
                    </div>
                </form>
            </div>
        </div>
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

        // Add Modal Functions
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('addModal');
            if (event.target === modal) {
                closeAddModal();
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = document.querySelectorAll('input[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-red-500');
                } else {
                    field.classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>