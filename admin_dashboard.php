<?php
session_start();
require_once 'db_connect.php';

// Security check: Must be logged in and have HR_Admin or Supervisor role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR_Admin', 'Supervisor'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR / Supervisor Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .content { margin-top: 20px; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .logout-btn { background: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Manager / HR Dashboard (Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> - <?php echo $_SESSION['role']; ?>)</h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="content">
        <h3>Company-Wide Leave Management</h3>
        <p>Welcome to the management portal. Company-wide leave requests will appear here.</p>
    </div>
</body>
</html>