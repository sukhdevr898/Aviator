<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle bank actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_bank') {
        $account_holder_name = $_POST['account_holder_name'] ?? '';
        $mobile_no = $_POST['mobile_no'] ?? '';
        $upi_id = $_POST['upi_id'] ?? '';
        $account_no = $_POST['account_no'] ?? '';
        $ifsc_code = $_POST['ifsc_code'] ?? '';
        $bank_name = $_POST['bank_name'] ?? '';
        $barcode = $_POST['barcode'] ?? '';
        
        if ($account_holder_name && $mobile_no && $upi_id && $account_no && $ifsc_code && $bank_name) {
            $stmt = executeQuery("
                INSERT INTO bankdetails (account_holder_name, mobile_no, upi_id, account_no, ifsc_code, bank_name, barcode, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [$account_holder_name, $mobile_no, $upi_id, $account_no, $ifsc_code, $bank_name, $barcode]);
            
            if ($stmt) {
                $success = "Bank details added successfully!";
            } else {
                $error = "Failed to add bank details.";
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    } elseif ($action === 'update_bank') {
        $id = $_POST['bank_id'] ?? '';
        $account_holder_name = $_POST['account_holder_name'] ?? '';
        $mobile_no = $_POST['mobile_no'] ?? '';
        $upi_id = $_POST['upi_id'] ?? '';
        $account_no = $_POST['account_no'] ?? '';
        $ifsc_code = $_POST['ifsc_code'] ?? '';
        $bank_name = $_POST['bank_name'] ?? '';
        $barcode = $_POST['barcode'] ?? '';
        
        if ($id && $account_holder_name && $mobile_no && $upi_id && $account_no && $ifsc_code && $bank_name) {
            $stmt = executeQuery("
                UPDATE bankdetails 
                SET account_holder_name = ?, mobile_no = ?, upi_id = ?, account_no = ?, ifsc_code = ?, bank_name = ?, barcode = ?, updated_at = NOW()
                WHERE id = ?
            ", [$account_holder_name, $mobile_no, $upi_id, $account_no, $ifsc_code, $bank_name, $barcode, $id]);
            
            if ($stmt) {
                $success = "Bank details updated successfully!";
            } else {
                $error = "Failed to update bank details.";
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    } elseif ($action === 'delete_bank') {
        $id = $_POST['bank_id'] ?? '';
        
        if ($id) {
            $stmt = executeQuery("DELETE FROM bankdetails WHERE id = ?", [$id]);
            if ($stmt) {
                $success = "Bank details deleted successfully!";
            } else {
                $error = "Failed to delete bank details.";
            }
        }
    }
}

// Get all bank details
$bankDetails = fetchAll("SELECT * FROM bankdetails ORDER BY created_at DESC");

// Get user bank details count for reference
$userBankCount = getCount("SELECT COUNT(*) FROM bank_details");

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Details Management - Aviator Admin Panel</title>
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
            <a href="#" class="flex items-center px-4 py-3 text-gray-700 bg-blue-50 border-r-4 border-blue-500">
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
                    <h1 class="text-2xl font-bold text-gray-800">Bank Details Management</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Bank Account
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
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Admin Bank Accounts</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo count($bankDetails); ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-university text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">User Bank Accounts</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo number_format($userBankCount); ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-users text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Payment Methods</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo count($bankDetails) + $userBankCount; ?></p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-credit-card text-purple-600 text-xl"></i>
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

            <!-- Bank Details Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <?php foreach ($bankDetails as $bank): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-white font-bold text-lg"><?php echo htmlspecialchars($bank['bank_name']); ?></h3>
                                    <p class="text-blue-100"><?php echo htmlspecialchars($bank['account_holder_name']); ?></p>
                                </div>
                                <div class="text-white">
                                    <i class="fas fa-university text-2xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Account Number</p>
                                    <p class="text-gray-900 font-mono"><?php echo htmlspecialchars($bank['account_no']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">IFSC Code</p>
                                    <p class="text-gray-900 font-mono"><?php echo htmlspecialchars($bank['ifsc_code']); ?></p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">UPI ID</p>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($bank['upi_id']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Mobile Number</p>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($bank['mobile_no']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($bank['barcode']): ?>
                                <div class="mb-4">
                                    <p class="text-gray-600 text-sm font-medium">QR Code</p>
                                    <p class="text-gray-900 text-sm"><?php echo htmlspecialchars($bank['barcode']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                                <div class="text-sm text-gray-500">
                                    Added: <?php echo date('M d, Y', strtotime($bank['created_at'])); ?>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($bank)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteBank('<?php echo $bank['id']; ?>')" 
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($bankDetails)): ?>
                    <div class="col-span-2 bg-white rounded-xl shadow-lg p-12 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-university text-6xl"></i>
                        </div>
                        <h3 class="text-xl font-medium text-gray-900 mb-2">No Bank Details Found</h3>
                        <p class="text-gray-600 mb-6">Add your first bank account to start receiving deposits.</p>
                        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Bank Account
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add/Edit Bank Modal -->
    <div id="bankModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Add Bank Account</h3>
                
                <form id="bankForm" method="POST">
                    <input type="hidden" name="action" id="modalAction" value="add_bank">
                    <input type="hidden" name="bank_id" id="bankId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" id="accountHolderName" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bank Name *</label>
                        <input type="text" name="bank_name" id="bankName" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Account Number *</label>
                        <input type="text" name="account_no" id="accountNo" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">IFSC Code *</label>
                        <input type="text" name="ifsc_code" id="ifscCode" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">UPI ID *</label>
                        <input type="text" name="upi_id" id="upiId" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="example@upi" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Number *</label>
                        <input type="text" name="mobile_no" id="mobileNo" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">QR Code (Optional)</label>
                        <input type="text" name="barcode" id="barcode" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="QR code data or URL">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeBankModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700" id="submitButton">
                            Add Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Bank Account</h3>
                <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete this bank account? This action cannot be undone.</p>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button onclick="confirmDelete()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentBankId = '';

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

        // Bank Modal Functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Bank Account';
            document.getElementById('modalAction').value = 'add_bank';
            document.getElementById('bankId').value = '';
            document.getElementById('submitButton').textContent = 'Add Account';
            
            // Clear form
            document.getElementById('bankForm').reset();
            document.getElementById('bankModal').classList.remove('hidden');
        }

        function openEditModal(bankData) {
            document.getElementById('modalTitle').textContent = 'Edit Bank Account';
            document.getElementById('modalAction').value = 'update_bank';
            document.getElementById('bankId').value = bankData.id;
            document.getElementById('submitButton').textContent = 'Update Account';
            
            // Fill form with existing data
            document.getElementById('accountHolderName').value = bankData.account_holder_name;
            document.getElementById('bankName').value = bankData.bank_name;
            document.getElementById('accountNo').value = bankData.account_no;
            document.getElementById('ifscCode').value = bankData.ifsc_code;
            document.getElementById('upiId').value = bankData.upi_id;
            document.getElementById('mobileNo').value = bankData.mobile_no;
            document.getElementById('barcode').value = bankData.barcode || '';
            
            document.getElementById('bankModal').classList.remove('hidden');
        }

        function closeBankModal() {
            document.getElementById('bankModal').classList.add('hidden');
        }

        // Delete Modal Functions
        function deleteBank(bankId) {
            currentBankId = bankId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function confirmDelete() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_bank">
                <input type="hidden" name="bank_id" value="${currentBankId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const bankModal = document.getElementById('bankModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === bankModal) {
                closeBankModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>