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

    if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($password) && !empty($confirm_password)) {
        if ($password === $confirm_password) {
            // Check if email already exists
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Email = ?");
            $stmt_check->execute([$email]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = "An account with this email already exists.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Generate a unique Employee Number
                    $stmt_count = $pdo->query("SELECT COUNT(*) FROM employee");
                    $emp_count = $stmt_count->fetchColumn() + 1;
                    $employee_no = 'EMP-' . str_pad($emp_count, 3, '0', STR_PAD_LEFT);

                    // 2. Insert into employee table (Department defaults to NULL until assigned by admin)
                    $stmt_emp = $pdo->prepare("INSERT INTO employee (EmployeeNo, FirstName, LastName, Department, LeaveCredits, Email) VALUES (?, ?, ?, NULL, 15.00, ?)");
                    $stmt_emp->execute([$employee_no, $first_name, $last_name, $email]);

                    // 3. Insert into users table with 'Employee' role
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_user = $pdo->prepare("INSERT INTO users (Email, PasswordHash, Role, EmployeeNo) VALUES (?, ?, 'Employee', ?)");
                    $stmt_user->execute([$email, $password_hash, $employee_no]);

                    $pdo->commit();
                    $success = "Registration successful! You can now <a href='login.php' style='color: var(--primary); font-weight: 600;'>sign in</a>.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        } else {
            $error = "Passwords do not match.";
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
    <title>Register - Leave Application System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :root {
            --primary: #0f766e;
            --primary-hover: #115e59;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius: 14px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            color: var(--text-main);
        }

        .register-card {
            background: var(--card-bg);
            padding: 40px 32px;
            border-radius: var(--radius);
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.08);
            width: 440px;
            max-width: 100%;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0f766e, #14b8a6);
        }

        .register-card h2 {
            margin-bottom: 24px;
            color: #0f172a;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .form-row { display: flex; gap: 12px; }
        .form-group { margin-bottom: 16px; flex: 1; }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
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
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1);
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            border: none;
            color: white;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.2);
            margin-top: 4px;
        }
        .btn:hover { background: var(--primary-hover); transform: translateY(-1px); }

        .error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-size: 13px;
            font-weight: 500;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            font-size: 13px;
            font-weight: 500;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .login-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            font-size: 13.5px;
            color: var(--text-muted);
        }
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="register-card">
        <h2>Employee Registration</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
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
                <input type="email" name="email" placeholder="john.doe@system.com" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create password" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm password" required>
            </div>
            <button type="submit" class="btn">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>
    </div>
</body>
</html>