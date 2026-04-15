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
// Replaced the Python OpenCV webcam loop 
// — captures multiple face photos via the browser camera API

let videoStream = null;
let capturedImages = []; // stores base64 JPEGs, like the samples[] array in enroll_student.py

function startCamera() {
    const video = document.getElementById("video");
    const canvas = document.getElementById("canvas");
    const status = document.getElementById("camera-status");
    const overlay = document.getElementById("successOverlay");

    overlay.style.opacity = "0";
    video.style.display = "block";
    canvas.style.display = "none";

    // Reset captures when restarting camera
    capturedImages = [];

    navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
        .then(stream => {
            videoStream = stream;
            video.srcObject = stream;
            status.textContent = "Camera active — capture 5-10 photos with slight head turns.";
        })
        .catch(error => {
            console.error(error);
            status.textContent = "Camera access denied";
        });
}

function capturePhoto() {
    const video = document.getElementById("video");
    const canvas = document.getElementById("canvas");
    const status = document.getElementById("camera-status");
    const overlay = document.getElementById("successOverlay");

    // Don't capture if camera isn't running
    if (!videoStream) {
        status.textContent = "Start the camera first.";
        return;
    }

    const context = canvas.getContext("2d");
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    video.style.display = "none";
    canvas.style.display = "block";

    stopCamera();
    // Store as JPEG base64 (smaller than PNG, good enough for face embedding)
    const imageData = canvas.toDataURL("image/jpeg", 0.9);
    capturedImages.push(imageData);

    overlay.style.opacity = "1";
    setTimeout(() => { overlay.style.opacity = "0"; }, 400);

    status.textContent = `Captured ${capturedImages.length} photo(s) — aim for 5-10.`;
}

function stopCamera() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
}

// Form submission
// Injects all captured images as hidden fields so PHP can upload them to Supabase Storage
document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector(".enroll-form");
    if (!form) return;

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        if (capturedImages.length < 3) {
            alert("Please capture at least 3 photos before enrolling.");
            return;
        }

        // Add each captured image as a hidden input so it's sent with the POST
        capturedImages.forEach((img, i) => {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = `faces[${i}]`;
            input.value = img;
            form.appendChild(input);
        });

        stopCamera();
        form.submit();
    });
});