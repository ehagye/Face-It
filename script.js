// NAVIGATION
function goToLogin() {
    window.location.href = "login.html";
}

function goToEnroll() {
    window.location.href = "enroll.html";
}

function goToDashboard() {
    window.location.href = "main.html";
}

function goToAlerts() {
    window.location.href = "alerts.html";
}

function goToSettings() {
    window.location.href = "settings.html";
}

// LOGIN (MOCK)
function login(event) {
    event.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const error = document.getElementById("loginError");

    if (email === "professor@faceit.edu" && password === "faceit123") {
        sessionStorage.setItem("isLoggedIn", "true");
        window.location.href = "main.html";
    } else {
        error.style.display = "block";
    }
}

// LOGOUT
function logout() {
    sessionStorage.removeItem("isLoggedIn");
    goHome();
}

// PAGE PROTECTION
function protectPage() {
    if (!sessionStorage.getItem("isLoggedIn")) {
        window.location.href = "home.html";
    }
}

function mockEnroll(event) {
    event.preventDefault();

    alert("Student enrolled successfully (mock)");

    // Later:
    // - upload images
    // - insert student into MySQL
    // - associate with professor + class
}

function goToManageClasses() {
    window.location.href = "manage_classes.html";
}

function goToDashboard() {
    window.location.href = "main.html";
}

function goHome() {
    window.location.href = "home.html";
}

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

    // Freeze frame: hide video and show canvas
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