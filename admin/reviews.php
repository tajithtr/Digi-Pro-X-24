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

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Review Actions
        if ($action === 'approve_review' && isset($_POST['review_id'])) {
            $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?");
            $stmt->execute([(int)$_POST['review_id']]);
        } elseif ($action === 'delete_review' && isset($_POST['review_id'])) {
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([(int)$_POST['review_id']]);
        } elseif ($action === 'reply_review' && isset($_POST['review_id']) && isset($_POST['reply_text'])) {
            $stmt = $pdo->prepare("UPDATE reviews SET admin_reply = ?, is_approved = 1 WHERE id = ?");
            $stmt->execute([trim($_POST['reply_text']), (int)$_POST['review_id']]);
        }
        
        // Question Actions
        elseif ($action === 'approve_question' && isset($_POST['question_id'])) {
            $stmt = $pdo->prepare("UPDATE product_questions SET is_approved = 1 WHERE id = ?");
            $stmt->execute([(int)$_POST['question_id']]);
        } elseif ($action === 'delete_question' && isset($_POST['question_id'])) {
            $stmt = $pdo->prepare("DELETE FROM product_questions WHERE id = ?");
            $stmt->execute([(int)$_POST['question_id']]);
        } elseif ($action === 'reply_question' && isset($_POST['question_id']) && isset($_POST['reply_text'])) {
            $stmt = $pdo->prepare("UPDATE product_questions SET admin_reply = ?, is_approved = 1 WHERE id = ?");
            $stmt->execute([trim($_POST['reply_text']), (int)$_POST['question_id']]);
        }
        
        $view = $_POST['current_view'] ?? 'reviews';
        header("Location: reviews.php?view=" . urlencode($view));
        exit;
    }
}

$view = $_GET['view'] ?? 'reviews';

// Fetch all reviews
$stmtR = $pdo->query("SELECT r.*, p.name as product_name FROM reviews r LEFT JOIN products p ON r.product_id = p.id ORDER BY r.created_at DESC");
$reviews = $stmtR->fetchAll(PDO::FETCH_ASSOC);

