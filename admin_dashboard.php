<?php
session_start();
require_once 'db_connect.php';

// Security check: Must be logged in and have HR_Admin or Supervisor role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR_Admin', 'Supervisor'])) {
    header("Location: login.php");
    exit();
}

// Fetch all leave requests joined with employee names and leave types
$stmt = $pdo->prepare("
    SELECT r.RequestNo, e.EmployeeName, t.LeaveTypeName, r.StartDate, r.EndDate, r.Days, r.Decision 
    FROM LEAVE_REQUEST r 
    JOIN EMPLOYEE e ON r.EmployeeNo = e.EmployeeNo 
    JOIN LEAVE_TYPE t ON r.LeaveTypeID = t.LeaveTypeID
");
$stmt->execute();
$all_requests = $stmt->fetchAll();
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
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #28a745; color: white; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Manager / HR Dashboard (Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> - <?php echo $_SESSION['role']; ?>)</h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="content">
        <h3>Company-Wide Leave Requests Management</h3>
        <table>
            <tr>
                <th>Request No</th>
                <th>Employee Name</th>
                <th>Leave Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Days</th>
                <th>Status</th>
            </tr>
            <?php foreach ($all_requests as $req): ?>
            <tr>
                <td><?php echo htmlspecialchars($req['RequestNo']); ?></td>
                <td><?php echo htmlspecialchars($req['EmployeeName']); ?></td>
                <td><?php echo htmlspecialchars($req['LeaveTypeName']); ?></td>
                <td><?php echo htmlspecialchars($req['StartDate']); ?></td>
                <td><?php echo htmlspecialchars($req['EndDate']); ?></td>
                <td><?php echo htmlspecialchars($req['Days']); ?></td>
                <td><strong><?php echo htmlspecialchars($req['Decision']); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>