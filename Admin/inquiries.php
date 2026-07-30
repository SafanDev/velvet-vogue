<?php
// admin/inquiries.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

// =======================================================
// HANDLE AJAX REQUESTS (Fetch Ticket & Send Reply)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');

    $action = (string) ($_POST['action'] ?? '');
    $inquiryID = (int) ($_POST['inquiryID'] ?? 0);

    if ($inquiryID < 1 || !in_array($action, ['get_ticket', 'send_reply'], true)) {
        vv_json_response(['status' => 'error', 'message' => 'Invalid support request.'], 422);
    }

    vv_enforce_rate_limit('admin-support', 120, 60, (string) ($_SESSION['userID'] ?? 'admin'));

    if ($action === 'get_ticket') {
        try {
            $stmt = $pdo->prepare("
                SELECT i.*, u.phoneNo
                FROM inquiry i
                LEFT JOIN `user` u ON i.userID = u.userID
                WHERE i.inquiryID = ?
                LIMIT 1
            ");
            $stmt->execute([$inquiryID]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                vv_json_response(['status' => 'error', 'message' => 'Ticket not found.'], 404);
            }

            vv_json_response(['status' => 'success', 'data' => $ticket]);
        } catch (PDOException $exception) {
            error_log('Support ticket load failed: ' . $exception->getMessage());
            vv_json_response(['status' => 'error', 'message' => 'The ticket could not be loaded.'], 500);
        }
    }

    $replyText = trim((string) ($_POST['replyText'] ?? ''));
    $newStatus = (string) ($_POST['newStatus'] ?? '');
    $validStatuses = ['open', 'in_progress', 'resolved', 'closed'];

    if ($replyText === '' || mb_strlen($replyText) > 5000 || !in_array($newStatus, $validStatuses, true)) {
        vv_json_response(['status' => 'error', 'message' => 'Enter a reply and choose a valid status.'], 422);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE inquiry
            SET reply = ?, inquiryStatus = ?, repliedAt = CURRENT_TIMESTAMP
            WHERE inquiryID = ?
        ");
        $stmt->execute([$replyText, $newStatus, $inquiryID]);

        if ($stmt->rowCount() !== 1) {
            vv_json_response(['status' => 'error', 'message' => 'Ticket not found.'], 404);
        }

        vv_json_response(['status' => 'success', 'message' => 'Reply saved successfully.']);
    } catch (PDOException $exception) {
        error_log('Support reply failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The reply could not be saved.'], 500);
    }
}

// =======================================================
// FETCH ALL TICKETS FOR INBOX
// =======================================================
$query = "
    SELECT inquiryID, senderName, senderEmail, subject, inquiryStatus, createdAt
    FROM inquiry
    ORDER BY
        CASE inquiryStatus
            WHEN 'open' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'resolved' THEN 3
            WHEN 'closed' THEN 4
        END,
        createdAt DESC
";
$tickets = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Base Metrics
$totalTickets = count($tickets);
$openCount = 0;
$progressCount = 0;
$resolvedCount = 0;

