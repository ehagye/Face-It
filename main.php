<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
require 'config.php';
$professor = $_SESSION['user'];
$prof_name = $professor['first_name'] . ' ' . $professor['last_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face-IT Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
</head>
<body>
<div class="dashboard-wrapper">
    <header class="header">
        <div class="header-content">
            <h1>Face-IT Attendance</h1>
            <p>Welcome, <?php echo htmlspecialchars($prof_name); ?></p>
        </div>
        <nav class="nav-links">
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <main class="dashboard-container">
        <section class="dashboard-grid">
            <div class="column">
                <div class="glass-card">
                    <h3>Class Selection</h3>
                    <select id="classSelect" onchange="handleClassChange()">
                        <option value="">-- Select a class --</option>
                    </select>
                </div>

                <div class="glass-card">
                    <h3>Class Roster</h3>
                    <div class="roster" id="rosterContainer">
                        <p style="color: #94a3b8; padding: 1rem; text-align: center;">Select a class above</p>
                    </div>
                </div>

                <div class="glass-card">
                    <h3>Activity Log</h3>
                    <div class="log" id="activityLog">
                        <p style="color: #94a3b8;">No activity yet</p>
                    </div>
                </div>
            </div>

            <div class="column">
                <div class="glass-card">
                    <h3>Attendance Control</h3>
                    <button id="attendanceBtn" class="manage-btn" onclick="toggleAttendance()" disabled>
                        Start Attendance
                    </button>
                    <p id="attendanceStatus" style="color: #94a3b8; margin-top: 0.5rem;">Not started</p>
                </div>

                <div class="glass-card">
                    <h3>Attendance Summary</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="padding: 0.75rem; background: rgba(16, 185, 129, 0.1); border-radius: 0.5rem;">
                            <p style="margin: 0; color: #94a3b8; font-size: 0.9rem;">Present</p>
                            <p style="margin: 0.25rem 0 0 0; font-size: 1.5rem; color: #10b981;" id="presentCount">0</p>
                        </div>
                        <div style="padding: 0.75rem; background: rgba(239, 68, 68, 0.1); border-radius: 0.5rem;">
                            <p style="margin: 0; color: #94a3b8; font-size: 0.9rem;">Absent</p>
                            <p style="margin: 0.25rem 0 0 0; font-size: 1.5rem; color: #ef4444;" id="absentCount">0</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
const CONFIG = {
    supabaseUrl: '<?php echo getenv("SUPABASE_URL") ?: ""; ?>',
    supabaseKey: '<?php echo getenv("SUPABASE_KEY") ?: ""; ?>',
    classId: null,
};

let db = null;
let attendanceActive = false;
let loggedStudents = new Set();
let pollingInterval = null;

// Initialize Supabase
async function initSupabase() {
    const { createClient } = window.supabase;
    db = createClient(CONFIG.supabaseUrl, CONFIG.supabaseKey);
    console.log('[INIT] Supabase ready');
}

// Load classes
async function loadClasses() {
    try {
        const { data, error } = await db
            .from('classes')
            .select('class_id, class_name')
            .order('class_name');
        
        if (error) throw error;
        
        const select = document.getElementById('classSelect');
        data.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.class_id;
            option.textContent = cls.class_name;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('[ERROR] Load classes:', error);
    }
}

// Handle class change
async function handleClassChange() {
    const classId = document.getElementById('classSelect').value;
    CONFIG.classId = classId;
    
    if (!classId) {
        document.getElementById('rosterContainer').innerHTML = 
            '<p style="color: #94a3b8; padding: 1rem; text-align: center;">Select a class above</p>';
        document.getElementById('attendanceBtn').disabled = true;
        return;
    }
    
    document.getElementById('attendanceBtn').disabled = false;
    loggedStudents.clear();
    await loadRoster(classId);
    startPolling(classId);
}

// Load roster
async function loadRoster(classId) {
    try {
        const { data, error } = await db
            .from('students')
            .select('id, first_name, last_name')
            .eq('class_id', classId)
            .order('last_name');
        
        if (error) throw error;
        
        const container = document.getElementById('rosterContainer');
        container.innerHTML = '';
        
        data.forEach(student => {
            const row = document.createElement('div');
            row.className = 'roster-item';
            row.id = `student-${student.id}`;
            row.innerHTML = `
                <div style="flex: 1;">
                    <p style="margin: 0; font-weight: 500;">${student.first_name} ${student.last_name}</p>
                    <p style="margin: 0.25rem 0 0 0; color: #94a3b8; font-size: 0.9rem;">#${student.id}</p>
                </div>
                <select class="status-dropdown" onchange="updateAttendance(${student.id}, this.value)">
                    <option value="--">--</option>
                    <option value="on_time">On Time</option>
                    <option value="late">Late</option>
                    <option value="absent">Absent</option>
                </select>
            `;
            container.appendChild(row);
        });
        
        updateCounts();
    } catch (error) {
        console.error('[ERROR] Load roster:', error);
    }
}

// Start polling for attendance updates
function startPolling(classId) {
    if (pollingInterval) clearInterval(pollingInterval);
    
    let lastCheck = new Date().toISOString();
    
    pollingInterval = setInterval(async () => {
        if (!attendanceActive) return;
        
        try {
            const { data, error } = await db
                .from('attendance_logs')
                .select('*')
                .eq('class_id', classId)
                .gt('detected_at', lastCheck)
                .order('detected_at', { ascending: false });
            
            if (error) throw error;
            
            if (data && data.length > 0) {
                data.forEach(log => {
                    if (!loggedStudents.has(log.student_id)) {
                        loggedStudents.add(log.student_id);
                        
                        const el = document.getElementById(`student-${log.student_id}`);
                        if (el) {
                            el.querySelector('.status-dropdown').value = log.status || 'on_time';
                            el.style.background = 'rgba(16, 185, 129, 0.1)';
                        }
                        
                        addLog(`✓ ${log.student_name} (${log.status})`);
                        updateCounts();
                    }
                });
                lastCheck = data[0].detected_at;
            }
        } catch (error) {
            console.error('[POLLING] Error:', error);
        }
    }, 1000);
}

// Toggle attendance
function toggleAttendance() {
    attendanceActive = !attendanceActive;
    const btn = document.getElementById('attendanceBtn');
    const status = document.getElementById('attendanceStatus');
    
    if (attendanceActive) {
        btn.textContent = 'Stop Attendance';
        btn.style.background = '#ef4444';
        status.textContent = 'Recording...';
        status.style.color = '#10b981';
        addLog('Started attendance recording');
        
        const cmd = `python camera_with_display.py --class-id ${CONFIG.classId} --camera 0`;
        const message = `Copy and run this in PowerShell:\n\n${cmd}\n\nThen click OK.`;
        alert(message);
    } else {
        btn.textContent = 'Start Attendance';
        btn.style.background = '';
        status.textContent = 'Not started';
        status.style.color = '#94a3b8';
        addLog('Stopped attendance recording');
    }
}

// Update attendance manually
async function updateAttendance(studentId, status) {
    if (status === '--') return;
    
    try {
        const { data: existing } = await db
            .from('attendance_logs')
            .select('log_id')
            .eq('student_id', studentId)
            .eq('class_id', CONFIG.classId);
        
        if (existing && existing.length > 0) {
            await db
                .from('attendance_logs')
                .update({ status })
                .eq('log_id', existing[0].log_id);
        } else {
            const name = document.querySelector(`#student-${studentId} p`).textContent;
            await db
                .from('attendance_logs')
                .insert({
                    student_id: studentId,
                    class_id: CONFIG.classId,
                    detected_at: new Date().toISOString(),
                    status: status,
                    confidence_score: 1.0,
                    student_name: name
                });
        }
        
        loggedStudents.add(studentId);
        updateCounts();
    } catch (error) {
        console.error('[ERROR] Update attendance:', error);
    }
}

// Add activity log entry
function addLog(msg) {
    const log = document.getElementById('activityLog');
    if (log.textContent.includes('No activity yet')) {
        log.innerHTML = '';
    }
    
    const entry = document.createElement('p');
    entry.style.cssText = 'margin: 0.5rem 0; color: #10b981; font-size: 0.9rem;';
    entry.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
    log.insertBefore(entry, log.firstChild);
    
    while (log.children.length > 10) {
        log.removeChild(log.lastChild);
    }
}

// Update counts
function updateCounts() {
    const roster = document.getElementById('rosterContainer');
    const dropdowns = roster.querySelectorAll('.status-dropdown');
    
    let present = 0, absent = 0;
    dropdowns.forEach(dd => {
        if (dd.value === 'on_time' || dd.value === 'late') present++;
        if (dd.value === 'absent') absent++;
    });
    
    document.getElementById('presentCount').textContent = present;
    document.getElementById('absentCount').textContent = absent;
}

// Initialize
(async () => {
    await initSupabase();
    await loadClasses();
    addLog('Dashboard loaded');
})();
</script>

<style>
.roster-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: rgba(51, 65, 85, 0.5);
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.status-dropdown {
    padding: 0.5rem;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(148, 163, 184, 0.2);
    color: #e2e8f0;
    border-radius: 0.35rem;
    cursor: pointer;
}
</style>
</body>
</html>