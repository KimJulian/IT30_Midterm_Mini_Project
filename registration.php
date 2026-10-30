<?php
session_start();
require_once 'db_connect.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $department = trim($_POST['department']);
    $role = 'Employee'; // Default role

    if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($password) && !empty($department)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if email already exists in USERS table
            $stmt_check = $pdo->prepare("SELECT UserID FROM USERS WHERE Email = ?");
            $stmt_check->execute([$email]);
            
            if ($stmt_check->rowCount() > 0) {
                $error = "An account with this email already exists.";
            } else {
                // 1. Automatically generate next EmployeeNo (e.g., EMP-00002)
                $stmt_last = $pdo->query("SELECT EmployeeNo FROM EMPLOYEE ORDER BY EmployeeNo DESC LIMIT 1");
                $last_emp = $stmt_last->fetch();
                
                if ($last_emp) {
                    $num = intval(substr($last_emp['EmployeeNo'], 4)) + 1;
                    $employee_no = 'EMP-' . str_pad($num, 5, '0', STR_PAD_LEFT);
                } else {
                    $employee_no = 'EMP-00001';
                }

                // Default supervisor for auto-registered staff
                $supervisor_id = 'SUP-00001'; 

                // 2. Insert into EMPLOYEE table
                $stmt_emp = $pdo->prepare("INSERT INTO EMPLOYEE (EmployeeNo, FirstName, MiddleName, LastName, Email, Department, SupervisorID) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                if ($stmt_emp->execute([$employee_no, $first_name, $middle_name, $last_name, $email, $department, $supervisor_id])) {
                    // 3. Insert into USERS table
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_user = $pdo->prepare("INSERT INTO USERS (Email, PasswordHash, Role, EmployeeNo) VALUES (?, ?, ?, ?)");
                    
                    if ($stmt_user->execute([$email, $password_hash, $role, $employee_no])) {
                        $message = "Registration successful! Your Employee ID is <b>$employee_no</b>. You can now <a href='login.php'>login here</a>.";
                    } else {
                        $error = "Failed to create user login credentials.";
                    }
                } else {
                    $error = "Failed to create employee record.";
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
    <title>Register - Leave Application System</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .register-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 400px; }
        .register-card h2 { margin-bottom: 20px; color: #333; text-align: center; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #666; }
        .form-group input, .form-group select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; font-size: 13px; color: #555; }
        .checkbox-group input { width: auto; }
        .btn { width: 100%; padding: 10px; background: #28a745; border: none; color: white; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #218838; }
        .error { color: red; font-size: 14px; margin-bottom: 15px; text-align: center; }
        .success { color: green; font-size: 14px; margin-bottom: 15px; text-align: center; }
        .login-link { text-align: center; margin-top: 15px; font-size: 14px; }
        .login-link a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="register-card">
        <h2>User Registration</h2>
        <?php if (!empty($error)): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (!empty($message)): ?><div class="success"><?php echo $message; ?></div><?php endif; ?>
        <form action="registration.php" method="POST">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" placeholder="e.g., Carla" required>
            </div>
            <div class="form-group">
                <label>Middle Name (Optional)</label>
                <input type="text" name="middle_name" placeholder="e.g., Santos">
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" placeholder="e.g., Dizon" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="e.g., carla.dizon@company.com" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter secure password" required>
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department" required>
                    <option value="">-- Select Department --</option>
                    <option value="Sales">Sales</option>
                    <option value="HR">HR</option>
                    <option value="IT">IT</option>
                    <option value="Operations">Operations</option>
                </select>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="manual_check" name="manual_check">
                <label for="manual_check" style="margin-bottom:0;">If department/supervisor does not exist, check here for manual HR assignment</label>
            </div>
            <button type="submit" class="btn">Register Account</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>