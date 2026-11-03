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
    $department = trim($_POST['department']);

    if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($password)) {
        // Check if email already exists
        $stmt_check = $pdo->prepare("SELECT * FROM USERS WHERE Email = ?");
        $stmt_check->execute([$email]);
        
        if ($stmt_check->rowCount() > 0) {
            $error = "Email address is already registered.";
        } else {
            // Generate unique EmployeeNo (e.g., E-04, E-05...)
            $stmt_count = $pdo->query("SELECT COUNT(*) FROM EMPLOYEE");
            $emp_count = $stmt_count->fetchColumn() + 1;
            $employee_no = 'E-' . str_pad($emp_count, 2, '0', STR_PAD_LEFT);

            // Hash password securely
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                // 1. Insert into EMPLOYEE table
                $stmt_emp = $pdo->prepare("INSERT INTO EMPLOYEE (EmployeeNo, FirstName, LastName, Department, LeaveCredits, Email) VALUES (?, ?, ?, ?, 15, ?)");
                $stmt_emp->execute([$employee_no, $first_name, $last_name, $department, $email]);

                // 2. Insert into USERS table (Default role: Employee)
                $stmt_user = $pdo->prepare("INSERT INTO USERS (Email, PasswordHash, Role, EmployeeNo) VALUES (?, ?, 'Employee', ?)");
                $stmt_user->execute([$email, $password_hash, $employee_no]);

                $pdo->commit();
                $success = "Registration successful! You can now <a href='login.php'>login here</a>.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed: " . $e->getMessage();
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
        .register-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 400px; }
        .register-card h2 { margin-bottom: 20px; color: #333; text-align: center; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #666; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
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
        <h2>Employee Registration</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form action="registration.php" method="POST">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" required>
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department" required>
                    <option value="">-- Select Department --</option>
                    <option value="Sales">Sales</option>
                    <option value="IT">IT</option>
                    <option value="Operations">Operations</option>
                    <option value="HR">HR</option>
                </select>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Register</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>