// Fetch all questions
$stmtQ = $pdo->query("SELECT q.*, p.name as product_name FROM product_questions q LEFT JOIN products p ON q.product_id = p.id ORDER BY q.created_at DESC");
$questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q & A and Reviews - Digi Pro X 24 Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;
        }
        .modal-box {
            background: #fff; width: 100%; max-width: 500px; border-radius: 16px; padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); transform: scale(0.95); transition: all 0.3s ease;
        }
        .modal-overlay.active { display: flex; }
        .modal-overlay.active .modal-box { transform: scale(1); }
        .modal-header { font-size: 1.25rem; font-weight: 800; color: #1e293b; margin-bottom: 1.5rem; }
        .reply-textarea {
            width: 100%; height: 120px; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 12px;
            font-family: inherit; font-size: 0.95rem; margin-bottom: 1.5rem; resize: none; outline: none;
        }
        .reply-textarea:focus { border-color: #3b82f6; }
        .btn-group { display: flex; justify-content: flex-end; gap: 1rem; }
        
        .view-toggle {
            display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; background: #fff; padding: 0.8rem; border-radius: 12px; border: 1px solid var(--border-light);
        }
        .view-btn {
            padding: 0.6rem 1.4rem; border-radius: 8px; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; border: none; background: transparent; color: var(--text-muted);
        }
        .view-btn.active { background: #eff6ff; color: #3b82f6; }
        .reply-badge { display: inline-block; background: #dbeafe; color: #2563eb; font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; font-weight: 700; margin-top: 0.5rem; }
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
            <li><a href="index.php">📊 Admin Dashboard</a></li>
            <li><a href="categories.php">📁 Categories</a></li>
            <li><a href="products.php">🛍️ Products</a></li>
            <li><a href="services.php">🛠️ Services</a></li>
            <li><a href="reviews.php" class="active">⭐ Q & A Reviews <?php if(isset($sidebar_rev) && $sidebar_rev > 0): ?><span style="background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem; margin-left:5px;"><?php echo $sidebar_rev; ?></span><?php endif; ?></a></li>
            <li><a href="orders.php">📦 Orders</a></li>
            <li><a href="service_requests.php">📋 Service Requests <?php if(isset($sidebar_sr) && $sidebar_sr > 0): ?><span style="background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem; margin-left:5px;"><?php echo $sidebar_sr; ?></span><?php endif; ?></a></li>
            <li><a href="messages.php">💬 Messages <?php if(isset($sidebar_msg) && $sidebar_msg > 0): ?><span style="background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem; margin-left:5px;"><?php echo $sidebar_msg; ?></span><?php endif; ?></a></li>
            <li><a href="users.php">👥 Users</a></li>
            <li><a href="change_password.php">🔒 Change Password</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="sidebar-toggle" id="menu-toggle">☰</button>
                <div>
                    <span style="color: var(--text-muted); font-weight:600; font-size:0.9rem;">Management</span>
                    <h1>Questions & Reviews</h1>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="header-user-badge">
                    Logged in as: <span style="color: var(--primary-glow, #3b82f6); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
                </div>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="view-toggle">
                <button class="view-btn <?php echo $view === 'reviews' ? 'active' : ''; ?>" onclick="window.location.href='reviews.php?view=reviews'">⭐ Product Reviews (<?php echo count($reviews); ?>)</button>
                <button class="view-btn <?php echo $view === 'questions' ? 'active' : ''; ?>" onclick="window.location.href='reviews.php?view=questions'">❓ Product Questions (<?php echo count($questions); ?>)</button>
            </div>

            <!-- REVIEWS TABLE -->
            <div class="admin-table-wrapper" style="<?php echo $view === 'reviews' ? '' : 'display:none;'; ?>">
                <table class="admin-table responsive-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Reviewer</th>
                            <th>Rating</th>
                            <th>Review Text</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reviews)): ?>
                            <tr><td colspan="7" style="text-align: center;">No reviews found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reviews as $rev): ?>
                                <tr>
                                    <td data-label="Date"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></td>
                                    <td data-label="Product"><?php echo htmlspecialchars($rev['product_name']); ?></td>
                                    <td data-label="Reviewer"><?php echo htmlspecialchars($rev['reviewer_name']); ?></td>
                                    <td data-label="Rating" style="color: #d97706;"><?php echo str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']); ?></td>
                                    <td data-label="Review Text">
                                        <div style="max-width: 300px; font-size: 0.9rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                            <?php echo htmlspecialchars($rev['review_text']); ?>
                                            <?php if(!empty($rev['admin_reply'])): ?>
                                                <div class="reply-badge" style="display: inline-block;">Replied ✓</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Status">
                                        <?php if ($rev['is_approved']): ?>
                                            <span class="badge approved">Approved</span>
                                        <?php else: ?>
                                            <span class="badge pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class="td-actions action-forms" style="display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap;">
                                        <?php if (!$rev['is_approved']): ?>
                                            <form method="POST"><input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>"><input type="hidden" name="action" value="approve_review"><input type="hidden" name="current_view" value="reviews"><button type="submit" class="btn-small btn-approve">✔️ Approve</button></form>
                                        <?php endif; ?>
                                        <button class="btn-small" onclick="openReplyModal('review', <?php echo $rev['id']; ?>, `<?php echo htmlspecialchars(addslashes($rev['admin_reply'] ?? '')); ?>`)">
                                            💬 <?php echo !empty($rev['admin_reply']) ? 'View/Edit Reply' : 'Reply'; ?>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Delete this review?');"><input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>"><input type="hidden" name="action" value="delete_review"><input type="hidden" name="current_view" value="reviews"><button type="submit" class="btn-small btn-delete">🗑️ Delete</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- QUESTIONS TABLE -->
            <div class="admin-table-wrapper" style="<?php echo $view === 'questions' ? '' : 'display:none;'; ?>">
                <table class="admin-table responsive-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Question</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($questions)): ?>
                            <tr><td colspan="6" style="text-align: center;">No questions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($questions as $q): ?>
                                <tr>
                                    <td data-label="Date"><?php echo date('M d, Y', strtotime($q['created_at'])); ?></td>
                                    <td data-label="Product"><?php echo htmlspecialchars($q['product_name']); ?></td>
                                    <td data-label="Customer"><?php echo htmlspecialchars($q['customer_name']); ?></td>
                                    <td data-label="Question">
                                        <div style="max-width: 400px; font-size: 0.9rem; font-weight:600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                            <?php echo htmlspecialchars($q['question_text']); ?>
                                            <?php if(!empty($q['admin_reply'])): ?>
                                                <div class="reply-badge" style="display: inline-block;">Replied ✓</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Status">
                                        <?php if ($q['is_approved']): ?>
                                            <span class="badge approved">Approved</span>
                                        <?php else: ?>
                                            <span class="badge pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class="td-actions action-forms" style="display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap;">
                                        <?php if (!$q['is_approved']): ?>
                                            <form method="POST"><input type="hidden" name="question_id" value="<?php echo $q['id']; ?>"><input type="hidden" name="action" value="approve_question"><input type="hidden" name="current_view" value="questions"><button type="submit" class="btn-small btn-approve">✔️ Approve</button></form>
                                        <?php endif; ?>
                                        <button class="btn-small" onclick="openReplyModal('question', <?php echo $q['id']; ?>, `<?php echo htmlspecialchars(addslashes($q['admin_reply'] ?? '')); ?>`)">
                                            💬 <?php echo !empty($q['admin_reply']) ? 'View/Edit Reply' : 'Reply'; ?>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Delete this question?');"><input type="hidden" name="question_id" value="<?php echo $q['id']; ?>"><input type="hidden" name="action" value="delete_question"><input type="hidden" name="current_view" value="questions"><button type="submit" class="btn-small btn-delete">🗑️ Delete</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Reply Modal -->
    <div class="modal-overlay" id="reply-modal">
        <div class="modal-box">
            <div class="modal-header">Respond to Customer</div>
            <form method="POST">
                <input type="hidden" name="action" id="modal-action-input">
                <input type="hidden" name="review_id" id="modal-review-id-input">
                <input type="hidden" name="question_id" id="modal-question-id-input">
                <input type="hidden" name="current_view" value="<?php echo htmlspecialchars($view); ?>">
                
                <textarea class="reply-textarea" name="reply_text" id="modal-textarea" placeholder="Type your response here... (This will be visible on the product page)" required></textarea>
                
                <div class="btn-group">
                    <button type="button" class="btn-secondary" style="padding: 0.8rem 1.5rem; border-radius: 8px; border: none; background: #e2e8f0; cursor: pointer; font-weight: 600;" onclick="closeReplyModal()">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding: 0.8rem 1.5rem; border-radius: 8px; border: none; cursor: pointer;">Save & Publish Reply</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');

        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        function openReplyModal(type, id, existingReply) {
            document.getElementById('reply-modal').classList.add('active');
            
            const actionInput = document.getElementById('modal-action-input');
            const reviewIdInput = document.getElementById('modal-review-id-input');
            const questionIdInput = document.getElementById('modal-question-id-input');
            const textarea = document.getElementById('modal-textarea');
            
            textarea.value = existingReply || '';
            
            if (type === 'review') {
                actionInput.value = 'reply_review';
                reviewIdInput.value = id;
                questionIdInput.value = '';
            } else {
                actionInput.value = 'reply_question';
                questionIdInput.value = id;
                reviewIdInput.value = '';
            }
        }

        function closeReplyModal() {
            document.getElementById('reply-modal').classList.remove('active');
        }
    </script>
</body>
</html>