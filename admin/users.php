<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header("Location: ../login.php");
    exit;
}

require_once '../config.php';

$success = '';
$error = '';

// Handle add user/admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'customer';

    $allowed_roles = ['customer', 'admin'];
    if ($_SESSION['role'] === 'superadmin') {
        $allowed_roles[] = 'superadmin';
    }

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, $allowed_roles)) {
        $error = 'Invalid role selected.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'A user with this email address already exists.';
            } else {
                $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone ?: null, $hashed_pw, $role]);
                $success = "User '{$name}' successfully registered as " . ($role === 'superadmin' ? 'a Super Administrator' : ($role === 'admin' ? 'an Admin' : 'a Customer')) . ".";
            }
        } catch (Exception $e) {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// Handle delete user
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    // Prevent admin from deleting themselves
    if ($delete_id === (int)$_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT name, role FROM users WHERE id = ?");
            $stmt->execute([$delete_id]);
            $userToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userToDelete) {
                if ($userToDelete['role'] === 'superadmin' && $_SESSION['role'] !== 'superadmin') {
                    $error = "You do not have permission to delete a superadmin.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$delete_id]);
                    $success = "User '{$userToDelete['name']}' deleted successfully.";
                }
            } else {
                $error = "User not found.";
            }
        } catch (Exception $e) {
            $error = "Failed to delete user: " . $e->getMessage();
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'do_reset_password') {
    $reset_id = (int)$_POST['reset_id'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT name, role FROM users WHERE id = ?");
        $stmt->execute([$reset_id]);
        $userToReset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userToReset) {
            $can_reset = ($_SESSION['role'] === 'superadmin') || ($userToReset['role'] === 'customer' && $_SESSION['role'] === 'admin');
            if (!$can_reset) {
                $error = "You do not have permission to reset this user's password.";
            } elseif (empty($new_password) || empty($confirm_new_password)) {
                $error = 'Please fill in all fields.';
            } elseif ($new_password !== $confirm_new_password) {
                $error = 'New passwords do not match.';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                $hashed_pw = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->execute([$hashed_pw, $reset_id]);
                $success = "Password for user '" . htmlspecialchars($userToReset['name']) . "' has been reset successfully.";
                unset($_GET['reset_id']);
            }
        } else {
            $error = 'User not found.';
        }
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
}

// Prepare info for reset form if reset_id is specified
$userToReset = null;
if (isset($_GET['reset_id'])) {
    $reset_id = (int)$_GET['reset_id'];
    $stmt = $pdo->prepare("SELECT name, email, role FROM users WHERE id = ?");
    $stmt->execute([$reset_id]);
    $userToReset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userToReset) {
        $can_reset = ($_SESSION['role'] === 'superadmin') || ($userToReset['role'] === 'customer' && $_SESSION['role'] === 'admin');
        if (!$can_reset) {
            $error = "You do not have permission to reset this user's password.";
            $userToReset = null;
            unset($_GET['reset_id']);
        }
    } else {
        $error = "User not found.";
        unset($_GET['reset_id']);
    }
}

// Tab selection
$tab = $_GET['tab'] ?? 'customer';
if (!in_array($tab, ['customer', 'admin'])) {
    $tab = 'customer';
}

