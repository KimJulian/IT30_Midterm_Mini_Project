<?php
session_start();
require_once 'db_connect.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $employee_no = !empty($_POST['employee_no']) ? trim($_POST['employee_no']) : null;
    
    // Automatically set default role to 'Employee'
    $role = 'Employee';

    if (!empty($username) && !empty($password) && !empty($employee_no)) {
        // Check if username already exists
        $stmt_check = $pdo->prepare("SELECT UserID FROM USERS WHERE Username = ?");
        $stmt_check->execute([$username]);
        
        if ($stmt_check->rowCount() > 0) {
            $error = "Username already taken. Please choose another.";
        } else {
            // Securely hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user into database with default 'Employee' role
            $stmt = $pdo->prepare("INSERT INTO USERS (Username, PasswordHash, Role, EmployeeNo) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $password_hash, $role, $employee_no])) {
                $message = "Registration successful! You can now <a href='login.php'>login here</a>.";
            } else {
                $error = "Registration failed. Please try again.";
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
    <title>Register - Leave Application System</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .register-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 350px; }
        .register-card h2 { margin-bottom: 20px; color: #333; text-align: center; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #666; }
        .form-group input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn { width: 100%; padding: 10px; background: #28a745; border: none; color: white; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #218838; }
        .error { color: red; font-size: 14px; margin-bottom: 15px; text-align: center; }
        .success { color: green; font-size: 14px; margin-bottom: 15px; text-align: center; }
        .login-link { text-align: center; margin-top: 15px; font-size: 14px; }
        .login-link a { color: #007bff; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="register-card">
        <h2>User Registration</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($message)): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        <form action="registration.php" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Employee Number (Linked ID)</label>
                <input type="number" name="employee_no" placeholder="e.g., 1" required>
            </div>
            <button type="submit" class="btn">Register Account</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>