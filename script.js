// ===============================
// API PROXY HELPER
// ===============================
function fetchFromAPI(endpoint, query = '') {
    return fetch(`api.php?endpoint=${endpoint}&query=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .catch(err => console.error('API error:', err));
}

// ===============================
// NAVIGATION
// ===============================
function goToLogin() { window.location.href = "login.php"; }
function goToEnroll() { window.location.href = "enroll.php"; }
function goToDashboard() { window.location.href = "main.php"; }
function goToAlerts() { window.location.href = "alerts.php"; }
function goToSettings() { window.location.href = "settings.php"; }
function goToManageClasses() { window.location.href = "manage_classes.php"; }
function goHome() { window.location.href = "home.html"; }

// ===============================
// LOGIN (MOCK)
// ===============================
function login(event) {
    event.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const error = document.getElementById("loginError");

    if (email === "professor@faceit.edu" && password === "faceit123") {
        sessionStorage.setItem("isLoggedIn", "true");
        window.location.href = "main.php";
    } else {
        error.style.display = "block";
    }
}

// ===============================
// LOGOUT
// ===============================
function logout() {
    sessionStorage.removeItem("isLoggedIn");
    window.location.href = "home.html";
}

function mockEnroll(event) {
    event.preventDefault();
    alert("Student enrolled successfully (mock)");
}

// ===============================
// CAMERA
// ===============================
let videoStream = null;

function startCamera() {
    const video = document.getElementById("video");
    const status = document.getElementById("camera-status");
    const overlay = document.getElementById("successOverlay");

    overlay.style.opacity = "0";
    video.style.display = "block";

    navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
        .then(stream => {
            videoStream = stream;
            video.srcObject = stream;
            status.textContent = "Camera active";
        })
        .catch(error => {
            console.error(error);
            status.textContent = "Camera access denied";
        });
}

function capturePhoto() {
    const video = document.getElementById("video");
    const canvas = document.getElementById("canvas");
    const hiddenInput = document.getElementById("face_image");
    const status = document.getElementById("camera-status");
    const overlay = document.getElementById("successOverlay");

    const context = canvas.getContext("2d");

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    const imageData = canvas.toDataURL("image/png");
    hiddenInput.value = imageData;

    video.style.display = "none";
    canvas.style.display = "block";

    stopCamera();

    overlay.style.opacity = "1";
    status.textContent = "Face captured successfully";
}

function stopCamera() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
}

// ===============================
// LOAD ROSTER (FIXED)
// ===============================
async function loadRoster() {
    const classSelect = document.querySelector('select');
    const selectedClassName = classSelect ? classSelect.value : 'Data Science 101';

    const students = await fetchFromAPI(
        'students',
        `?select=first_name,last_name,student_id&class_name=eq.${encodeURIComponent(selectedClassName)}`
    );

    const roster = document.querySelector('.roster');
    if (!roster || !students || students.length === 0) return;

    roster.innerHTML = students.map(student => `
        <div class="row">
            <span>${student.first_name} ${student.last_name}</span>
            <span>${student.student_id}</span>
            <select class="attendance-select" data-id="${student.student_id}">
                <option>Present</option>
                <option>Absent</option>
            </select>
        </div>
    `).join('');
}

// ===============================
// LOAD ACTIVITY LOG
// ===============================
async function loadActivityLog() {
    const logs = await fetchFromAPI(
        'attendance_logs',
        '?select=*&order=detected_at.desc&limit=10'
    );

    const log = document.querySelector('.log');
    if (!log || !logs || logs.length === 0) return;

    log.innerHTML = logs.map(entry => `
        <p class="${entry.confidence_score < 0.8 ? 'warn' : ''}">
            [${entry.detected_at}] Student ${entry.student_id} (${entry.status})
        </p>
    `).join('');
}

// ===============================
// GLOBAL CHART VARIABLE
// ===============================
let attendanceChart;

// ===============================
// MAIN INIT
// ===============================
document.addEventListener("DOMContentLoaded", () => {

    const present = typeof window.present !== "undefined" ? window.present : 0;
    const absent = typeof window.absent !== "undefined" ? window.absent : 0;

    const ctx = document.getElementById("attendanceChart");
    if (ctx) {
        attendanceChart = new Chart(ctx.getContext("2d"), {
            type: "doughnut",
            data: {
                labels: ["Present", "Absent"],
                datasets: [{
                    data: [present, absent],
                    backgroundColor: ["#4fc3ff", "#ff6b6b"]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: { color: "white" }
                    }
                }
            }
        });
    }

    // OPTIONAL: load dynamic data
    loadRoster();
    loadActivityLog();
});

// ===============================
// EVENT DELEGATION (KEY FIX)
// ===============================
document.addEventListener("change", (e) => {

    if (!e.target.classList.contains("attendance-select")) return;

    const select = e.target;
    const studentId = select.dataset.id;
    const status = select.value;

    console.log("Sending:", studentId, status);

    fetch("update_attendance.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            student_id: studentId,
            status: status
        })
    })
    .then(res => res.json())
    .then(data => {
        console.log("Saved:", data);
    })
    .catch(err => console.error("Error:", err));

    // UPDATE CHART
    if (!attendanceChart) return;

    const selects = document.querySelectorAll(".attendance-select");

    let newPresent = 0;
    let newAbsent = 0;

    selects.forEach(s => {
        if (s.value === "Present") newPresent++;
        else newAbsent++;
    });

    attendanceChart.data.datasets[0].data = [newPresent, newAbsent];
    attendanceChart.update();
});