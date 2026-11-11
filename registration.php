<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'Employee'; // Default role for all newly registered users
    $department = 'Unassigned'; // Default department for admin assignment

    if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($password) && !empty($confirm_password)) {
        if ($password !== $confirm_password) {
            $error = "Passwords do not match. Please try again.";
        } else {
            // Check if email already exists
            $stmt_check = $pdo->prepare("SELECT UserID FROM users WHERE Email = ?");
            $stmt_check->execute([$email]);
            if ($stmt_check->rowCount() > 0) {
                $error = "Email address is already registered.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Insert into employee table first to generate the auto-incremented EmployeeNo
                    $stmt_emp = $pdo->prepare("INSERT INTO employee (FirstName, LastName, Department, LeaveCredits) VALUES (?, ?, ?, 15.0)");
                    $stmt_emp->execute([$first_name, $last_name, $department]);
                    $employee_no = $pdo->lastInsertId();

                    // 2. Insert into users table using the correct $employee_no foreign key
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_user = $pdo->prepare("INSERT INTO users (EmployeeNo, Email, PasswordHash, Role) VALUES (?, ?, ?, ?)");
                    $stmt_user->execute([$employee_no, $email, $password_hash, $role]);

                    $pdo->commit();
                    $success = "Account successfully created! You can now sign in.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
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
    <title>Create Account - Leave Application System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --primary: #0d9488;
            --primary-light: #ccfbf1;
            --primary-hover: #0f766e;
            --bg-main: #e2e8f0;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius-md: 16px;
            --shadow-card: 0 20px 40px rgba(15, 23, 42, 0.12);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background-color: var(--bg-main);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px;
            color: var(--text-main);
        }

        .split-container {
            display: flex;
            width: 1050px;
            max-width: 100%;
            background: var(--card-bg);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            border: 1px solid var(--border-color);
            position: relative;
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
            overflow: visible;
            box-shadow: 15px 0 30px rgba(15, 23, 42, 0.25);
            z-index: 10;
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            top: -80px; right: -80px;
            pointer-events: none;
        }

        .brand-logo {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-content h1 {
            font-size: 34px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -1px;
            margin-bottom: 14px;
        }

        .brand-content p {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.5;
        }

        .brand-footer {
            font-size: 12.5px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Right Side: Form Panel */
        .form-panel {
            flex: 1.2;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            background: var(--card-bg);
            position: relative;
            z-index: 1;
        }

        .register-card {
            width: 100%;
            max-width: 420px;
        }

        .register-header {
            margin-bottom: 24px;
        }

        .register-header h2 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .register-header p {
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .form-row {
            display: flex;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 16px;
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-main);
            font-size: 12px;
            font-weight: 700;
        }

        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13.5px;
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
            padding: 13px;
            background: var(--primary);
            border: none;
            color: white;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
            margin-top: 4px;
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
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
        }

        .success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media(max-width: 850px) {
            .brand-panel { display: none; }
            .split-container { max-width: 460px; }
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
                <h1>Join the enterprise portal today.</h1>
                <p>Register your account to manage leave applications, track credit balances, and coordinate team schedules seamlessly.</p>
            </div>
            <div class="brand-footer">
                &copy; 2026 Leave Application System. All rights reserved.
            </div>
        </div>

        <!-- Right Registration Form -->
        <div class="form-panel">
            <div class="register-card">
                <div class="register-header">
                    <h2>Create Account</h2>
                    <p>Enter your details to register a new account</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form action="registration.php" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" placeholder="John" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" placeholder="Doe" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="name@company.com" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Create password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn">Create Account</button>
                </form>

                <div class="login-link">
                    Already have an account? <a href="login.php">Sign in</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>