foreach ($tickets as $t) {
    if ($t['inquiryStatus'] === 'open') $openCount++;
    if ($t['inquiryStatus'] === 'in_progress') $progressCount++;
    if ($t['inquiryStatus'] === 'resolved' || $t['inquiryStatus'] === 'closed') $resolvedCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= vv_e(vv_versioned_asset('../favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= vv_e(vv_versioned_asset('../Assets/images/brand/velvet-vogue-mark.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= vv_e(vv_versioned_asset('../Assets/images/brand/velvet-vogue-favicon-32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= vv_e(vv_versioned_asset('../Assets/images/brand/velvet-vogue-apple-touch.png')) ?>">
    <meta name="theme-color" content="#050505">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <title>Support Desk | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/inquiries.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1800px;">

        <div class="d-flex justify-content-between align-items-end mb-4 pb-3 border-bottom-dark scroll-reveal visible">
            <div>
                <div class="d-flex align-items-center gap-3 mb-1">
                    <span class="simple-label text-gold m-0">Customer Service</span>
                    <span class="badge-count text-white" id="totalBadge"><?= $totalTickets ?> Tickets</span>
                </div>
                <h1 class="massive-title text-white m-0">Support Desk</h1>
            </div>
            <div class="tactical-search">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="ticketSearch" class="search-input" placeholder="Search subject or sender..." autocomplete="off">
            </div>
        </div>

        <div class="row g-4 mb-4 scroll-reveal visible" id="metricsContainer">
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-regular fa-folder metric-icon"></i>
                    <div class="metric-info">
                        <span class="metric-label">Total Tickets</span>
                        <span class="metric-value text-white" id="countTotal"><?= $totalTickets ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-circle-exclamation metric-icon"></i>
                    <div class="metric-info">
                        <span class="metric-label">Action Required</span>
                        <span class="metric-value text-white" id="countOpen"><?= $openCount ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-spinner metric-icon"></i>
                    <div class="metric-info">
                        <span class="metric-label">In Progress</span>
                        <span class="metric-value text-white" id="countProgress"><?= $progressCount ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-check-double metric-icon"></i>
                    <div class="metric-info">
                        <span class="metric-label">Resolved / Closed</span>
                        <span class="metric-value text-white" id="countResolved"><?= $resolvedCount ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 scroll-reveal visible" style="height: 68vh; min-height: 650px;">

            <div class="col-xl-4 col-lg-5 h-100">
                <div class="matrix-pane h-100 d-flex flex-column">
                    <div class="pane-header">
                        <h4 class="pane-title m-0">Inbox</h4>
                    </div>

                    <div class="inbox-list flex-grow-1 custom-scrollbar" id="inboxList">
                        <?php if(empty($tickets)): ?>
                            <div class="text-center py-5 text-silver font-body" style="font-size: 0.9rem;">No tickets in queue.</div>
                        <?php else: ?>
                            <?php foreach($tickets as $t):
                                // Visual dot color for inbox scannability
                                $statusColor = '';
                                if($t['inquiryStatus'] === 'open') $statusColor = 'status-red';
                                if($t['inquiryStatus'] === 'in_progress') $statusColor = 'status-gold';
                                if($t['inquiryStatus'] === 'resolved') $statusColor = 'status-green';
                                if($t['inquiryStatus'] === 'closed') $statusColor = 'status-grey';
                            ?>
                                <div class="inbox-item" data-id="<?= (int) $t['inquiryID'] ?>" data-status="<?= vv_e($t['inquiryStatus']) ?>" data-search="<?= vv_e(strtolower($t['subject'] . ' ' . $t['senderName'] . ' ' . $t['senderEmail'])) ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-dot <?= $statusColor ?>"></span>
                                            <span class="sender-name text-white font-heading fw-bold" style="font-size: 0.9rem;"><?= vv_e($t['senderName']) ?></span>
                                        </div>
                                        <span class="time-stamp text-silver font-monospace" style="font-size: 0.7rem;"><?= date('M d', strtotime($t['createdAt'])) ?></span>
                                    </div>
                                    <div class="subject-line text-silver font-body fw-bold mb-1 truncate-text" style="font-size: 0.85rem;"><?= vv_e($t['subject']) ?></div>
                                    <div class="email-line font-monospace truncate-text" style="font-size: 0.75rem; color: #777;"><?= vv_e($t['senderEmail']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-lg-7 h-100">
                <div class="matrix-pane h-100 d-flex flex-column position-relative" id="readingConsole">

                    <div class="console-empty-state d-flex flex-column align-items-center justify-content-center h-100 w-100 position-absolute z-3" id="emptyState" style="background: #050505;">
                        <i class="fa-regular fa-envelope-open mb-3" style="font-size: 3rem; color: #222;"></i>
                        <h4 class="text-silver font-heading text-uppercase tracking-widest" style="font-size: 0.85rem;">Select a ticket to view</h4>
                    </div>

                    <div id="activeTicketView" class="d-none h-100 flex-column">

                        <div class="pane-header d-flex justify-content-between align-items-start pb-4">
                            <div>
                                <h3 class="text-white font-heading fw-bold mb-3" id="c_subject" style="font-size: 1.3rem;">Subject</h3>
                                <div class="d-flex gap-4 font-monospace text-silver" style="font-size: 0.8rem;">
                                    <span><i class="fa-solid fa-user me-2 text-gold"></i> <span id="c_name">Name</span></span>
                                    <span><i class="fa-regular fa-envelope me-2 text-gold"></i> <span id="c_email">Email</span></span>
                                    <span id="c_phone_wrapper" class="d-none"><i class="fa-solid fa-phone me-2 text-gold"></i> <span id="c_phone">Phone</span></span>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-end justify-content-center">
                                <span class="ticket-badge mb-2" id="c_ticketID">Ticket #000</span>
                                <span class="d-block text-silver font-monospace" style="font-size: 0.75rem;">Received: <span id="c_date">Date</span></span>
                            </div>
                        </div>

                        <div class="console-body flex-grow-1 custom-scrollbar p-4" style="background: #020202;">

                            <div class="chat-bubble client-bubble mb-4">
                                <div class="bubble-header d-flex justify-content-between">
                                    <span class="text-silver fw-bold text-uppercase font-heading" style="font-size: 0.75rem; letter-spacing: 2px;">Customer Message</span>
                                </div>
                                <div class="bubble-content font-body text-white" id="c_message" style="line-height: 1.8; font-size: 0.95rem;">
                                    Message goes here...
                                </div>
                            </div>

                            <div id="adminReplyContainer" class="d-none">
                                <div class="chat-bubble admin-bubble ml-auto">
                                    <div class="bubble-header d-flex justify-content-between">
                                        <span class="text-gold fw-bold text-uppercase font-heading" style="font-size: 0.75rem; letter-spacing: 2px;">Admin Reply</span>
                                        <span class="text-silver font-monospace" style="font-size: 0.7rem;" id="c_replyDate">Date</span>
                                    </div>
                                    <div class="bubble-content font-body text-white" id="c_reply" style="line-height: 1.8; font-size: 0.95rem;">
                                        Reply goes here...
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="pane-footer p-4 border-top-dark" style="background: #080808;">
                            <form id="replyForm" method="post">
                                <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                                <input type="hidden" id="f_inquiryID" name="inquiryID">
                                <input type="hidden" name="action" value="send_reply">

                                <div class="form-floating-custom mb-3">
                                    <textarea name="replyText" id="f_replyText" class="luxury-input" style="height: 90px; resize: none;" placeholder=" " required></textarea>
                                    <label for="f_replyText">Type your response here...</label>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="text-silver font-body fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">Status:</span>
                                        <div class="elegant-select-wrapper" style="width: 140px;">
                                            <select name="newStatus" id="f_status" class="elegant-select text-white w-100">
                                                <option value="open">Open</option>
                                                <option value="in_progress">In Progress</option>
                                                <option value="resolved">Resolved</option>
                                                <option value="closed">Closed</option>
                                            </select>
                                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-core-solid" id="submitReplyBtn">
                                        Send Reply <i class="fa-regular fa-paper-plane ms-2"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </main>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
        <div id="actionToast" class="toast align-items-center text-white bg-dark border border-secondary" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body font-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/inquiries.js')) ?>"></script>
</body>
</html>