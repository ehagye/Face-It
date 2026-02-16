// NAVIGATION
function goToLogin() {
    window.location.href = "login.php";
}

function goToEnroll() {
    window.location.href = "enroll.php";
}

function goToDashboard() {
    window.location.href = "index.php";
}

function goToAlerts() {
    window.location.href = "alerts.php";
}

function goToSettings() {
    window.location.href = "settings.php";
}

// LOGIN (MOCK)
function login(event) {
    event.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const error = document.getElementById("loginError");

    if (email === "professor@faceit.edu" && password === "faceit123") {
        sessionStorage.setItem("isLoggedIn", "true");
        window.location.href = "index.php";
    } else {
        error.style.display = "block";
    }
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