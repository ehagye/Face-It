const ctx = document.getElementById("attendanceChart");

new Chart(ctx, {
    type: "doughnut",
    data: {
        labels: ["Present", "Absent"],
        datasets: [{
            data: [26, 3],
            backgroundColor: ["#4CAF50", "#E53935"]
        }]
    },
    options: {
        plugins: {
            legend: {
                position: "bottom"
            }
        }
    }
});
function goToSettings() {
    window.location.href = "settings.html";
}

function goToAlerts() {
    window.location.href = "alerts.html";
}

function goHome() {
    window.location.href = "index.html";
}
