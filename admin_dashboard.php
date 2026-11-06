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
        $stmt_check = $pdo->prepare("SELECT EmployeeNo, Days, Status FROM leave_request WHERE RequestNo = ?");
        $stmt_check->execute([$request_no]);
        $req_data = $stmt_check->fetch();

        if ($req_data) {
            $current_status = $req_data['Status'];
            $emp_no = $req_data['EmployeeNo'];
            $days = $req_data['Days'];

            $pdo->beginTransaction();

            $stmt_decide = $pdo->prepare("UPDATE leave_request SET Status = ? WHERE RequestNo = ?");
            $stmt_decide->execute([$decision, $request_no]);

            if ($decision === 'Approved' && $current_status !== 'Approved') {
                $stmt_deduct = $pdo->prepare("UPDATE employee SET LeaveCredits = LeaveCredits - ? WHERE EmployeeNo = ?");
                $stmt_deduct->execute([$days, $emp_no]);
            }
            
            $pdo->commit();
            $action_message = "Request #{$request_no} has been " . strtolower($decision) . ".";
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
            $action_message = "Department successfully assigned to employee {$emp_no}!";
        }
    }
}

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

$stmt_unassigned = $pdo->query("SELECT EmployeeNo, CONCAT(FirstName, ' ', LastName) AS FullName, Email FROM employee WHERE Department IS NULL OR Department = ''");
$unassigned_employees = $stmt_unassigned->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Leave Application System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :root {
            --primary: #0f766e;
            --primary-hover: #115e59;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            padding: 24px;
            line-height: 1.5;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--card-bg);
            padding: 20px 28px;
            border-radius: var(--radius);
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .header h2 { font-size: 20px; font-weight: 700; color: #0f172a; }

        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13.5px;
            transition: background 0.2s, transform 0.1s;
        }
        .logout-btn:hover { background: #dc2626; transform: translateY(-1px); }

        .content {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            overflow-x: auto;
        }

        .content h3 { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 16px; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        th { background-color: #f1f5f9; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        tr:hover td { background-color: #f8fafc; }

        .badge { padding: 5px 10px; border-radius: 20px; font-weight: 600; font-size: 11.5px; display: inline-flex; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }

        .btn-approve {
            background: #10b981;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-approve:hover { background: #059669; }

        .btn-reject {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-reject:hover { background: #dc2626; }

        .assign-form { display: flex; gap: 10px; align-items: center; }
        .assign-form select {
            padding: 6px 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 13px;
            background: #f8fafc;
        }
        .assign-btn {
            background: #0284c7;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .assign-btn:hover { background: #0369a1; }

        .msg-box { background: #ccfbf1; color: #115e59; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border: 1px solid #99f6e4; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Admin Dashboard &mdash; Welcome, <?php echo htmlspecialchars($_SESSION['Email']); ?> (<?php echo $_SESSION['Role']; ?>)</h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <?php if (!empty($action_message)): ?>
        <div class="msg-box"><?php echo htmlspecialchars($action_message); ?></div>
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
                            <button type="submit" name="assign_dept" class="assign-btn">Assign</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p style="color: var(--text-muted); font-size: 14px;">All registered employees have been assigned a department.</p>
        <?php endif; ?>
    </div>

    <!-- Section 2: Complete Leave Requests Table -->
    <div class="content">
        <h3>Company-Wide Leave Requests Management</h3>
        <table>
            <tr>
                <th>Request No</th>
                <th>Emp No</th>
                <th>Employee Name</th>
                <th>Department</th>
                <th>Supervisor ID</th>
                <th>Supervisor Name</th>
                <th>Leave Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Days</th>
                <th>Credits</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php if (count($all_requests) > 0): ?>
                <?php foreach ($all_requests as $req): ?>
                <tr>
                    <td><strong>#<?php echo htmlspecialchars($req['RequestNo']); ?></strong></td>
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
                            <div style="display: flex; gap: 6px;">
                                <form action="admin_dashboard.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="request_no" value="<?php echo $req['RequestNo']; ?>">
                                    <input type="hidden" name="decision" value="Approved">
                                    <button type="submit" name="update_decision" class="btn-approve">Approve</button>
                                </form>
                                <form action="admin_dashboard.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="request_no" value="<?php echo $req['RequestNo']; ?>">
                                    <input type="hidden" name="decision" value="Rejected">
                                    <button type="submit" name="update_decision" class="btn-reject">Reject</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 12.5px; font-style: italic;">Completed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="13" style="text-align:center; color: var(--text-muted); padding: 24px;">No leave requests found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>