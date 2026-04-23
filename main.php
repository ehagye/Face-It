<?php
session_start();

if (empty($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$professor_email = $_SESSION['user']['email'];

ob_start();
require 'config.php';
ob_end_clean();

function get_professor_id($email, $config) {
    $url = $config['SUPABASE_URL'] . "/rest/v1/professors?select=professor_id,first_name,last_name,email";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['SUPABASE_KEY']}",
            "apikey: {$config['SUPABASE_KEY']}"
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $professors = json_decode($response, true) ?? [];
        foreach ($professors as $prof) {
            if (isset($prof['email']) && strtolower($prof['email']) === strtolower($email)) {
                return $prof;
            }
        }
    }
    return null;
}

function fetch_professor_classes($professor_id, $config) {
    $url = $config['SUPABASE_URL'] . "/rest/v1/classes?professor_id=eq." . intval($professor_id) . "&select=class_id,class_name,scheduled_start_time";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['SUPABASE_KEY']}",
            "apikey: {$config['SUPABASE_KEY']}"
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true) ?? [];
    }
    return [];
}

$professor = get_professor_id($professor_email, $config);

if (!$professor) {
    die("Error: Professor account not found for email: " . htmlspecialchars($professor_email));
}

$professor_id = $professor['professor_id'];
$professor_name = $professor['first_name'] . ' ' . $professor['last_name'];
$classes = fetch_professor_classes($professor_id, $config);

// Fun welcome messages
$welcome_messages = [
    "Ready to mark attendance?",
    "Let's make attendance smart!",
    "Recognize faces, recognize progress!",
    "Bringing AI to the classroom!",
    "Attendance just got smarter!",
    "Let's mark attendance with a smile! 😊"
];
$welcome_msg = $welcome_messages[array_rand($welcome_messages)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face-IT Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dark-bg">

<!-- TOP NAV BAR -->
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

<!-- WELCOME MESSAGE -->
<section class="welcome-section">
    <div class="welcome-content">
        <h2><?php echo htmlspecialchars($welcome_msg); ?></h2>
        <p>Welcome back, <span class="professor-name"><?php echo htmlspecialchars($professor_name); ?></span>! </p>
    </div>
</section>

<!-- MAIN DASHBOARD -->
<main class="dashboard">
    <section class="dashboard-grid">
        
        <!-- LEFT COLUMN -->
        <div class="column">
            
            <!-- CLASS SELECTION -->
            <div class="glass-card">
                <h3>Class Selection</h3>
                <select id="classSelect" onchange="handleClassChange()">
                    <option value="">-- Select a class --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($classes)): ?>
                    <p style="color: #ef4444; padding: 1rem; text-align: center; margin-top: 1rem;">No classes assigned</p>
                <?php endif; ?>
            </div>

            <!-- CLASS ROSTER -->
            <div class="glass-card">
                <h3>Class Roster</h3>
                <div class="roster" id="rosterContainer">
                    <p style="color: #94a3b8; padding: 1rem; text-align: center;">Select a class above</p>
                </div>
            </div>

            <!-- ACTIVITY LOG -->
            <div class="glass-card">
                <h3>Activity Log</h3>
                <div class="log" id="activityLog">
                    <p style="color: #94a3b8;">No activity yet</p>
                </div>
            </div>
            
        </div>

        <!-- RIGHT COLUMN -->
        <div class="column">
            
            <!-- LIVE CAMERA FEED -->
            <div class="glass-card">
                <div class="card-header">
                    <h3>Live Camera Feed</h3>
                    <button id="camToggle" class="manage-btn" onclick="toggleAttendance()" disabled>
                        Start Attendance
                    </button>
                </div>
                <div class="camera-feed-wrapper">
                    <video id="dashCam" autoplay playsinline muted></video>
                    <p id="camStatus" class="subtitle">Camera off</p>
                </div>

                <!-- DETECTION STATS -->
                <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(51, 65, 85, 0.5); border-radius: 0.5rem; font-size: 0.9rem;">
                    <p style="margin: 0.25rem 0;"><strong>Server:</strong> <span id="serverStatus" style="color: #ef4444;">● Disconnected</span></p>
                    <p style="margin: 0.25rem 0;"><strong>FPS:</strong> <span id="fpsCounter">0</span></p>
                    <p style="margin: 0.25rem 0;"><strong>Latest Match:</strong> <span id="latestMatch" style="color: #10b981;">None</span></p>
                </div>
            </div>

            <!-- SYSTEM STATUS -->
            <div class="glass-card status">
                <h3>System Status</h3>
                <p id="wsIndicator">● Detection Server: <span style="color: #ef4444;">Disconnected</span></p>
                <p id="camIndicator" style="margin-top: 0.5rem;">● Attendance: <span style="color: #ef4444;">Inactive</span></p>
            </div>
            
        </div>
        
    </section>
