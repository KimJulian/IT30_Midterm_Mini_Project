<?php
session_start();
require_once 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE Email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['PasswordHash'])) {
            $_SESSION['UserID'] = $user['UserID'];
            $_SESSION['Email'] = $user['Email'];
            $_SESSION['Role'] = $user['Role'];
            $_SESSION['EmployeeNo'] = $user['EmployeeNo'];

            // Role-based redirection
            if ($user['Role'] === 'Admin' || $user['Role'] === 'HR_Admin' || $user['Role'] === 'HR' || $user['Role'] === 'Supervisor') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: employee_dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid email address or password.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In - Leave Application System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --primary: #0d9488;
            --primary-light: #ccfbf1;
            --primary-hover: #0f766e;
            --bg-main: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius-sm: 8px;
            --radius-md: 16px;
            --shadow-card: 0 10px 25px -5px rgba(15, 23, 42, 0.05), 0 8px 10px -6px rgba(15, 23, 42, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background-color: var(--bg-main);
            display: flex;
            min-height: 100vh;
            color: var(--text-main);
        }

        /* Split Screen Layout */
        .split-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Left Side: Brand Panel */
        .brand-panel {
            flex: 1.1;
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 50px;
            position: relative;
            overflow: visible; /* Allows shadow to cast outside */
            box-shadow: 15px 0 30px rgba(15, 23, 42, 0.25);
            z-index: 10; /* Ensures the shadow drops over the white side */
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            top: -100px; right: -100px;
        }

        .brand-logo {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-content h1 {
            font-size: 40px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -1px;
            margin-bottom: 16px;
        }

        .brand-content p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.85);
            max-width: 440px;
            line-height: 1.5;
        }

        .brand-footer {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Right Side: Form Panel */
        .form-panel {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            background: var(--card-bg);
        }

        .login-card {
            width: 440px;
            max-width: 100%;
        }

        .login-header {
            margin-bottom: 32px;
        }

        .login-header h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }

        .login-header p {
            font-size: 14px;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-main);
            font-size: 13px;
            font-weight: 700;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 14px;
            background: #f8fafc;
            color: var(--text-main);
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.12);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            border: none;
            color: white;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
            margin-top: 8px;
        }

        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-size: 13px;
            font-weight: 600;
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            text-align: center;
        }

        .register-link {
            text-align: center;
            margin-top: 28px;
            font-size: 14px;
            color: var(--text-muted);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* Responsive collapse for mobile */
        @media(max-width: 900px) {
            .brand-panel { display: none; }
        }
    </style>
</head>
<body>

    <div class="split-container">
        <!-- Left Brand Banner -->
        <div class="brand-panel">
            <div class="brand-logo">
                <span>LeavePortal</span>
            </div>
            <div class="brand-content">
                <h1>Streamline your workflow & leave management.</h1>
                <p>Manage leave credits, process employee requests securely, and coordinate team availability all in one unified portal.</p>
            </div>
            <div class="brand-footer">
                &copy; 2026 Leave Application System. All rights reserved.
            </div>
        </div>

        <!-- Right Login Form -->
        <div class="form-panel">
            <div class="login-card">
                <div class="login-header">
                    <h2>Sign In</h2>
                    <p>Enter your credentials to access your portal</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn">Sign In</button>
                </form>

                <div class="register-link">
                    Don't have an account? <a href="registration.php">Create account</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>