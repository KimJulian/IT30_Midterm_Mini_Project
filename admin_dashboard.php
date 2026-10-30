<?php
session_start();
require_once 'db_connect.php';

// Security check: Must be logged in and have HR_Admin or Supervisor role
if (!isset($_SESSION['UserID']) || !in_array($_SESSION['Role'], ['HR_Admin', 'Supervisor'])) {
    header("Location: login.php");
    exit();
}

// Handle Department Assignment form submission
$assign_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_dept'])) {
    $emp_no = $_POST['employee_no'];
    $department = trim($_POST['department']);

    if (!empty($emp_no) && !empty($department)) {
        $stmt_update = $pdo->prepare("UPDATE EMPLOYEE SET Department = ? WHERE EmployeeNo = ?");
        if ($stmt_update->execute([$department, $emp_no])) {
            $assign_message = "Department successfully assigned!";
        }
    }
}

// Fetch all leave requests joined with employee names and leave types
$stmt = $pdo->prepare("
    SELECT r.RequestNo, CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName, t.LeaveTypeName, r.StartDate, r.EndDate, r.Days, r.Decision 
    FROM LEAVE_REQUEST r 
    JOIN EMPLOYEE e ON r.EmployeeNo = e.EmployeeNo 
    JOIN LEAVE_TYPE t ON r.LeaveTypeID = t.LeaveTypeID
");
$stmt->execute();
$all_requests = $stmt->fetchAll();

// Fetch employees who have no department assigned yet
$stmt_unassigned = $pdo->query("SELECT EmployeeNo, CONCAT(FirstName, ' ', LastName) AS FullName, Email FROM EMPLOYEE WHERE Department IS NULL OR Department = ''");
$unassigned_employees = $stmt_unassigned->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Leave Application System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .content { margin-top: 20px; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #28a745; color: white; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
        .assign-form { display: flex; gap: 10px; align-items: center; }
        .assign-form select, .assign-form button { padding: 5px; }
        .success-msg { color: green; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Admin Dashboard (Welcome, <?php echo htmlspecialchars($_SESSION['Email']); ?> - <?php echo $_SESSION['Role']; ?>)</h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <!-- Assign Departments to Newly Registered Employees -->
    <div class="content">
        <h3>Pending Employee Department Assignments</h3>
        <?php if (!empty($assign_message)): ?><div class="success-msg"><?php echo $assign_message; ?></div><?php endif; ?>
        <?php if (count($unassigned_employees) > 0): ?>
            <table>
                <tr>
                    <th>Employee ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Action (Assign Department)</th>
                </tr>
                <?php foreach ($unassigned_employees as $emp): ?>
                <tr>
                    <td><?php echo htmlspecialchars($emp['EmployeeNo']); ?></td>
                    <td><?php echo htmlspecialchars($emp['FullName']); ?></td>
                    <td><?php echo htmlspecialchars($emp['Email']); ?></td>
                    <td>
                        <form action="admin_dashboard.php" method="POST" class="assign-form">
                            <input type="hidden" name="employee_no" value="<?php echo $emp['EmployeeNo']; ?>">
                            <select name="department" required>
                                <option value="">-- Select Dept --</option>
                                <option value="Sales">Sales</option>
                                <option value="HR">HR</option>
                                <option value="IT">IT</option>
                                <option value="Operations">Operations</option>
                            </select>
                            <button type="submit" name="assign_dept" style="background:#007bff; color:white; border:none; border-radius:3px; cursor:pointer;">Assign</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p style="color: #666;">All registered employees have been assigned a department.</p>
        <?php endif; ?>
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