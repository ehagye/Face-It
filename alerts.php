<?php
session_start();

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error'] ?? null;

<<<<<<< HEAD
ob_start();
require 'config.php';
ob_end_clean();

$alerts = [
    [
        'id' => 1,
        'student_name' => 'Marcus Johnson',
        'class' => 'Capstone CTW 02',
        'timestamp' => '2024-04-15 09:07:00',
        'type' => 'low_confidence',
        'confidence' => 0.75,
        'status' => 'pending',
        'details' => 'Face detected with low confidence score'
    ],
    [
        'id' => 2,
        'student_name' => 'Unknown Face',
        'class' => 'Capstone CTW 02',
        'timestamp' => '2024-04-15 09:12:00',
        'type' => 'unknown_face',
        'confidence' => 0.45,
        'status' => 'pending',
        'details' => 'Unrecognized face in class session'
    ],
    [
        'id' => 3,
        'student_name' => 'Sofia Reyes',
        'class' => 'Data Science 101',
        'timestamp' => '2024-04-15 09:45:00',
        'type' => 'late_arrival',
        'confidence' => 0.92,
        'status' => 'pending',
        'details' => 'Student arrived 15 minutes after class start'
    ]
];
?>
=======
unset($_SESSION['success'], $_SESSION['error']);
?>

>>>>>>> parent of 7563cdb (Merge branch 'dashboardalerts')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Face-IT · Alerts</title>
    <link rel="stylesheet" href="styles.css">
