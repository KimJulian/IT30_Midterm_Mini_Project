<?php
session_start();
require_once 'db_connect.php';

// Security check: Must be logged in and have Admin, HR_Admin, or Supervisor role
if (!isset($_SESSION['UserID']) || !in_array($_SESSION['Role'], ['Admin', 'HR_Admin', 'Supervisor'])) {
    header("Location: login.php");
    exit();
}

$action_message = '';

// Handle Leave Request Decision (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_decision'])) {
    $request_no = $_POST['request_no'];
    $decision = $_POST['decision'];

    if (!empty($request_no) && in_array($decision, ['Approved', 'Rejected'])) {
        // 1. Fetch request details using lowercase table and Status column
        $stmt_check = $pdo->prepare("SELECT EmployeeNo, Days, Status FROM leave_request WHERE RequestNo = ?");
        $stmt_check->execute([$request_no]);
        $req_data = $stmt_check->fetch();

        if ($req_data) {
            $current_status = $req_data['Status'];
            $emp_no = $req_data['EmployeeNo'];
            $days = $req_data['Days'];

            // Begin transaction to ensure data integrity
            $pdo->beginTransaction();

            // 2. Update the status column
            $stmt_decide = $pdo->prepare("UPDATE leave_request SET Status = ? WHERE RequestNo = ?");
            $stmt_decide->execute([$decision, $request_no]);

            // 3. Enforce BR4: If newly Approved, deduct leave credits
            if ($decision === 'Approved' && $current_status !== 'Approved') {
                $stmt_deduct = $pdo->prepare("UPDATE employee SET LeaveCredits = LeaveCredits - ? WHERE EmployeeNo = ?");
                $stmt_deduct->execute([$days, $emp_no]);
            }
            
            $pdo->commit();
            $action_message = "Request {$request_no} has been " . strtolower($decision) . ".";
        }
    }
}

// Handle Department Assignment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_dept'])) {
    $emp_no = $_POST['employee_no'];
    $department = trim($_POST['department']);

    if (!empty($emp_no) && !empty($department)) {
        $stmt_update = $pdo->prepare("UPDATE employee SET Department = ? WHERE EmployeeNo = ?");
        if ($stmt_update->execute([$department, $emp_no])) {
            $action_message = "Department successfully assigned to {$emp_no}!";
        }
    }
}

// Fetch all leave requests using lowercase table names and correct column aliases
$stmt = $pdo->prepare("
    SELECT 
        r.RequestNo,
        e.EmployeeNo,
        CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
        e.Department,
        e.SupervisorID,
        CONCAT(sup.FirstName, ' ', sup.LastName) AS SupervisorName,
        t.TypeName AS LeaveType,
        r.StartDate,
        r.EndDate,
        r.Days,
        e.LeaveCredits AS CreditsAvailable,
        r.Status
    FROM leave_request r
    JOIN employee e ON r.EmployeeNo = e.EmployeeNo
    JOIN leave_type t ON r.LeaveTypeID = t.LeaveTypeID
    LEFT JOIN employee sup ON e.SupervisorID = sup.EmployeeNo
    ORDER BY r.RequestNo ASC
");
$stmt->execute();
$all_requests = $stmt->fetchAll();

// Fetch employees who have no department assigned yet
$stmt_unassigned = $pdo->query("SELECT EmployeeNo, CONCAT(FirstName, ' ', LastName) AS FullName, Email FROM employee WHERE Department IS NULL OR Department = ''");
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
        .content { margin-top: 20px; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; font-size: 14px; }
        th, td { border: 1px solid #005a5b; padding: 10px 8px; text-align: left; }
        th { background-color: #006666; color: white; white-space: nowrap; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; display: inline-block; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .btn-approve { background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; }
        .btn-reject { background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; }
        .msg-box { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .assign-form { display: flex; gap: 10px; align-items: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Admin Dashboard (Welcome, <?php echo htmlspecialchars($_SESSION['Email']); ?> - <?php echo $_SESSION['Role']; ?>)</h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <?php if (!empty($action_message)): ?>
        <div class="msg-box" style="margin-top:20px;"><?php echo htmlspecialchars($action_message); ?></div>
    <?php endif; ?>

    <!-- Section 1: Unassigned Employees -->
    <div class="content">
        <h3>Pending Employee Department Assignments</h3>
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
                            <button type="submit" name="assign_dept" style="background:#007bff; color:white; border:none; border-radius:3px; padding:5px 10px; cursor:pointer;">Assign</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p style="color: #666;">All registered employees have been assigned a department.</p>
        <?php endif; ?>
    </div>

    <!-- Section 2: Complete Leave Requests Table -->
    <div class="content">
        <h3>Company-Wide Leave Requests Management</h3>
        <table>
            <tr>
                <th>Request No</th>
                <th>Employee No</th>
                <th>Employee Name</th>
                <th>Department</th>
                <th>Supervisor ID</th>
                <th>Supervisor Name</th>
                <th>Leave Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Days</th>
                <th>Credits Available</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php if (count($all_requests) > 0): ?>
                <?php foreach ($all_requests as $req): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($req['RequestNo']); ?></strong></td>
                    <td><?php echo htmlspecialchars($req['EmployeeNo']); ?></td>
                    <td><?php echo htmlspecialchars($req['EmployeeName']); ?></td>
                    <td><?php echo htmlspecialchars($req['Department'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($req['SupervisorID'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($req['SupervisorName'] ?? 'None'); ?></td>
                    <td><?php echo htmlspecialchars($req['LeaveType']); ?></td>
                    <td><?php echo htmlspecialchars($req['StartDate']); ?></td>
                    <td><?php echo htmlspecialchars($req['EndDate']); ?></td>
                    <td><?php echo htmlspecialchars($req['Days']); ?></td>
                    <td><?php echo htmlspecialchars($req['CreditsAvailable'] ?? '0'); ?></td>
                    <td>
                        <?php 
                            $status = $req['Status'];
                            $badge_class = 'badge-pending';
                            if ($status === 'Approved') $badge_class = 'badge-approved';
                            if ($status === 'Rejected') $badge_class = 'badge-rejected';
                        ?>
                        <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                    </td>
                    <td>
                        <?php if ($status === 'Pending'): ?>
                            <form action="admin_dashboard.php" method="POST" style="display:inline-block;">
                                <input type="hidden" name="request_no" value="<?php echo $req['RequestNo']; ?>">
                                <input type="hidden" name="decision" value="Approved">
                                <button type="submit" name="update_decision" class="btn-approve">Approve</button>
                            </form>
                            <form action="admin_dashboard.php" method="POST" style="display:inline-block;">
                                <input type="hidden" name="request_no" value="<?php echo $req['RequestNo']; ?>">
                                <input type="hidden" name="decision" value="Rejected">
                                <button type="submit" name="update_decision" class="btn-reject">Reject</button>
                            </form>
                        <?php else: ?>
                            <span style="color: #999; font-size: 12px;">Completed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="13" style="text-align:center; color: #777;">No leave requests found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>