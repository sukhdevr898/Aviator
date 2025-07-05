<?php
session_start();
require_once '../config/database.php';

// Check if user is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ../dashboard/');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Check admin credentials
        $user = fetchSingle(
            "SELECT * FROM users WHERE email = ? AND (isadmin = '1' OR id = 50030)",
            [$email]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_name'] = $user['name'];
            $_SESSION['admin_email'] = $user['email'];
            
            header('Location: ../dashboard/');
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Aviator Game Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-card {
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .floating-icons {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        .floating-icon {
            position: absolute;
            color: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="floating-icons">
        <i class="fas fa-plane floating-icon text-4xl" style="top: 10%; left: 10%; animation-delay: 0s;"></i>
        <i class="fas fa-rocket floating-icon text-3xl" style="top: 20%; right: 15%; animation-delay: 1s;"></i>
        <i class="fas fa-coins floating-icon text-2xl" style="bottom: 20%; left: 20%; animation-delay: 2s;"></i>
        <i class="fas fa-chart-line floating-icon text-3xl" style="bottom: 15%; right: 10%; animation-delay: 3s;"></i>
        <i class="fas fa-trophy floating-icon text-2xl" style="top: 60%; left: 5%; animation-delay: 4s;"></i>
        <i class="fas fa-star floating-icon text-2xl" style="top: 50%; right: 5%; animation-delay: 5s;"></i>
    </div>

    <div class="login-card glass-effect rounded-2xl shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white bg-opacity-20 rounded-full mb-4">
                <i class="fas fa-plane text-2xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Aviator Admin</h1>
            <p class="text-white text-opacity-80">Sign in to your admin dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-100 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-100 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-white text-sm font-medium mb-2">
                    <i class="fas fa-envelope mr-2"></i>Email Address
                </label>
                <input type="email" name="email" required
                       class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 focus:border-transparent transition-all duration-200"
                       placeholder="Enter your email">
            </div>

            <div>
                <label class="block text-white text-sm font-medium mb-2">
                    <i class="fas fa-lock mr-2"></i>Password
                </label>
                <div class="relative">
                    <input type="password" name="password" id="password" required
                           class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 focus:border-transparent transition-all duration-200"
                           placeholder="Enter your password">
                    <button type="button" onclick="togglePassword()" class="absolute right-3 top-3 text-white text-opacity-70 hover:text-opacity-100 transition-opacity duration-200">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" class="form-checkbox h-4 w-4 text-white bg-transparent border-white border-opacity-30 rounded focus:ring-white focus:ring-opacity-50">
                    <span class="ml-2 text-white text-sm">Remember me</span>
                </label>
                <a href="#" class="text-white text-opacity-80 hover:text-opacity-100 text-sm font-medium transition-opacity duration-200">
                    Forgot password?
                </a>
            </div>

            <button type="submit"
                    class="w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white font-bold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50">
                <i class="fas fa-sign-in-alt mr-2"></i>Sign In
            </button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-white text-opacity-60 text-sm">
                © 2024 Aviator Admin Panel. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Add some interactivity to the form
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[type="email"], input[type="password"]');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>