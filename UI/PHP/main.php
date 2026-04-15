<?php
session_start();

if (empty($_SESSION['user'])) {
    header("Location: main.php");
    exit;
}

$config = require 'config.php';
$professor_email = $_SESSION['user']['email'] ?? '';

/* ── Supabase helper ─────────────────────────────────────────── */
function supabase_get($path, $config) {
    $url = $config['SUPABASE_URL'] . "/rest/v1/" . $path;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['SUPABASE_KEY']}",
            "apikey: {$config['SUPABASE_KEY']}",
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200) ? (json_decode($body, true) ?? []) : [];
}

/* ── Load professor ──────────────────────────────────────────── */
$rows = supabase_get("professors?email=eq." . urlencode($professor_email) . "&select=*", $config);
$professor = $rows[0] ?? null;

/* ── Load their classes ──────────────────────────────────────── */
$classes = supabase_get(
    "classes?professor_id=eq." . intval($professor['professor_id']) . "&select=*&order=class_name.asc",
    $config
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FaceIT — Dashboard</title>
<link rel="stylesheet" href="../styles.css">
</head>
<body class="dark-bg">

<!-- ═══════════════ TOP BAR ═══════════════ -->
<header class="top-bar">
    <h1>Face-IT</h1>
    <nav class="nav-actions">
        <a href="main.php" class="nav-btn">Dashboard</a>
        <a href="manage_classes.php" class="nav-btn">Manage Classes</a>
        <a href="settings.php" class="nav-btn">Settings</a>
        <a href="logout.php" class="nav-btn">Log Out</a>
    </nav>
</header>

<main class="dashboard">

<!-- ═══════════════ PROFESSOR CARD ═══════════════ -->
<section class="glass-card professor-card">
    <div>
        <h2><?php echo htmlspecialchars($professor['first_name'] . ' ' . $professor['last_name']); ?></h2>
        <p class="subtitle"><?php echo htmlspecialchars($professor['email']); ?></p>
    </div>
</section>

<section class="dashboard-grid">

<!-- ══════════════════════════════════════════════
     LEFT COLUMN — Class selector, Roster, Log
     ══════════════════════════════════════════════ -->
<div class="column">

    <!-- CLASS SELECTOR -->
    <div class="glass-card">
        <div class="card-header">
            <h3>Class Selection</h3>
            <a href="manage_classes.php" class="manage-btn">Manage Classes</a>
        </div>
        <select id="classSelect" onchange="onClassChange()">
            <option value="">— Select a class —</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?php echo $c['class_id']; ?>"
                        data-start="<?php echo htmlspecialchars($c['scheduled_start_time'] ?? ''); ?>">
                    <?php echo htmlspecialchars($c['class_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- ROSTER -->
    <div class="glass-card">
        <div class="card-header">
            <h3>Class Roster</h3>
            <span class="subtitle" id="rosterCount"></span>
        </div>
        <div class="roster" id="rosterContainer">
            <p class="subtitle" style="text-align:center;padding:1.5rem 0">
                Select a class above
            </p>
        </div>
    </div>

    <!-- ACTIVITY LOG -->
    <div class="glass-card">
        <h3>Activity Log</h3>
        <div class="log" id="activityLog">
            <p class="subtitle">No activity yet</p>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════
     RIGHT COLUMN — Camera feed, Stats, Controls
     ══════════════════════════════════════════════ -->
<div class="column">

    <!-- LIVE CAMERA FEED (from Python server) -->
    <div class="glass-card">
        <div class="card-header">
            <h3>Live Detection Feed</h3>
            <button id="btnStartStop" class="manage-btn" onclick="toggleSession()" disabled>
                Start Attendance
            </button>
        </div>

        <div class="camera-feed-wrapper" id="feedWrapper">
            <img id="feedImage" style="display:none; width:100%; border-radius:8px;" alt="Live feed">
            <p id="feedPlaceholder" class="subtitle" style="padding:3rem 0; text-align:center">
                Select a class &amp; press Start Attendance
            </p>
        </div>

        <!-- Detection stats bar -->
        <div class="stats-bar">
            <span><strong>Server:</strong>
                <span id="wsStatus" style="color:#ef4444">● Disconnected</span>
            </span>
            <span><strong>FPS:</strong> <span id="fpsVal">—</span></span>
            <span><strong>Faces:</strong> <span id="facesVal">0</span></span>
            <span><strong>Det:</strong> <span id="detVal">—</span>ms</span>
        </div>
    </div>

    <!-- LATEST MATCH + THRESHOLD -->
    <div class="glass-card">
        <h3>Detection</h3>
        <div id="latestMatch" style="margin:0.75rem 0; font-size:1.05rem; color:#94a3b8">
            Waiting for detections…
        </div>
        <div style="margin-top:1rem">
            <label class="subtitle" style="font-size:0.85rem">Match Threshold</label>
            <div style="display:flex; align-items:center; gap:0.75rem; margin-top:0.4rem">
                <input type="range" id="thresholdSlider" min="0.3" max="0.9" step="0.05" value="0.55"
                       oninput="onThresholdChange(this.value)" style="flex:1">
                <span id="thresholdVal" style="font-weight:600; min-width:3ch">0.55</span>
            </div>
        </div>
    </div>

    <!-- SYSTEM STATUS -->
    <div class="glass-card status">
        <h3>System Status</h3>
        <p id="wsIndicator" class="inactive">● Detection Server: Disconnected</p>
        <p id="sessionIndicator" class="inactive" style="margin-top:0.5rem">● Attendance: Inactive</p>
    </div>

</div>

</section>
</main>

<!-- ═══════════════════════════════════════════════════════════════
     JAVASCRIPT — WebSocket, roster loading, session control
     ═══════════════════════════════════════════════════════════════ -->
<script>
/* ── State ────────────────────────────────────────────────── */
const S = {
    wsUrl: "ws://localhost:8765",
    classId: null,
    students: [],
    attendance: new Map(),   // student_id → {status, time, confidence}
    running: false,
    ws: null,
    reconnectTimer: null,
};

/* ── WebSocket ────────────────────────────────────────────── */
function connectWS() {
    if (S.ws && S.ws.readyState === WebSocket.OPEN) return;
    clearTimeout(S.reconnectTimer);

    try {
        S.ws = new WebSocket(S.wsUrl);

        S.ws.onopen = () => {
            setWSStatus(true);
            log("Server connected", "ok");
        };

        S.ws.onmessage = (e) => {
            try { handleEvent(JSON.parse(e.data)); }
            catch (err) { console.error("WS parse:", err); }
        };

        S.ws.onerror = () => setWSStatus(false);

        S.ws.onclose = () => {
            setWSStatus(false);
            if (S.running) S.reconnectTimer = setTimeout(connectWS, 3000);
        };
    } catch (e) {
        setWSStatus(false);
        if (S.running) S.reconnectTimer = setTimeout(connectWS, 3000);
    }
}

function disconnectWS() {
    clearTimeout(S.reconnectTimer);
    if (S.ws) { S.ws.onclose = null; S.ws.close(); S.ws = null; }
    setWSStatus(false);
}

function sendCmd(action, data = {}) {
    if (S.ws && S.ws.readyState === WebSocket.OPEN)
        S.ws.send(JSON.stringify({ action, ...data }));
}

function setWSStatus(ok) {
    const el = document.getElementById("wsStatus");
    const ind = document.getElementById("wsIndicator");
    if (ok) {
        el.style.color = "#22c55e";
        el.textContent = "● Connected";
        ind.className = "active";
        ind.textContent = "● Detection Server: Connected";
    } else {
        el.style.color = "#ef4444";
        el.textContent = "● Disconnected";
        ind.className = "inactive";
        ind.textContent = "● Detection Server: Disconnected";
    }
}

/* ── Event handler ────────────────────────────────────────── */
function handleEvent(evt) {
    switch (evt.type) {
        case "session_start":
            log("Session: " + (evt.class_name || "Class " + evt.class_id), "info");
            break;

        case "frame_image": {
            const img = document.getElementById("feedImage");
            const ph  = document.getElementById("feedPlaceholder");
            img.src = "data:image/jpeg;base64," + evt.image;
            img.style.display = "block";
            ph.style.display = "none";
            break;
        }

        case "frame_update":
            document.getElementById("fpsVal").textContent   = evt.fps.toFixed(1);
            document.getElementById("facesVal").textContent  = evt.faces_detected;
            document.getElementById("detVal").textContent    = evt.detection_ms.toFixed(0);
            break;

        case "face_detected":
            if (!evt.logged) break;
            S.attendance.set(evt.student_id, {
                status: evt.status,
                time: new Date().toLocaleTimeString(),
                confidence: evt.confidence,
            });
            highlightRosterRow(evt.student_id, evt.status);
            updateCount();
            showLatestMatch(evt);
            log(evt.first_name + " " + evt.last_name + " — "
                + evt.status + " (" + (evt.confidence * 100).toFixed(0) + "%)", "ok");
            break;

        case "error":
            log("Server error: " + evt.message, "warn");
            break;
    }
}

/* ── Latest match display ─────────────────────────────────── */
function showLatestMatch(evt) {
    const el = document.getElementById("latestMatch");
    const label = evt.status === "on_time" ? "On Time" : "Late";
    const color = evt.status === "on_time" ? "#22c55e" : "#f59e0b";
    el.innerHTML =
        '<strong style="font-size:1.1rem">' + evt.first_name + ' ' + evt.last_name + '</strong><br>'
        + '<span style="color:' + color + '; font-weight:600; text-transform:uppercase; font-size:0.85rem">'
        + label + '</span>'
        + ' <span class="subtitle" style="font-size:0.82rem">'
        + (evt.confidence * 100).toFixed(1) + '% match</span>';
}

/* ── Class selection & roster ─────────────────────────────── */
async function onClassChange() {
    const sel = document.getElementById("classSelect");
    const btn = document.getElementById("btnStartStop");

    if (S.running) stopSession();

    if (!sel.value) {
        S.classId = null;
        S.students = [];
        S.attendance.clear();
        renderRoster();
        btn.disabled = true;
        return;
    }

    S.classId = parseInt(sel.value);
    S.attendance.clear();

    try {
        const res = await fetch("get_students_by_class.php?class_id=" + S.classId);
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        S.students = data.students || [];
        renderRoster();
        updateCount();
        btn.disabled = false;
        log("Loaded " + S.students.length + " students", "info");
    } catch (e) {
        log("Roster load failed: " + e.message, "warn");
        btn.disabled = true;
    }
}

function renderRoster() {
    const c = document.getElementById("rosterContainer");

    if (!S.students.length) {
        c.innerHTML = '<p class="subtitle" style="text-align:center;padding:1.5rem 0">'
            + (S.classId ? "No students in this class" : "Select a class above") + '</p>';
        return;
    }

    c.innerHTML = "";
    S.students.forEach(s => {
        const rec = S.attendance.get(s.student_id);
        const row = document.createElement("div");
        row.className = "row";
        row.id = "roster-" + s.student_id;

        if (rec) {
            row.style.background = rec.status === "late"
                ? "rgba(245,158,11,0.08)" : "rgba(34,197,94,0.08)";
            row.style.borderLeft = "3px solid " + (rec.status === "late" ? "#f59e0b" : "#22c55e");
        }

        const statusLabel = rec
            ? '<span style="color:' + (rec.status === "late" ? "#f59e0b" : "#22c55e")
              + '; font-weight:600; font-size:0.85rem; text-transform:uppercase">'
              + (rec.status === "on_time" ? "On Time" : rec.status) + '</span>'
            : '<select onchange="manualMark(' + s.student_id + ', this.value)" '
              + 'style="padding:6px 10px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); border-radius:8px; color:#e2e8f0; font-size:0.85rem;">'
              + '<option value="">—</option>'
              + '<option value="on_time">On Time</option>'
              + '<option value="late">Late</option>'
              + '<option value="absent">Absent</option>'
              + '</select>';

        row.innerHTML =
            '<span style="font-weight:500">' + s.first_name + ' ' + s.last_name + '</span>'
            + '<span class="subtitle" style="font-size:0.85rem">#' + s.student_id + '</span>'
            + statusLabel;

        c.appendChild(row);
    });
}

function highlightRosterRow(studentId, status) {
    const row = document.getElementById("roster-" + studentId);
    if (!row) return;
    const color = status === "late" ? "#f59e0b" : "#22c55e";
    row.style.background = status === "late" ? "rgba(245,158,11,0.08)" : "rgba(34,197,94,0.08)";
    row.style.borderLeft = "3px solid " + color;

    // Replace the select with a status label
    const cells = row.children;
    if (cells.length >= 3) {
        cells[2].innerHTML = '<span style="color:' + color
            + '; font-weight:600; font-size:0.85rem; text-transform:uppercase">'
            + (status === "on_time" ? "On Time" : status) + '</span>';
    }
}

function manualMark(studentId, status) {
    if (!status) return;
    S.attendance.set(studentId, { status, time: new Date().toLocaleTimeString(), confidence: 1.0 });
    highlightRosterRow(studentId, status);
    updateCount();
    const s = S.students.find(x => x.student_id === studentId);
    log("Manual: " + (s ? s.first_name + " " + s.last_name : "#" + studentId) + " → " + status, "info");
}

function updateCount() {
    document.getElementById("rosterCount").textContent =
        S.attendance.size + " / " + S.students.length + " present";
}

/* ── Session control ──────────────────────────────────────── */
function toggleSession() {
    S.running ? stopSession() : startSession();
}

function startSession() {
    if (!S.classId) { log("Select a class first", "warn"); return; }
    S.running = true;
    document.getElementById("btnStartStop").textContent = "Stop Attendance";
    document.getElementById("sessionIndicator").className = "active";
    document.getElementById("sessionIndicator").textContent = "● Attendance: Active";
    log("Connecting to detection server…", "info");
    connectWS();
}

function stopSession() {
    S.running = false;
    disconnectWS();
    document.getElementById("btnStartStop").textContent = "Start Attendance";
    document.getElementById("sessionIndicator").className = "inactive";
    document.getElementById("sessionIndicator").textContent = "● Attendance: Inactive";
    document.getElementById("feedImage").style.display = "none";
    document.getElementById("feedPlaceholder").style.display = "";
    document.getElementById("fpsVal").textContent = "—";
    document.getElementById("facesVal").textContent = "0";
    document.getElementById("detVal").textContent = "—";
    log("Session stopped", "info");
}

/* ── Threshold ────────────────────────────────────────────── */
function onThresholdChange(val) {
    document.getElementById("thresholdVal").textContent = parseFloat(val).toFixed(2);
    sendCmd("set_threshold", { threshold: parseFloat(val) });
}

/* ── Activity log ─────────────────────────────────────────── */
function log(msg, type) {
    const el = document.getElementById("activityLog");
    const t = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", second: "2-digit" });

    // Clear the "No activity yet" placeholder
    if (el.querySelector(".subtitle")) el.innerHTML = "";

    const p = document.createElement("p");
    p.textContent = "[" + t + "] " + msg;
    if (type === "ok") p.style.color = "#22c55e";
    else if (type === "warn") p.className = "warn";
    else if (type === "info") p.style.color = "#4fc3ff";

    el.insertBefore(p, el.firstChild);
    while (el.children.length > 40) el.removeChild(el.lastChild);
}

/* ── Init ─────────────────────────────────────────────────── */
window.addEventListener("beforeunload", disconnectWS);
</script>

</body>
</html>