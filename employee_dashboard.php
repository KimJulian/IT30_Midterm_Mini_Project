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
    <title>Employee Dashboard - Leave Application System</title>
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

        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .card {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--border-color);
        }

        .card h3 { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 16px; }

        .content {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--border-color);
            overflow-x: auto;
        }

        .content h3 { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 16px; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background-color: #f1f5f9; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        tr:hover td { background-color: #f8fafc; }

        .badge { padding: 5px 10px; border-radius: 20px; font-weight: 600; font-size: 11.5px; display: inline-flex; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #475569; font-size: 13px; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            background: #f8fafc;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1);
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-1px); }

        .msg-box { background: #ccfbf1; color: #115e59; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border: 1px solid #99f6e4; }
        .err-box { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border: 1px solid #fecaca; }
        .credit-number { font-size: 32px; font-weight: 800; color: var(--primary); margin-top: 6px; letter-spacing: -1px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Employee Dashboard &mdash; Welcome, <?php echo htmlspecialchars($employee['FirstName'] ?? $_SESSION['Email']); ?></h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <?php if (!empty($action_message)): ?>
        <div class="msg-box"><?php echo htmlspecialchars($action_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="err-box"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card-container">
        <!-- Leave Credits Card -->
        <div class="card">
            <h3>My Leave Balance</h3>
            <p style="color: var(--text-muted); font-size: 13.5px;">Available credits for current term:</p>
            <div class="credit-number"><?php echo htmlspecialchars($employee['LeaveCredits'] ?? '0'); ?> Days</div>
            <p style="font-size: 12.5px; color: var(--text-muted); margin-top: 14px;">Department: <strong><?php echo htmlspecialchars($employee['Department'] ?? 'Unassigned'); ?></strong></p>
        </div>

        <!-- File Leave Request Form -->
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
                <div style="display: flex; gap: 12px;">
                    <div class="form-group" style="flex:1;">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>End Date</label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>
                <button type="submit" name="submit_leave" class="btn-submit">Submit Request</button>
            </form>
        </div>
    </div>

    <!-- My Leave History Table -->
    <div class="content">
        <h3>My Leave Application History</h3>
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
                    <td><?php echo htmlspecialchars($req['Days']); ?></td>
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
                    <td colspan="6" style="text-align:center; color: var(--text-muted); padding: 24px;">You haven't submitted any leave requests yet.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>