<?php
session_start();
require_once 'db_connect.php';

// Security check: Must be logged in and have Admin, HR_Admin, or Supervisor role
if (!isset($_SESSION['UserID']) || !in_array($_SESSION['Role'], ['Admin', 'HR_Admin', 'Supervisor'])) {
    header("Location: login.php");
    exit();
}

// Fetch all employees for the directory
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
    <title>Employee Directory - Admin Portal</title>
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
            overflow-x: auto;
        }

        .content-card h3 { font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 16px; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px; }
        th, td { padding: 14px 18px; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        th { background-color: #f8fafc; color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.6px; }
        tr:hover td { background-color: #f8fafc; }

        .badge-role { background: #e0f2fe; color: #0369a1; padding: 6px 12px; border-radius: 30px; font-weight: 700; font-size: 11.5px; display: inline-flex; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <span>AdminPortal</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><span>Dashboard</span></a></li>
            <li><a href="employees.php" class="active"><span>Employees</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><span>Sign Out</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h2 style="font-size: 22px; font-weight: 800; letter-spacing: -0.5px;">Employee Directory</h2>
                <p style="color: var(--text-muted); font-size: 13.5px; margin-top: 2px;">View all registered company personnel, roles, and leave balances.</p>
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

        <div class="content-card">
            <h3>Registered Personnel Directory</h3>
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
                        <td><span class="badge-role"><?php echo htmlspecialchars($direc['Role'] ?? 'Employee'); ?></span></td>
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
    </div>
</body>
</html>