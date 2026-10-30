<?php
session_start();
require_once 'db_connect.php';

// Security check: Must be logged in and have the 'Employee' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header("Location: login.php");
    exit();
}

$employee_no = $_SESSION['employee_no'];

// Fetch employee leave balances
$stmt_balance = $pdo->prepare("SELECT * FROM LEAVE_BALANCE WHERE EmployeeNo = ?");
$stmt_balance->execute([$employee_no]);
$balances = $stmt_balance->fetchAll();

// Fetch employee leave requests joined with leave types
$stmt_requests = $pdo->prepare("
    SELECT r.RequestNo, r.StartDate, r.EndDate, r.Days, r.Decision, t.LeaveTypeName 
    FROM LEAVE_REQUEST r 
    JOIN LEAVE_TYPE t ON r.LeaveTypeID = t.LeaveTypeID 
    WHERE r.EmployeeNo = ?
");
$stmt_requests->execute([$employee_no]);
$requests = $stmt_requests->fetchAll();
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
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #007bff; color: white; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Employee Dashboard (Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>)</h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="content">
        <h3>My Leave Balances</h3>
        <table>
            <tr><th>Leave Type ID</th><th>Year</th><th>Credits Available</th></tr>
            <?php foreach ($balances as $b): ?>
            <tr>
                <td><?php echo htmlspecialchars($b['LeaveTypeID']); ?></td>
                <td><?php echo htmlspecialchars($b['BalanceYear']); ?></td>
                <td><?php echo htmlspecialchars($b['CreditsAvailable']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h3 style="margin-top: 30px;">My Leave Requests</h3>
        <table>
            <tr><th>Request No</th><th>Leave Type</th><th>Start Date</th><th>End Date</th><th>Days</th><th>Decision</th></tr>
            <?php foreach ($requests as $r): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['RequestNo']); ?></td>
                <td><?php echo htmlspecialchars($r['LeaveTypeName']); ?></td>
                <td><?php echo htmlspecialchars($r['StartDate']); ?></td>
                <td><?php echo htmlspecialchars($r['EndDate']); ?></td>
                <td><?php echo htmlspecialchars($r['Days']); ?></td>
                <td><strong><?php echo htmlspecialchars($r['Decision']); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>