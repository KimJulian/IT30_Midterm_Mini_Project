<?php
session_start();
require_once 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        // Updated table name to lowercase 'users'
        $stmt = $pdo->prepare("SELECT * FROM users WHERE Email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['PasswordHash'])) {
            $_SESSION['UserID'] = $user['UserID'];
            $_SESSION['Email'] = $user['Email'];
            $_SESSION['Role'] = $user['Role'];
            $_SESSION['EmployeeNo'] = $user['EmployeeNo'];

            // Added 'Admin' to match your database seed role
            if ($user['Role'] === 'Admin' || $user['Role'] === 'HR_Admin' || $user['Role'] === 'Supervisor') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: employee_dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Leave Application System</title>
    <style>
    /* ============ BASE ============ */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Inter', -apple-system, 'Segoe UI', Arial, sans-serif;
        background: linear-gradient(135deg, #eef2f7 0%, #dfe6ef 50%, #e6f1ea 100%);
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        padding: 20px;
        color: #1f2937;
    }

    /* ============ LOGIN CARD ============ */
    .login-card {
        background: #ffffff;
        padding: 38px 32px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
        width: 380px;
        max-width: 100%;
        border: 1px solid #e5eaf1;
        position: relative;
        overflow: hidden;
        animation: fadeUp 0.45s ease;
    }
    .login-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 5px;
        background: linear-gradient(90deg, #007bff, #28a745);
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .login-card h2 {
        margin-bottom: 26px;
        color: #111827;
        text-align: center;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.4px;
    }

    /* ============ FORM ============ */
    .form-group { margin-bottom: 18px; }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        color: #4b5563;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .form-group input {
        width: 100%;
        padding: 11px 14px;
        box-sizing: border-box;
        border: 1.5px solid #d7dce3;
        border-radius: 10px;
        font-family: inherit;
        font-size: 14px;
        color: #1f2937;
        background: #f8fafc;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    }
    .form-group input::placeholder { color: #9ca3af; }
    .form-group input:hover { border-color: #b6bfc9; }
    .form-group input:focus {
        border-color: #007bff;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.12);
    }

    /* ============ BUTTON ============ */
    .btn {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #339dff, #007bff);
        border: none;
        color: white;
        border-radius: 10px;
        font-family: inherit;
        font-weight: 700;
        font-size: 14px;
        letter-spacing: 0.3px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        transition: transform 0.15s, box-shadow 0.15s;
        margin-top: 4px;
    }
    .btn:hover {
        background: #0056b3;
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(0, 86, 179, 0.35);
    }
    .btn:active { transform: translateY(0); }

    /* ============ ERROR ============ */
    .error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-left: 4px solid #dc3545;
        color: #b91c1c;
        font-size: 13px;
        font-weight: 600;
        padding: 11px 14px;
        border-radius: 8px;
        margin-bottom: 16px;
        text-align: center;
    }

    /* ============ REGISTER LINK ============ */
    .register-link {
        text-align: center;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid #f0f4f8;
        font-size: 13.5px;
        color: #6b7280;
    }
    .register-link a {
        color: #28a745;
        text-decoration: none;
        font-weight: 700;
        transition: color 0.15s;
    }
    .register-link a:hover {
        text-decoration: underline;
        color: #1d7a35;
    }
</style>
</head>
<body>
    <div class="login-card">
        <h2>System Login</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="e.g., admin@system.com" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <div class="register-link">
            Don't have an account? <a href="registration.php">Register here</a>
        </div>
    </div>
</body>
</html>