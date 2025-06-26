
<?php
require_once "includes/config.php";
require_once "includes/functions.php";

if(isLoggedIn()){
    redirectToDashboard();
}

$username = $password = "";
$username_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $login_err = "Please enter username and password.";
    } else {
        $sql = "SELECT id, username, password FROM admin WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $db_username, $hashed_password);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    session_start();
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["username"] = $db_username;

                    redirectToDashboard();
                } else {
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Invalid username or password.";
            }
            $stmt->close();
        }
    }
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Billing System | Login</title>
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect x='45' y='20' width='10' height='60' fill='%2300d8ff'/><rect x='20' y='45' width='60' height='10' fill='%2300d8ff'/><circle cx='50' cy='50' r='48' stroke='%2300d8ff' stroke-width='2' fill='none'/></svg>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --dark-color: #212529;
            --light-color: #f8f9fa;
            --danger-color: #dc3545;
            --success-color: #28a745;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .login-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background-color: white;
            transition: all 0.3s ease;
        }
        
        .login-card:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .login-header {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }
        
        .login-logo {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .login-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        .login-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            position: relative;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .input-group-text {
            background-color: #f1f3f5;
            border-right: none;
        }
        
        .form-control {
            border-left: none;
            padding-left: 0;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: #ced4da;
        }
        
        .btn-login {
            background-color: var(--primary-color);
            border: none;
            padding: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background-color: #0b5ed7;
            transform: translateY(-2px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--secondary-color);
            z-index: 5;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .login-footer {
            text-align: center;
            padding: 1rem;
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
        }
        
        .login-footer a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .login-footer a:hover {
            color: var(--primary-color);
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: var(--dark-color);
            }
            
            .login-card {
                background-color: #2c3e50;
                color: white;
            }
            
            .form-control, .input-group-text {
                background-color: #34495e;
                color: white;
                border-color: #4a6278;
            }
            
            .form-control:focus {
                background-color: #34495e;
                color: white;
            }
            
            .form-label {
                color: #ecf0f1;
            }
            
            .login-footer {
                background-color: #34495e;
                border-color: #4a6278;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card animate__animated animate__fadeIn">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-hospital-user"></i>
                </div>
                <h2 class="login-title">Medical Billing System</h2>
                <p class="login-subtitle">Secure Provider Portal</p>
            </div>
            
            <div class="login-body">
                <?php if(!empty($login_err)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $login_err; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="loginForm">
                    <div class="mb-4">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" name="username" id="username"
                                   class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($username); ?>" 
                                   placeholder="Enter your username"
                                   autocomplete="username"
                                   required>
                        </div>
                        <div class="invalid-feedback"><?php echo $username_err; ?></div>
                    </div>
                    
                    <div class="mb-4 position-relative">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" name="password" id="password"
                                   class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                   placeholder="Enter your password"
                                   autocomplete="current-password"
                                   required>
                            <span class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
                            <label class="form-check-label" for="rememberMe">Remember me</label>
                        </div>
                        <a href="forgot-password.php" class="text-decoration-none">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login w-100 mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                    
                    <div class="text-center">
                        <small class="text-muted">Need an account? <a href="contact.php">Contact administrator</a></small>
                    </div>
                </form>
            </div>
            
            <div class="login-footer">
                <small class="text-muted">© <?php echo date('Y'); ?> Medical Billing System. All rights reserved.</small>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Form submission animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Authenticating...';
            submitBtn.disabled = true;
        });
        
        // Focus on username field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Check for dark mode preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>
</html>