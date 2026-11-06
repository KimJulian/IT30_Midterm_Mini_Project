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

// Fetch all leave requests
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

// Fetch unassigned employees
$stmt_unassigned = $pdo->query("SELECT EmployeeNo, CONCAT(FirstName, ' ', LastName) AS FullName, Email FROM employee WHERE Department IS NULL OR Department = '' OR Department = 'Unassigned'");
$unassigned_employees = $stmt_unassigned->fetchAll();

// Fetch all employees for the new Employee Directory section
$stmt_all_emps = $pdo->query("
    SELECT e.EmployeeNo, CONCAT(e.FirstName, ' ', e.LastName) AS FullName, e.Email, e.Department, e.LeaveCredits, u.Role 
    FROM employee e
    LEFT JOIN users u ON e.EmployeeNo = u.EmployeeNo
    ORDER BY e.EmployeeNo ASC
");
$all_employees_directory = $stmt_all_emps->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Portal - Leave Application System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --primary: #0d9488;
            --primary-light: #ccfbf1;
            --primary-hover: #0f766e;
            --bg-main: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius-sm: 8px;
            --radius-md: 14px;
            --shadow-subtle: 0 4px 6px -1px rgba(15, 23, 42, 0.03), 0 2px 4px -2px rgba(15, 23, 42, 0.03);
            --shadow-card: 0 10px 25px -5px rgba(15, 23, 42, 0.05), 0 8px 10px -6px rgba(15, 23, 42, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 260px;
            background: var(--card-bg);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 24px;
            position: fixed;
            top: 0; bottom: 0; left: 0;
            z-index: 10;
        }

        .sidebar-brand {
            font-size: 18px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.5px;
        }

        .sidebar-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .sidebar-menu a.active, .sidebar-menu a:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .sidebar-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 13.5px;
            transition: background 0.2s;
        }
        .logout-btn:hover { background: #fecaca; }

        /* Main Content Wrapper */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 32px;
            max-width: calc(100vw - 260px);
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--card-bg);
            padding: 20px 32px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-color);
            margin-bottom: 28px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 42px; height: 42px;
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 15px;
        }

        @media(max-width: 1024px) {
            .sidebar { width: 80px; padding: 16px; }
            .sidebar-brand span, .sidebar-menu span { display: none; }
            .main-content { margin-left: 80px; max-width: calc(100vw - 80px); }
        }

        .content-card {
            background: var(--card-bg);
            padding: 28px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
            margin-bottom: 28px;
            overflow-x: auto;
        }

        .content-card h3 { font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 16px; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px; }
        th, td { padding: 14px 18px; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        th { background-color: #f8fafc; color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.6px; }
        tr:hover td { background-color: #f8fafc; }

        .badge { padding: 6px 12px; border-radius: 30px; font-weight: 700; font-size: 11.5px; display: inline-flex; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-role { background: #e0f2fe; color: #0369a1; }

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
            padding: 8px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 13px;
            background: #f8fafc;
        }
        .assign-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 8px 14px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .assign-btn:hover { background: var(--primary-hover); }

        .alert { padding: 14px 20px; border-radius: var(--radius-sm); margin-bottom: 24px; font-weight: 600; font-size: 14px; background: #ccfbf1; color: #115e59; border: 1px solid #99f6e4; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <span>AdminPortal</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="active"><span>Dashboard</span></a></li>
            <li><a href="#employees-section"><span>Employees</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><span>Sign Out</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h2 style="font-size: 22px; font-weight: 800; letter-spacing: -0.5px;">Admin Control Center</h2>
                <p style="color: var(--text-muted); font-size: 13.5px; margin-top: 2px;">Manage department assignments and review employee leave requests.</p>
            </div>
            <div class="user-profile">
                <div class="avatar">
                    <?php echo strtoupper(substr($_SESSION['Email'] ?? 'A', 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 14px;"><?php echo htmlspecialchars($_SESSION['Email']); ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($_SESSION['Role']); ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($action_message)): ?>
            <div class="alert"><?php echo htmlspecialchars($action_message); ?></div>
        <?php endif; ?>

        <!-- Section 1: Pending Department Assignments -->
        <div class="content-card">
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
                        <td><strong><?php echo htmlspecialchars($emp['EmployeeNo']); ?></strong></td>
                        <td><?php echo htmlspecialchars($emp['FullName']); ?></td>
                        <td><?php echo htmlspecialchars($emp['Email']); ?></td>
                        <td>
                            <form action="admin_dashboard.php" method="POST" class="assign-form">
                                <input type="hidden" name="employee_no" value="<?php echo $emp['EmployeeNo']; ?>">
                                <select name="department" required>
                                    <option value="">-- Select Dept --</option>
                                    <option value="Sales">Sales</option>
                                    <option value="HR">HR</option>
                                    <option value="Operations">Operations</option>
                                </select>
                                <button type="submit" name="assign_dept" class="assign-btn">Assign</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p style="color: var(--text-muted); font-size: 14px; padding: 12px 0;">All registered employees have been assigned a department.</p>
            <?php endif; ?>
        </div>

        <!-- Section 2: Employee Directory (New Sidebar Target) -->
        <div class="content-card" id="employees-section">
            <h3>Employee Directory</h3>
            <table>
                <tr>
                    <th>Employee ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>System Role</th>
                    <th>Leave Credits</th>
                </tr>
                <?php if (count($all_employees_directory) > 0): ?>
                    <?php foreach ($all_employees_directory as $direc): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($direc['EmployeeNo']); ?></strong></td>
                        <td><?php echo htmlspecialchars($direc['FullName']); ?></td>
                        <td><?php echo htmlspecialchars($direc['Email']); ?></td>
                        <td><?php echo htmlspecialchars($direc['Department'] ?? 'Unassigned'); ?></td>
                        <td><span class="badge badge-role"><?php echo htmlspecialchars($direc['Role'] ?? 'Employee'); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($direc['LeaveCredits']); ?> Days</strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color: var(--text-muted); padding: 32px;">No employees found.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Section 3: Company-Wide Leave Requests Management -->
        <div class="content-card">
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
                        <td colspan="13" style="text-align:center; color: var(--text-muted); padding: 32px;">No leave requests found.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</body>
</html>