<?php
session_start();
require_once 'db_connect.php';

// Security check: Must be logged in as an Employee
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Employee') {
    header("Location: login.php");
    exit();
}

$employee_no = $_SESSION['EmployeeNo'] ?? '';
$action_message = '';
$error_message = '';

// Handle Leave Request Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_leave'])) {
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    if (!empty($leave_type_id) && !empty($start_date) && !empty($end_date)) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        if ($end >= $start) {
            $interval = $start->diff($end);
            $days = $interval->days + 1;

            $stmt_cred = $pdo->prepare("SELECT LeaveCredits FROM employee WHERE EmployeeNo = ?");
            $stmt_cred->execute([$employee_no]);
            $emp_data = $stmt_cred->fetch();
            $available_credits = $emp_data['LeaveCredits'] ?? 0;

            if ($available_credits >= $days) {
                $stmt_insert = $pdo->prepare("INSERT INTO leave_request (EmployeeNo, LeaveTypeID, StartDate, EndDate, Days, Status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                if ($stmt_insert->execute([$employee_no, $leave_type_id, $start_date, $end_date, $days])) {
                    $action_message = "Leave request submitted successfully!";
                }
            } else {
                $error_message = "Insufficient leave credits. You requested {$days} days, but only have {$available_credits} credits available.";
            }
        } else {
            $error_message = "End date cannot be earlier than start date.";
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

$stmt_emp = $pdo->prepare("SELECT * FROM employee WHERE EmployeeNo = ?");
$stmt_emp->execute([$employee_no]);
$employee = $stmt_emp->fetch();

$stmt_types = $pdo->query("SELECT * FROM leave_type");
$leave_types = $stmt_types->fetchAll();

$stmt_reqs = $pdo->prepare("
    SELECT r.RequestNo, t.TypeName AS LeaveTypeName, r.StartDate, r.EndDate, r.Days, r.Status 
    FROM leave_request r
    JOIN leave_type t ON r.LeaveTypeID = t.LeaveTypeID
    WHERE r.EmployeeNo = ?
    ORDER BY r.RequestNo DESC
");
$stmt_reqs->execute([$employee_no]);
$my_requests = $stmt_reqs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Portal - Leave Application System</title>
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
            --radius-lg: 20px;
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

        /* Top Header Bar */
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

        /* Grid Cards */
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        @media(max-width: 1024px) {
            .grid-container { grid-template-columns: 1fr; }
            .sidebar { width: 80px; padding: 16px; }
            .sidebar-brand span, .sidebar-menu span { display: none; }
            .main-content { margin-left: 80px; max-width: calc(100vw - 80px); }
        }

        .card {
            background: var(--card-bg);
            padding: 28px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
        }

        .card h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 16px;
        }

        .credit-display {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: white;
            padding: 24px;
            border-radius: var(--radius-md);
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px -5px rgba(13, 148, 136, 0.3);
        }

        .credit-number {
            font-size: 40px;
            font-weight: 800;
            letter-spacing: -1px;
            margin: 8px 0 4px 0;
        }

        /* Forms */
        .form-row { display: flex; gap: 16px; }
        .form-group { margin-bottom: 18px; flex: 1; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 12.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 14px;
            background: #f8fafc;
            color: var(--text-main);
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.12);
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-1px); }

        /* Tables */
        .table-card {
            background: var(--card-bg);
            padding: 28px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
        }

        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 14px; }
        th, td { padding: 14px 18px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background-color: #f8fafc; color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.6px; }
        tr:hover td { background-color: #f8fafc; }

        .badge { padding: 6px 12px; border-radius: 30px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }

        /* Alerts */
        .alert { padding: 14px 20px; border-radius: var(--radius-sm); margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #ccfbf1; color: #115e59; border: 1px solid #99f6e4; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <span>LeavePortal</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="employee_dashboard.php" class="active"><span>Dashboard</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><span>Sign Out</span></a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h2 style="font-size: 22px; font-weight: 800; letter-spacing: -0.5px;">Employee Dashboard</h2>
                <p style="color: var(--text-muted); font-size: 13.5px; margin-top: 2px;">Manage your leave credits and file new applications.</p>
            </div>
            <div class="user-profile">
                <div class="avatar">
                    <?php echo strtoupper(substr($employee['FirstName'] ?? 'E', 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 14px;"><?php echo htmlspecialchars(($employee['FirstName'] ?? '') . ' ' . ($employee['LastName'] ?? '')); ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($employee['Department'] ?? 'Unassigned Dept'); ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($action_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($action_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Grid Cards Section -->
        <div class="grid-container">
            <!-- Left Column: Balance Card -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                <div class="credit-display">
                    <div style="font-size: 12.5px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.8px; opacity: 0.9;">Available Leave Balance</div>
                    <div class="credit-number"><?php echo htmlspecialchars($employee['LeaveCredits'] ?? '0'); ?> <span style="font-size: 20px; font-weight: 600;">Days</span></div>
                    <div style="font-size: 13px; opacity: 0.85; margin-top: 4px;">Employee ID: <?php echo htmlspecialchars($employee_no); ?></div>
                </div>

                <div class="card" style="padding: 24px;">
                    <h3 style="margin-bottom: 12px; font-size: 15px;">Quick Guidelines</h3>
                    <ul style="padding-left: 18px; font-size: 13px; color: var(--text-muted); display: flex; flex-direction: column; gap: 8px;">
                        <li>Ensure requests are filed at least 3 days in advance.</li>
                        <li>Approved leaves automatically deduct from your balance.</li>
                        <li>Contact HR if your department is unassigned.</li>
                    </ul>
                </div>
            </div>

            <!-- Right Column: File Leave Form -->
            <div class="card">
                <h3>File New Leave Request</h3>
                <form action="employee_dashboard.php" method="POST">
                    <div class="form-group">
                        <label>Leave Type</label>
                        <select name="leave_type_id" required>
                            <option value="">-- Select Leave Type --</option>
                            <?php foreach ($leave_types as $type): ?>
                                <option value="<?php echo $type['LeaveTypeID']; ?>"><?php echo htmlspecialchars($type['TypeName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" required>
                        </div>
                    </div>
                    <button type="submit" name="submit_leave" class="btn-submit">Submit Leave Application</button>
                </form>
            </div>
        </div>

        <!-- Leave History Table -->
        <div class="table-card">
            <h3 style="margin-bottom: 16px;">My Leave Application History</h3>
            <table>
                <tr>
                    <th>Request No</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Status</th>
                </tr>
                <?php if (count($my_requests) > 0): ?>
                    <?php foreach ($my_requests as $req): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($req['RequestNo']); ?></strong></td>
                        <td><?php echo htmlspecialchars($req['LeaveTypeName']); ?></td>
                        <td><?php echo htmlspecialchars($req['StartDate']); ?></td>
                        <td><?php echo htmlspecialchars($req['EndDate']); ?></td>
                        <td><strong><?php echo htmlspecialchars($req['Days']); ?></strong></td>
                        <td>
                            <?php 
                                $status = $req['Status'];
                                $badge_class = 'badge-pending';
                                if ($status === 'Approved') $badge_class = 'badge-approved';
                                if ($status === 'Rejected') $badge_class = 'badge-rejected';
                            ?>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color: var(--text-muted); padding: 32px;">You haven't submitted any leave requests yet.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</body>
</html>