<<<<<<< HEAD
    <style>
        .alerts-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-ignore-all {
            background: transparent;
            border: 1px solid #ff6b6b;
            color: #ff6b6b;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .btn-ignore-all:hover {
            background: rgba(255, 107, 107, 0.1);
        }

        .alerts-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .alert-item {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(14px);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border-left: 4px solid #ff6b6b;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-item:hover {
            background: rgba(255,255,255,0.12);
            transform: translateX(4px);
        }

        .alert-item.resolved {
            border-left-color: #4caf50;
            opacity: 0.7;
        }

        .alert-left {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            font-size: 1rem;
            margin: 0 0 4px 0;
            color: #f1f5f9;
        }

        .alert-meta {
            font-size: 0.85rem;
            color: #aaa;
            margin: 4px 0;
        }

        .alert-badge {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-right: 8px;
        }

        .alert-badge.resolved {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }

        .alert-confidence {
            font-size: 0.9rem;
            color: #aaa;
            margin-top: 4px;
        }

        .alert-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-dismiss {
            background: transparent;
            border: none;
            color: #ff6b6b;
            font-size: 1.4rem;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-dismiss:hover {
            color: #ff5252;
            transform: scale(1.2);
        }

        .arrow {
            color: #aaa;
            font-size: 0.9rem;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(135deg, #1a1f3c 0%, #0b0e1a 100%);
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.8);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header h2 {
            margin: 0 0 8px 0;
            font-size: 1.5rem;
            color: #f1f5f9;
        }

        .modal-subtext {
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        .modal-details {
            background: rgba(255,255,255,0.05);
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .modal-details p {
            margin: 8px 0;
            color: #cbd5e1;
        }

        .modal-details strong {
            color: #f1f5f9;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            color: #cbd5e1;
            margin-bottom: 6px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-group select {
            width: 100%;
            padding: 10px;
            background: rgba(51, 65, 85, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 6px;
            color: #f1f5f9;
            font-size: 0.95rem;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }

        .btn-confirm {
            flex: 1;
            padding: 10px;
            background: linear-gradient(90deg, #4caf50, #45a049);
            border: none;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-confirm:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-close {
            flex: 1;
            padding: 10px;
            background: transparent;
            border: 1px solid rgba(148, 163, 184, 0.4);
            color: #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-close:hover {
            background: rgba(148, 163, 184, 0.1);
            color: #f1f5f9;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #aaa;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin: 0;
        }
    </style>
=======
>>>>>>> parent of 7563cdb (Merge branch 'dashboardalerts')
</head>
<body class="dark-bg">

<header class="top-bar">
    <h1>Face-IT</h1>
    <nav class="nav-actions">
        <a href="main.php" class="nav-btn">Dashboard</a>
        <a href="manage_classes.php" class="nav-btn">Manage Classes</a>
        <a href="alerts.php" class="nav-btn">Alerts</a>
        <a href="settings.php" class="nav-btn">Settings</a>
        <a href="logout.php" class="nav-btn">Log Out</a>
    </nav>
</header>

<main class="dashboard">
    <section class="glass-card">
<<<<<<< HEAD
        <div class="alerts-header">
            <div>
                <h2>Alert Review</h2>
                <p style="color: #aaa; margin-top: 0;">Review and respond to detection alerts</p>
            </div>
            <button class="btn-ignore-all" onclick="ignoreAllAlerts()">Ignore All</button>
        </div>

        <div class="alerts-container">
            <?php if (empty($alerts)): ?>
                <div class="empty-state">
                    <p>✓ No pending alerts</p>
                </div>
            <?php else: ?>
                <?php foreach ($alerts as $alert): ?>
                    <div class="alert-item" id="alert-<?php echo $alert['id']; ?>">
                        <div class="alert-left" onclick="openAlert(<?php echo htmlspecialchars(json_encode($alert)); ?>)" style="cursor: pointer;">
                            <p class="alert-title">
                                <span class="alert-badge">Pending</span>
                                <?php echo htmlspecialchars($alert['student_name']); ?>
                            </p>
                            <p class="alert-meta"><?php echo htmlspecialchars($alert['class']); ?> · <?php echo date('g:i A', strtotime($alert['timestamp'])); ?></p>
                            <p class="alert-confidence">Confidence: <?php echo number_format($alert['confidence'] * 100, 0); ?>%</p>
                        </div>
                        <div class="alert-right">
                            <span class="arrow">→</span>
                            <button class="btn-dismiss" onclick="dismissAlert(<?php echo $alert['id']; ?>); event.stopPropagation();">×</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
=======
        <h2>Review Alerts</h2>

        <div class="log">
            <p class="warn">[9:07] Marcus Johnson — Low confidence (0.75)</p>
            <p class="warn">[9:12] Unknown face detected — Camera A</p>
>>>>>>> parent of 7563cdb (Merge branch 'dashboardalerts')
        </div>
    </section>
</main>

<<<<<<< HEAD
<!-- DETAIL MODAL -->
<div class="modal" id="alertModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Alert Details</h2>
            <p class="modal-subtext" id="modalSubtext"></p>
        </div>

        <div class="modal-details" id="modalDetails"></div>

        <form id="alertForm">
            <div class="form-group">
                <label for="statusSelect">Mark As:</label>
                <select id="statusSelect">
                    <option value="">-- Select Action --</option>
                    <option value="on_time">On Time</option>
                    <option value="late">Late</option>
                    <option value="absent">Absent</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-confirm" onclick="confirmAlert()">Confirm</button>
                <button type="button" class="btn-close" onclick="closeModal()">Ignore</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentAlert = null;

function openAlert(alert) {
    currentAlert = alert;
    
    document.getElementById('modalTitle').textContent = alert.student_name;
    document.getElementById('modalSubtext').textContent = alert.class + ' · ' + new Date(alert.timestamp).toLocaleString();
    
    let detailsHTML = `
        <p><strong>Type:</strong> ${alert.type.replace(/_/g, ' ').toUpperCase()}</p>
        <p><strong>Timestamp:</strong> ${new Date(alert.timestamp).toLocaleString()}</p>
        <p><strong>Confidence:</strong> ${(alert.confidence * 100).toFixed(0)}%</p>
        <p><strong>Details:</strong> ${alert.details}</p>
    `;
    document.getElementById('modalDetails').innerHTML = detailsHTML;
    document.getElementById('statusSelect').value = '';
    
    document.getElementById('alertModal').classList.add('active');
}

function closeModal() {
    document.getElementById('alertModal').classList.remove('active');
    currentAlert = null;
}

function confirmAlert() {
    const status = document.getElementById('statusSelect').value;
    
    if (!status) {
        alert('Please select an action');
        return;
    }
    
    if (!currentAlert) return;
    
    console.log('Alert ID:', currentAlert.id, 'Status:', status);
    
    // TODO: Send to server
    // fetch('update_alert.php', {
    //     method: 'POST',
    //     headers: { 'Content-Type': 'application/json' },
    //     body: JSON.stringify({ alert_id: currentAlert.id, status: status })
    // });
    
    dismissAlert(currentAlert.id);
    closeModal();
}

function dismissAlert(alertId) {
    const alertEl = document.getElementById('alert-' + alertId);
    if (alertEl) {
        alertEl.style.opacity = '0';
        alertEl.style.transform = 'translateX(-20px)';
        setTimeout(() => alertEl.remove(), 300);
    }
}

function ignoreAllAlerts() {
    if (confirm('Ignore all pending alerts?')) {
        const alerts = document.querySelectorAll('.alert-item');
        alerts.forEach((alert, i) => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(-20px)';
                setTimeout(() => alert.remove(), 300);
            }, i * 50);
        });
    }
}

document.getElementById('alertModal').addEventListener('click', (e) => {
    if (e.target.id === 'alertModal') closeModal();
});
</script>

=======
>>>>>>> parent of 7563cdb (Merge branch 'dashboardalerts')
</body>
</html>