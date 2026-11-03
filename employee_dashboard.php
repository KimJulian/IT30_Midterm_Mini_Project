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
        // Calculate days inclusive of start and end date
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        if ($end >= $start) {
            $interval = $start->diff($end);
            $days = $interval->days + 1;

            // Check if employee has enough credits available
            $stmt_cred = $pdo->prepare("SELECT LeaveCredits FROM employee WHERE EmployeeNo = ?");
            $stmt_cred->execute([$employee_no]);
            $emp_data = $stmt_cred->fetch();
            $available_credits = $emp_data['LeaveCredits'] ?? 0;

            if ($available_credits >= $days) {
                // Insert request (RequestNo is auto-increment integer in leave_request table)
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

// Fetch employee's details and credit balance
$stmt_emp = $pdo->prepare("SELECT * FROM employee WHERE EmployeeNo = ?");
$stmt_emp->execute([$employee_no]);
$employee = $stmt_emp->fetch();

// Fetch leave types for dropdown selection
$stmt_types = $pdo->query("SELECT * FROM leave_type");
$leave_types = $stmt_types->fetchAll();

// Fetch leave requests submitted by this employee
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
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-container { display: flex; gap: 20px; margin-top: 20px; }
        .card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex: 1; }
        .content { margin-top: 20px; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        th, td { border: 1px solid #005a5b; padding: 10px 8px; text-align: left; }
        th { background-color: #006666; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; display: inline-block; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #444; }
        .form-group input, .form-group select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn-submit { background: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; width: 100%; }
        .btn-submit:hover { background: #218838; }
        .msg-box { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .err-box { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .credit-number { font-size: 28px; font-weight: bold; color: #006666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Employee Dashboard (Welcome, <?php echo htmlspecialchars($employee['FirstName'] ?? $_SESSION['Email']); ?>)</h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <?php if (!empty($action_message)): ?>
        <div class="msg-box" style="margin-top:20px;"><?php echo htmlspecialchars($action_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="err-box" style="margin-top:20px;"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card-container">
        <!-- Leave Credits Card -->
        <div class="card">
            <h3>My Leave Balance</h3>
            <p style="color: #666; margin-top: 5px;">Available credits for this year:</p>
            <div class="credit-number"><?php echo htmlspecialchars($employee['LeaveCredits'] ?? '0'); ?> Days</div>
            <p style="font-size: 12px; color: #888; margin-top: 10px;">Department: <?php echo htmlspecialchars($employee['Department'] ?? 'Unassigned'); ?></p>
        </div>

        <!-- File Leave Request Form -->
        <div class="card">
            <h3>File New Leave Request</h3>
            <form action="employee_dashboard.php" method="POST">
                <div class="form-group" style="margin-bottom: 10px;">
                    <label>Leave Type</label>
                    <select name="leave_type_id" required>
                        <option value="">-- Select Leave Type --</option>
                        <?php foreach ($leave_types as $type): ?>
                            <option value="<?php echo $type['LeaveTypeID']; ?>"><?php echo htmlspecialchars($type['TypeName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex:1; margin-bottom: 10px;">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group" style="flex:1; margin-bottom: 10px;">
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
                    <td><strong><?php echo htmlspecialchars($req['RequestNo']); ?></strong></td>
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
                    <td colspan="6" style="text-align:center; color: #777;">You haven't submitted any leave requests yet.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>