<?php
session_start();
require_once 'db_connect.php';

$message = '';
$error = '';

// Fetch employees so the user can select their name from a dropdown
$stmt_emp = $pdo->query("SELECT EmployeeNo, EmployeeName FROM EMPLOYEE");
$employees = $stmt_emp->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $employee_no = trim($_POST['employee_no']);
    $role = 'Employee'; // Default role

    if (!empty($username) && !empty($email) && !empty($password) && !empty($employee_no)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if username already exists
            $stmt_check = $pdo->prepare("SELECT UserID FROM USERS WHERE Username = ?");
            $stmt_check->execute([$username]);
            
            if ($stmt_check->rowCount() > 0) {
                $error = "Username already taken. Please choose another.";
            } else {
                // Check if this EmployeeNo is already linked to another user account
                $stmt_link_check = $pdo->prepare("SELECT UserID FROM USERS WHERE EmployeeNo = ?");
                $stmt_link_check->execute([$employee_no]);

                if ($stmt_link_check->rowCount() > 0) {
                    $error = "An account is already linked to this employee record.";
                } else {
                    // Securely hash the password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new user into database
                    $stmt = $pdo->prepare("INSERT INTO USERS (Username, PasswordHash, Role, EmployeeNo) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$username, $password_hash, $role, $employee_no])) {
                        // Update email in employee table
                        $stmt_email = $pdo->prepare("UPDATE EMPLOYEE SET Email = ? WHERE EmployeeNo = ?");
                        $stmt_email->execute([$email, $employee_no]);

                        $message = "Registration successful! You can now <a href='login.php'>login here</a>.";
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
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
        .register-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 350px; }
        .register-card h2 { margin-bottom: 20px; color: #333; text-align: center; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #666; }
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
                <input type="text" name="username" placeholder="e.g., Juan_DelaCruz" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="e.g., juandelacruz@gmail.com" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="At least 8 characters" required>
            </div>
            <div class="form-group">
                <label>Select Your Employee Profile</label>
                <select name="employee_no" required>
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['EmployeeNo']; ?>">
                            <?php echo htmlspecialchars($emp['EmployeeName'] . " (ID: " . $emp['EmployeeNo'] . ")"); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Register Account</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>