</main>

<script>
const CONFIG = {
    wsUrl: "ws://localhost:8765",
    currentClass: null,
    currentStudents: [],
    attendanceRecord: new Map(),
    attendanceRunning: false
};

let ws = null;
let dashStream = null;

function connectWebSocket() {
    if (ws && ws.readyState === WebSocket.OPEN) return;

    try {
        console.log('[WS] Connecting to ' + CONFIG.wsUrl);
        ws = new WebSocket(CONFIG.wsUrl);

        ws.onopen = () => {
            console.log('[WS] ✓ Connected');
            updateServerStatus(true);
            addActivityLog('✓ Server connected');
        };

        ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                handleDetectionEvent(data);
            } catch (e) {
                console.error('Parse error:', e);
            }
        };

        ws.onerror = (error) => {
            console.error('[WS] Error:', error);
            updateServerStatus(false);
        };

        ws.onclose = () => {
            console.log('[WS] Disconnected');
            updateServerStatus(false);
            setTimeout(connectWebSocket, 3000);
        };

    } catch (e) {
        console.error('Connection error:', e);
        updateServerStatus(false);
    }
}

function handleDetectionEvent(event) {
    switch (event.type) {
        case 'face_detected':
            if (event.logged) {
                markStudentAttendance(event.student_id, event.first_name, event.last_name, event.confidence, event.status);
                document.getElementById('latestMatch').textContent = `${event.first_name} ${event.last_name}`;
                addActivityLog(`✓ ${event.first_name} ${event.last_name} - ${event.status} (${(event.confidence*100).toFixed(0)}%)`);
            }
            break;

        case 'frame_update':
            document.getElementById('fpsCounter').textContent = event.fps.toFixed(1);
            break;

        case 'error':
            addActivityLog('✗ Server error: ' + event.message);
            break;
    }
}

function markStudentAttendance(studentId, firstName, lastName, confidence, status) {
    CONFIG.attendanceRecord.set(studentId, { status, time: new Date().toLocaleTimeString(), confidence });

    const rows = document.querySelectorAll('.roster .row');
    rows.forEach(row => {
        if (row.dataset.studentId == studentId) {
            row.style.background = 'rgba(16, 185, 129, 0.1)';
            row.style.borderLeft = '4px solid #10b981';
            const select = row.querySelector('select');
            if (select) select.value = status;
        }
    });
}

function updateServerStatus(connected) {
    const serverStatus = document.getElementById('serverStatus');
    const wsIndicator = document.getElementById('wsIndicator');
    
    if (connected) {
        serverStatus.innerHTML = '● <span style="color: #10b981;">Connected</span>';
        wsIndicator.innerHTML = '● Detection Server: <span style="color: #10b981;">Connected</span>';
    } else {
        serverStatus.innerHTML = '● <span style="color: #ef4444;">Disconnected</span>';
        wsIndicator.innerHTML = '● Detection Server: <span style="color: #ef4444;">Disconnected</span>';
    }
}

function addActivityLog(message) {
    const log = document.getElementById('activityLog');
    const entries = log.querySelectorAll('p');
    if (entries.length > 15) entries[0].remove();

    const p = document.createElement('p');
    p.textContent = '[' + new Date().toLocaleTimeString() + '] ' + message;
    if (message.includes('✓')) p.style.color = '#10b981';
    else if (message.includes('✗')) p.style.color = '#ef4444';
    log.appendChild(p);
    log.scrollTop = log.scrollHeight;
}

