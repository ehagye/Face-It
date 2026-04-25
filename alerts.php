<?php
session_start();

if (empty($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$supabase_url = 'https://evoqwkezqahsvctmopld.supabase.co';
$supabase_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImV2b3F3a2V6cWFoc3ZjdG1vcGxkIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzA4NDEyNzUsImV4cCI6MjA4NjQxNzI3NX0.2lxmqC6l7GxAMLQxxZ1qSLfniPuKWk4b2WsQSGO1v3o';
 
function supabase_get($url, $key) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$alerts = supabase_get("$supabase_url/rest/v1/alerts?select=*&order=detected_at.desc", $supabase_key);
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face-IT · Alerts</title>
    <link rel="stylesheet" href="styles.css">
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
 
        .alert-left { flex: 1; }
 
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
 
        .arrow { color: #aaa; font-size: 0.9rem; }
 
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
 
        .modal.active { display: flex; }
 
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
 
        .modal-details strong { color: #f1f5f9; }
 
        .form-group { margin-bottom: 16px; }
 
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
        }
 
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #aaa;
        }
    </style>
</head>
<body class="dark-bg">
 
<header class="top-bar">
    <h1>Face-IT</h1>
    <nav class="nav-actions">
        <a href="main.php" class="nav-btn">Dashboard</a>
        <a href="manage_classes.php" class="nav-btn">Manage Classes</a>
        <a href="Attendance_Report/report_filters.php" class="nav-btn">Attendance Reports</a>
        <a href="alerts.php" class="nav-btn">Alerts</a>
        <a href="settings.php" class="nav-btn">Settings</a>
        <a href="logout.php" class="nav-btn">Log Out</a>
    </nav>
</header>
 
<main class="dashboard">
    <section class="glass-card">
        <div class="alerts-header">
            <div>
                <h2>Alert Review</h2>
                <p style="color: #aaa; margin-top: 0;">Students flagged with low confidence scores (under 60%)</p>
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
                    <div class="alert-item" id="alert-<?= $alert['id'] ?>">
                        <div class="alert-left" onclick="openAlert(<?= htmlspecialchars(json_encode($alert)) ?>)">
                            <p class="alert-title">
                                <span class="alert-badge">Low Confidence</span>
                                <?= htmlspecialchars($alert['student_name']) ?>
                            </p>
                            <p class="alert-meta">
                                Detected: <?= date('M j, Y g:i A', strtotime($alert['detected_at'])) ?>
                            </p>
                            <p class="alert-confidence">
                                Confidence: <?= number_format($alert['confidence_score'] * 100, 0) ?>%
                            </p>
                        </div>
                        <div class="alert-right">
                            <span class="arrow">→</span>
                            <button class="btn-dismiss" onclick="dismissAlert(<?= $alert['id'] ?>); event.stopPropagation();">×</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
 
<!-- DETAIL MODAL -->
<div class="modal" id="alertModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Alert Details</h2>
            <p class="modal-subtext" id="modalSubtext"></p>
        </div>
        <div class="modal-details" id="modalDetails"></div>
        <div class="modal-actions">
            <button type="button" class="btn-close" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>
 
<script>
let currentAlert = null;
 
function openAlert(alert) {
    currentAlert = alert;
    document.getElementById('modalTitle').textContent = alert.student_name;
    document.getElementById('modalSubtext').textContent = 'Detected: ' + new Date(alert.detected_at).toLocaleString();
    document.getElementById('modalDetails').innerHTML = `
        <p><strong>Student ID:</strong> ${alert.student_id}</p>
        <p><strong>Student Name:</strong> ${alert.student_name}</p>
        <p><strong>Detected At:</strong> ${new Date(alert.detected_at).toLocaleString()}</p>
        <p><strong>Confidence Score:</strong> ${(alert.confidence_score * 100).toFixed(0)}%</p>
    `;
    document.getElementById('alertModal').classList.add('active');
}
 
function closeModal() {
    document.getElementById('alertModal').classList.remove('active');
    currentAlert = null;
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
 
</body>
</html>
 