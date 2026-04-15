#!/usr/bin/env python3
"""
server_manager.py

Start/stop/monitor the attendance_server.py process.

Usage:
    python server_manager.py --class-id 2 --start
    python server_manager.py --status
    python server_manager.py --stop
    python server_manager.py --logs
"""

import argparse
import subprocess
import os
import sys
import json
import time
from pathlib import Path


class ServerManager:
    def __init__(self):
        self.dir = Path(__file__).parent
        self.script = self.dir / "attendance_server.py"
        self.pid_file = self.dir / ".server.pid"
        self.log_file = self.dir / "server.log"

    def start(self, class_id, camera=0, grace=15, threshold=0.55, port=8765):
        if self.is_running():
            print("Server is already running. Use --stop first.")
            return

        cmd = [
            sys.executable, str(self.script),
            "--class-id", str(class_id),
            "--camera", str(camera),
            "--grace-minutes", str(grace),
            "--threshold", str(threshold),
            "--port", str(port),
        ]

        kwargs = dict(
            stdout=open(self.log_file, "w"),
            stderr=subprocess.STDOUT,
        )
        if sys.platform == "win32":
            kwargs["creationflags"] = subprocess.CREATE_NEW_PROCESS_GROUP
        else:
            kwargs["start_new_session"] = True

        proc = subprocess.Popen(cmd, **kwargs)

        with open(self.pid_file, "w") as f:
            json.dump({"pid": proc.pid, "class_id": class_id, "port": port, "start": time.time()}, f)

        print(f"Started (PID {proc.pid})")
        print(f"WebSocket: ws://localhost:{port}")
        print(f"Logs: {self.log_file}")

    def stop(self):
        if not self.pid_file.exists():
            print("No server running."); return
        data = json.loads(self.pid_file.read_text())
        try:
            os.kill(data["pid"], 9 if sys.platform == "win32" else 15)
        except ProcessLookupError:
            pass
        self.pid_file.unlink(missing_ok=True)
        print("Stopped.")

    def status(self):
        if not self.pid_file.exists():
            print("Not running."); return
        data = json.loads(self.pid_file.read_text())
        up = int(time.time() - data["start"])
        print(f"Running — PID {data['pid']}, class {data['class_id']}, port {data['port']}, uptime {up}s")

    def logs(self, n=30):
        if not self.log_file.exists():
            print("No log file."); return
        lines = self.log_file.read_text().splitlines()
        print("\n".join(lines[-n:]))

    def is_running(self):
        if not self.pid_file.exists():
            return False
        try:
            pid = json.loads(self.pid_file.read_text())["pid"]
            os.kill(pid, 0)
            return True
        except:
            return False


if __name__ == "__main__":
    p = argparse.ArgumentParser()
    p.add_argument("--start", action="store_true")
    p.add_argument("--stop", action="store_true")
    p.add_argument("--status", action="store_true")
    p.add_argument("--logs", type=int, nargs="?", const=30)
    p.add_argument("--class-id", type=int)
    p.add_argument("--camera", type=int, default=0)
    p.add_argument("--grace-minutes", type=int, default=15)
    p.add_argument("--threshold", type=float, default=0.55)
    p.add_argument("--port", type=int, default=8765)
    a = p.parse_args()
    m = ServerManager()

    if a.start:
        if not a.class_id: p.error("--class-id required with --start")
        m.start(a.class_id, a.camera, a.grace_minutes, a.threshold, a.port)
    elif a.stop:    m.stop()
    elif a.status:  m.status()
    elif a.logs is not None: m.logs(a.logs)
    else: p.print_help()