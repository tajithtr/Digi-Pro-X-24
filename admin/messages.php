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

// Auto-create table if not existing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_messages` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `email` varchar(255) NOT NULL,
      `message` text NOT NULL,
      `status` enum('unread','read','replied') DEFAULT 'unread',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (PDOException $e) {}

$success = '';
$error = '';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $msgId = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'mark_read') {
        $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
        $stmt->execute([$msgId]);
        $success = "Message marked as read.";
    } elseif ($action === 'mark_replied') {
        $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?");
        $stmt->execute([$msgId]);
        $success = "Message marked as replied.";
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$msgId]);
        $success = "Message deleted successfully.";
    }
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    if (!empty($ids)) {
        $in  = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id IN ($in)");
        $stmt->execute($ids);
        $success = count($ids) . " messages deleted successfully.";
    }
}

// Filter by status
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$query = "SELECT * FROM contact_messages";
$params = [];

if (in_array($statusFilter, ['unread', 'read', 'replied'])) {
    $query .= " WHERE status = ?";
    $params[] = $statusFilter;
}
$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$unreadCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'")->fetchColumn();
$readCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'read'")->fetchColumn();
$repliedCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'replied'")->fetchColumn();
$totalCount = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Messages - Admin Digi Pro X 24</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 0.6rem 1.4rem;
            border-radius: 10px;
            background: #ffffff;
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            transition: all 0.3s ease;
            text-align: center;
            flex: 1 1 auto;
        }
        .filter-btn.active, .filter-btn:hover {
            background: var(--accent-orange);
            color: #ffffff;
            border-color: var(--accent-orange);
            box-shadow: 0 4px 15px rgba(255, 94, 0, 0.25);
        }
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-unread { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .status-read { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .status-replied { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .msg-box {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s;
        }
        .msg-box:hover {
            border-color: rgba(255, 94, 0, 0.4);
        }
        .msg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.8rem;
        }
        .msg-sender {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
        }
        .msg-email {
            font-size: 0.9rem;
            color: #2563eb;
            text-decoration: none;
        }
        .msg-body {
            background: #f8fafc;
            padding: 1.2rem;
            border-radius: 10px;
            color: #334155;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.2rem;
            border-left: 4px solid var(--accent-orange);
        }
        .action-btns {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        .btn-act {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: none;
            cursor: pointer;
        }
        .btn-reply { background: var(--accent-orange); color: #fff; }
        .btn-mark { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .btn-del { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
        .msg-email-icon { display: inline-block; white-space: nowrap; }
        @media (max-width: 768px) {
            .msg-header > div:first-child { display: flex; flex-direction: column; align-items: flex-start; gap: 0.2rem; }
            .msg-header-dot { display: none; }
            .action-btns .btn-act { flex: 1 1 100%; justify-content: center; }
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
                    <span style="color: var(--text-muted); font-weight:600; font-size:0.9rem;">Customer Communication</span>
                    <h1>Contact Messages</h1>
                </div>
            </div>
            <div class="header-user-badge">
                Logged in as: <span style="color: var(--primary-glow, #3b82f6); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
            </div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="msg-banner success-banner">
                ✨ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <a href="messages.php?status=all" class="filter-btn <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All (<?php echo $totalCount; ?>)</a>
            <a href="messages.php?status=unread" class="filter-btn <?php echo $statusFilter === 'unread' ? 'active' : ''; ?>">🔴 Unread (<?php echo $unreadCount; ?>)</a>
            <a href="messages.php?status=read" class="filter-btn <?php echo $statusFilter === 'read' ? 'active' : ''; ?>">🔵 Read (<?php echo $readCount; ?>)</a>
            <a href="messages.php?status=replied" class="filter-btn <?php echo $statusFilter === 'replied' ? 'active' : ''; ?>">🟢 Replied (<?php echo $repliedCount; ?>)</a>
        </div>

        <?php if (empty($messages)): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: #ffffff; border-radius: 16px; border: 1px dashed #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">💬</div>
                <h3 style="color: #0f172a;">No Contact Messages Found</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.4rem;">Customer inquiries submitted on the Contact Us page will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="msg-box">
                    <div class="msg-header">
                        <div>
                            <span class="msg-sender"><?php echo htmlspecialchars($msg['name']); ?></span>
                            <span class="msg-header-dot" style="margin: 0 0.5rem; color: #cbd5e1;">•</span>
                            <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>" class="msg-email">
                                <span class="msg-email-icon">✉️ </span><span style="word-break: break-all;"><?php echo htmlspecialchars($msg['email']); ?></span>
                            </a>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                🕒 <?php echo date('M d, Y - h:i A', strtotime($msg['created_at'])); ?>
                            </span>
                            <span class="status-pill status-<?php echo $msg['status']; ?>">
                                <?php echo $msg['status']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="msg-body">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    </div>

                    <div class="action-btns">
                        <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>?subject=RE: Contact Inquiry - Digi Pro X 24" class="btn-act btn-reply" onclick="window.location.href='messages.php?action=mark_replied&id=<?php echo $msg['id']; ?>';">
                            ✉️ Reply via Email
                        </a>
                        <?php if ($msg['status'] === 'unread'): ?>
                            <a href="messages.php?action=mark_read&id=<?php echo $msg['id']; ?>&status=<?php echo $statusFilter; ?>" class="btn-act btn-mark">
                                👁️ Mark as Read
                            </a>
                        <?php endif; ?>
                        <?php if ($msg['status'] !== 'replied'): ?>
                            <a href="messages.php?action=mark_replied&id=<?php echo $msg['id']; ?>&status=<?php echo $statusFilter; ?>" class="btn-act btn-mark" style="background: #dcfce7; color: #15803d; border: 1px solid #86efac;">
                                ✔️ Mark as Replied
                            </a>
                        <?php endif; ?>
                        <a href="messages.php?action=delete&id=<?php echo $msg['id']; ?>&status=<?php echo $statusFilter; ?>" class="btn-act btn-del" onclick="return confirm('Are you sure you want to delete this message?');">
                            🗑️ Delete
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
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

        if (menuToggle) {
            menuToggle.addEventListener('click', toggleMenu);
        }
        if (overlay) {
            overlay.addEventListener('click', toggleMenu);
        }
    </script>
</body>
</html>
