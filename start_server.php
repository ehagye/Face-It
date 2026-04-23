<?php
// start_server.php
session_start();
if (empty($_SESSION['user'])) { http_response_code(403); exit; }

$class_id = intval($_GET['class_id'] ?? 0);
if (!$class_id) { echo json_encode(['error' => 'No class_id']); exit; }

// Kill any existing server first
shell_exec("taskkill /F /IM python.exe 2>NUL"); // Windows
// shell_exec("pkill -f attendance_server.py"); // Linux/Mac

// Start new server for this class
$cmd = "python attendance_server.py --class-id $class_id --camera 0 --port 8765";
pclose(popen("start /B " . $cmd, "r")); // Windows
// shell_exec($cmd . " > /dev/null 2>&1 &"); // Linux/Mac

sleep(2); // Give it time to boot
echo json_encode(['success' => true, 'class_id' => $class_id]);