async function handleClassChange() {
    const select = document.getElementById('classSelect');
    const classId = select.value;
    if (!classId) { /* ...existing reset logic... */ return; }

    CONFIG.currentClass = classId;
    addActivityLog('Starting detection server...');

    // 1. Spin up the Python server for this class
    try {
        const res = await fetch(`start_server.php?class_id=${classId}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        addActivityLog('✓ Server started for class');
    } catch (e) {
        addActivityLog('✗ Could not start server: ' + e.message);
    }

    // 2. Reconnect WebSocket (server just restarted)
    if (ws) ws.close();
    setTimeout(connectWebSocket, 1000);

    // 3. Load roster (your existing code)
    try {
        const response = await fetch(`get_students_by_class.php?class_id=${classId}`);
        const data = await response.json();
        CONFIG.currentStudents = data.students || [];
        renderRoster(CONFIG.currentStudents);
        addActivityLog(`✓ Loaded ${CONFIG.currentStudents.length} students`);
        document.getElementById('camToggle').disabled = false;
    } catch (e) {
        addActivityLog('✗ Failed to load roster: ' + e.message);
    }
}

function renderRoster(students) {
    const container = document.getElementById('rosterContainer');
    container.innerHTML = '';

    if (students.length === 0) {
        container.innerHTML = '<p style="color: #94a3b8; padding: 1rem;">No students in class</p>';
        return;
    }

    students.forEach(student => {
        const row = document.createElement('div');
        row.className = 'row';
        row.dataset.studentId = student.student_id;

        const isPresent = CONFIG.attendanceRecord.has(student.student_id);
        const status = isPresent ? CONFIG.attendanceRecord.get(student.student_id).status : '';

        row.innerHTML = `
            <div style="flex: 1;">
                <span style="font-weight: 500;">${student.first_name} ${student.last_name}</span><br>
                <span style="font-size: 0.85rem; color: #94a3b8;">#${student.student_id}</span>
            </div>
            <select onchange="updateManualAttendance(this, ${student.student_id})" style="padding: 0.5rem 0.75rem; background: rgba(51, 65, 85, 0.7); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 0.4rem; color: #f1f5f9; cursor: pointer; font-size: 0.9rem;">
                <option value="">--</option>
                <option value="on_time">On Time</option>
                <option value="late">Late</option>
                <option value="absent">Absent</option>
            </select>
        `;

        if (isPresent) {
            row.style.background = 'rgba(16, 185, 129, 0.1)';
            row.style.borderLeft = '4px solid #10b981';
            const select = row.querySelector('select');
            if (select) select.value = status;
        }

        container.appendChild(row);
    });
}

function updateManualAttendance(select, studentId) {
    const value = select.value;
    const row = document.querySelector(`[data-student-id="${studentId}"]`);
    
    if (value) {
        CONFIG.attendanceRecord.set(studentId, { status: value, time: new Date().toLocaleTimeString(), confidence: 1.0 });
        row.style.background = 'rgba(16, 185, 129, 0.1)';
        row.style.borderLeft = '4px solid #10b981';
        const statusText = value === 'on_time' ? 'On Time' : value === 'late' ? 'Late' : 'Absent';
        addActivityLog(`✓ Manual: ${statusText}`);
    } else {
        CONFIG.attendanceRecord.delete(studentId);
        row.style.background = '';
        row.style.borderLeft = '';
        addActivityLog('Cleared attendance record');
    }
}

async function toggleAttendance() {
    const btn = document.getElementById('camToggle');
    const status = document.getElementById('camStatus');
    const camIndicator = document.getElementById('camIndicator');

    if (CONFIG.attendanceRunning) {
        if (dashStream) {
            dashStream.getTracks().forEach(t => t.stop());
            dashStream = null;
        }
        
        CONFIG.attendanceRunning = false;
        btn.textContent = 'Start Attendance';
        status.textContent = 'Attendance stopped';
        camIndicator.innerHTML = '● Attendance: <span style="color: #ef4444;">Inactive</span>';
        addActivityLog('Attendance tracking stopped');
    } else {
        if (!CONFIG.currentClass) {
            addActivityLog('✗ Select a class first');
            return;
        }

        try {
            dashStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } } });
            const video = document.getElementById('dashCam');
            video.srcObject = dashStream;
            video.style.display = 'block';

            CONFIG.attendanceRunning = true;
            btn.textContent = 'Stop Attendance';
            status.textContent = 'Live detection active';
            camIndicator.innerHTML = '● Attendance: <span style="color: #10b981;">Active</span>';
            addActivityLog('✓ Attendance tracking started');

        } catch (e) {
            addActivityLog('✗ Camera error: ' + e.message);
            status.textContent = 'Camera access denied';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    connectWebSocket();
    addActivityLog('Dashboard loaded');

    setInterval(() => {
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            connectWebSocket();
        }
    }, 10000);
});

window.addEventListener('beforeunload', () => {
    if (dashStream) dashStream.getTracks().forEach(track => track.stop());
    if (ws) ws.close();
});
</script>

</body>
</html>