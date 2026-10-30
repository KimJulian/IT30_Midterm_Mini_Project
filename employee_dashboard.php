<?php
session_start();
require_once 'db_connect.php';

// Security check: Must be logged in and have the 'Employee' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .content { margin-top: 20px; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .logout-btn { background: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Employee Dashboard (Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>)</h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="content">
        <h3>My Leave Overview</h3>
        <p>Welcome to your employee portal. Your leave balances and requests will appear here.</p>
    </div>
</body>
</html>