// Fetch users based on selected tab and logged in role
if ($tab === 'admin') {
    if ($_SESSION['role'] === 'superadmin') {
        // Superadmin can see both normal admins and superadmins
        $stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin' OR role = 'superadmin' ORDER BY id DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Normal admin can only see normal admins (superadmins are hidden)
        $stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Customers
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY id DESC");
    $stmt->execute([$tab]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Digi Pro X 24 Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .tabs-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color, #cbd5e1);
            padding-bottom: 1rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 0.65rem 1.3rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.25s ease-in-out;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-btn:hover:not(.active) {
            background: #f1f5f9;
            color: #ff5e00;
            border-color: #ff5e00;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #ff5e00, #ea580c) !important;
            border-color: #ff5e00 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 15px rgba(255, 94, 0, 0.35);
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .role-badge.customer {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        .role-badge.admin {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .role-badge.superadmin {
            background: #f3e8ff;
            color: #7e22ce;
            border: 1px solid #d8b4fe;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="../index.php" class="sidebar-brand">
            Digi Pro X 24 <br><span>Admin Panel</span>
        </a>
        <div class="sidebar-footer">
            <a href="../index.php" style="text-align: center;">🌐 View Site</a>
            <a href="../logout.php" style="text-align: center;">🚪 Logout</a>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'class="active"' : ''; ?>>📊 Admin Dashboard</a></li>
            <li><a href="categories.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'categories.php') ? 'class="active"' : ''; ?>>📁 Categories</a></li>
            <li><a href="products.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'class="active"' : ''; ?>>🛍️ Products</a></li>
            <li><a href="services.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'services.php') ? 'class="active"' : ''; ?>>🛠️ Services</a></li>
            <li><a href="reviews.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'reviews.php') ? 'class="active"' : ''; ?>>⭐ Q & A Reviews <?php if(isset($sidebar_rev) && $sidebar_rev > 0): ?><span style="background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem; margin-left:5px;"><?php echo $sidebar_rev; ?></span><?php endif; ?></a></li>
            <li><a href="orders.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'class="active"' : ''; ?>>📦 Orders</a></li>
            <li><a href="service_requests.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'service_requests.php') ? 'class="active"' : ''; ?>>📋 Service Requests <?php if(isset($sidebar_sr) && $sidebar_sr > 0): ?><span style="background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem; margin-left:5px;"><?php echo $sidebar_sr; ?></span><?php endif; ?></a></li>
            <li><a href="messages.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'messages.php') ? 'class="active"' : ''; ?>>💬 Messages <?php if(isset($sidebar_msg) && $sidebar_msg > 0): ?><span style="background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem; margin-left:5px;"><?php echo $sidebar_msg; ?></span><?php endif; ?></a></li>
            <li><a href="users.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'class="active"' : ''; ?>>👥 Users</a></li>
            <li><a href="change_password.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'change_password.php') ? 'class="active"' : ''; ?>>🔒 Change Password</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="sidebar-toggle" id="menu-toggle">☰</button>
                <div>
                    <span style="color: var(--text-muted); font-weight:600; font-size:0.9rem;">Accounts</span>
                    <h1>Manage Users</h1>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <button onclick="toggleAddUser()" class="btn-add" style="border: none; cursor: pointer; text-decoration: none; font-family: inherit; font-size: 0.95rem;">➕ Add User</button>
                <div class="header-user-badge">
                    Logged in as: <span style="color: var(--primary-glow, #3b82f6); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
                </div>
            </div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="msg-banner success-banner"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="msg-banner error-banner"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Users List (Top) -->
        <div class="panel-box" style="margin-bottom: 2rem;">
            <div class="tabs-header">
                <a href="users.php?tab=customer" class="tab-btn <?php echo $tab === 'customer' ? 'active' : ''; ?>">👥 Registered Users</a>
                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin'): ?>
                    <a href="users.php?tab=admin" class="tab-btn <?php echo $tab === 'admin' ? 'active' : ''; ?>">🛡️ Admins</a>
                <?php endif; ?>
            </div>

            <h2><?php echo $tab === 'admin' ? 'Admin Users List' : 'Registered Customers List'; ?></h2>
            
            <div class="admin-table-wrapper">
                <table class="admin-table responsive-cards">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Joined Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted);">
                                    No <?php echo $tab === 'admin' ? 'admin users' : 'customers'; ?> found in database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="td-id" data-label="ID">#<?php echo $u['id']; ?></td>
                                    <td class="td-info" data-label="Name" style="font-weight: 600;">
                                        <?php echo htmlspecialchars($u['name']); ?>
                                    </td>
                                    <td data-label="Email"><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td data-label="Phone"><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                                    <td data-label="Role">
                                        <span class="role-badge <?php echo $u['role']; ?>">
                                            <?php echo ucfirst($u['role']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Joined Date" style="font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo date('Y-m-d H:i', strtotime($u['created_at'])); ?>
                                    </td>
                                    <td class="td-actions action-forms" data-label="Action" style="text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                        <?php 
                                        $can_reset = ($_SESSION['role'] === 'superadmin') || ($u['role'] === 'customer' && $_SESSION['role'] === 'admin');
                                        if ($can_reset): 
                                        ?>
                                            <a href="users.php?tab=<?php echo $tab; ?>&reset_id=<?php echo $u['id']; ?>#reset-section" 
                                               class="btn-reset" 
                                               style="background: #fff7ed; color: #ea580c; border: 1px solid rgba(255, 94, 0, 0.3); padding: 0.35rem 0.8rem; border-radius: 8px; text-decoration: none; font-size: 0.8rem; font-weight: 600; transition: all 0.2s;">
                                                Reset Password
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                            <?php 
                                            $can_delete = ($u['role'] !== 'superadmin') || ($_SESSION['role'] === 'superadmin');
                                            if ($can_delete): 
                                            ?>
                                                <a href="users.php?tab=<?php echo $tab; ?>&delete=<?php echo $u['id']; ?>" 
                                                   class="btn-delete" 
                                                   onclick="return confirm('Are you sure you want to delete user \'<?php echo addslashes($u['name']); ?>\'? This action cannot be undone.');"
                                                   style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 0.35rem 0.8rem; border-radius: 8px; text-decoration: none; font-size: 0.8rem; font-weight: 600; transition: all 0.2s;">
                                                    Delete
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="font-size: 0.8rem; color: var(--text-light); font-style: italic;">You</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add User / Reset Password Section (Under the List) -->
        <?php if (isset($_GET['reset_id']) && $userToReset): ?>
            <div class="panel-box" id="reset-section">
                <h2>Reset User Password</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Resetting password for: <strong><?php echo htmlspecialchars($userToReset['name']); ?></strong> (<?php echo htmlspecialchars($userToReset['email']); ?>)</p>
                <form action="users.php?tab=<?php echo $tab; ?>&reset_id=<?php echo $reset_id; ?>" method="POST">
                    <input type="hidden" name="action" value="do_reset_password">
                    <input type="hidden" name="reset_id" value="<?php echo $reset_id; ?>">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.2rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="new_password">New Password *</label>
                            <input type="password" name="new_password" id="new_password" class="form-input" placeholder="••••••••" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="confirm_new_password">Confirm New Password *</label>
                            <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-input" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.8rem; margin-top: 1.5rem;">
                        <button type="submit" class="btn-submit" style="padding: 0.8rem 2rem; margin: 0; width: 100%; box-sizing: border-box;">Reset Password</button>
                        <a href="users.php?tab=<?php echo $tab; ?>" class="tab-btn" style="padding: 0.8rem 2rem; margin: 0; text-align: center; display: inline-flex; align-items: center; justify-content: center; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 12px; font-weight: 600; text-decoration: none; width: 100%; box-sizing: border-box;">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="panel-box" id="add-user-section" style="display: <?php echo ($error !== '' && $_POST['action'] === 'add_user') ? 'block' : 'none'; ?>;">
                <h2>Register New User</h2>
                <form action="users.php?tab=<?php echo $tab; ?>" method="POST">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.2rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="name">Full Name *</label>
                            <input type="text" name="name" id="name" class="form-input" placeholder="e.g. John Doe" required>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="email">Email Address *</label>
                            <input type="email" name="email" id="email" class="form-input" placeholder="e.g. john@example.com" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="text" name="phone" id="phone" class="form-input" placeholder="e.g. 0771234567">
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="role">User Role *</label>
                            <select name="role" id="role" class="form-input" style="background: #ffffff; color: #0f172a;" required>
                                <option value="customer" <?php echo $tab === 'customer' ? 'selected' : ''; ?>>Customer (Registered User)</option>
                                <option value="admin" <?php echo $tab === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                    <option value="superadmin">Super Administrator</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="password">Password *</label>
                            <input type="password" name="password" id="password" class="form-input" placeholder="••••••••" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="confirm_password">Confirm Password *</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" style="margin-top: 1.5rem;">Register Account</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <script>
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function toggleMenu() {
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) {
                overlay.style.display = 'block';
            } else {
                overlay.style.display = 'none';
            }
        }

        function toggleAddUser() {
            const sec = document.getElementById('add-user-section');
            if (sec.style.display === 'none' || sec.style.display === '') {
                sec.style.display = 'block';
                sec.scrollIntoView({behavior: 'smooth'});
            } else {
                sec.style.display = 'none';
            }
        }

        menuToggle.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    </script>
</body>
</html>
