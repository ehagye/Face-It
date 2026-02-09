// NAVIGATION
function goToLogin() {
    window.location.href = "login.html";
}

function goToEnroll() {
    window.location.href = "enroll.html";
}

function goToDashboard() {
    window.location.href = "index.html";
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
        window.location.href = "index.html";
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
