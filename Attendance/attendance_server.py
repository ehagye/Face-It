"""
attendance_server.py

WebSocket server that runs on the professor's laptop.
Opens the local camera, runs InsightFace detection + Supabase matching,
and pushes results to the web dashboard over WebSocket.

Events sent to dashboard:
  session_start  — class info on connect
  frame_image    — base64 JPEG of annotated camera frame
  frame_update   — fps, face count, detection time
  face_detected  — student matched and logged (or skipped)

Usage:
    python attendance_server.py --class-id 2
    python attendance_server.py --class-id 2 --port 8765 --camera 0
"""

import argparse
import asyncio
import base64
import json
import os
import sys
import time

# Ensure project root (Face-It/) is on the path so imports work
# whether you run from the project root or from inside Attendance/
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

import cv2
import numpy as np
import websockets
from datetime import datetime

from AI_Facial_Detection.face_detector import InsightFaceDetector, DetectedFace
from AI_Facial_Detection.selection import face_selection
from AI_Facial_Detection.supabase_matcher import SupabaseMatcher
from Attendance.attendance_manager import AttendanceManager


class AttendanceServer:
    def __init__(
        self,
        class_id: int,
        grace_minutes: int = 15,
        match_threshold: float = 0.55,
        camera_index: int = 0,
        stream_fps: int = 8,
        stream_quality: int = 55,
    ):
        self.class_id = class_id
        self.grace_minutes = grace_minutes
        self.match_threshold = match_threshold
        self.camera_index = camera_index
        self.stream_fps = stream_fps
        self.stream_quality = stream_quality
        self.stream_width = 640

        # AI components
        self.detector = InsightFaceDetector(ctx_id=-1, det_size=(640, 640))
        self.matcher = SupabaseMatcher()
        self.attendance = AttendanceManager()

        # Load class info
        self.class_info = self.attendance.get_class(class_id)
        if self.class_info is None:
            raise RuntimeError(f"Class ID {class_id} not found in database")

        self.scheduled_start_time = self.class_info.get("scheduled_start_time", "")

        # Detection state
        self.recent_seen: dict[int, float] = {}
        self.cooldown_seconds = 10.0
        self.last_match_label = ""
        self.last_match_time = 0.0

        # WebSocket clients
        self.clients: set = set()

        # Performance
        self.fps = 0.0
        self._last_t = time.time()
        self._last_stream_t = 0.0

    # ------------------------------------------------------------------
    # WebSocket helpers
    # ------------------------------------------------------------------
    async def broadcast(self, event: dict):
        msg = json.dumps(event)
        if self.clients:
            await asyncio.gather(
                *[c.send(msg) for c in self.clients],
                return_exceptions=True,
            )

    async def broadcast_frame(self, frame: np.ndarray):
        """Encode + send an annotated frame, throttled to stream_fps."""
        if not self.clients:
            return
        now = time.time()
        if now - self._last_stream_t < 1.0 / self.stream_fps:
            return
        self._last_stream_t = now

        h, w = frame.shape[:2]
        if w > self.stream_width:
            scale = self.stream_width / w
            frame = cv2.resize(frame, (self.stream_width, int(h * scale)), interpolation=cv2.INTER_AREA)

        _, buf = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, self.stream_quality])
        b64 = base64.b64encode(buf).decode("utf-8")

        await self.broadcast({
            "type": "frame_image",
            "image": b64,
        })

    # ------------------------------------------------------------------
    # Overlay drawing  (DetectedFace.bbox is a tuple of ints)
    # ------------------------------------------------------------------
    def draw_overlay(self, frame: np.ndarray, faces: list, best) -> np.ndarray:
        out = frame.copy()

        for face in faces:
            x1, y1, x2, y2 = face.bbox
            is_best = best is not None and face.bbox == best.bbox
            color = (0, 255, 120) if is_best else (100, 100, 255)
            thick = 3 if is_best else 2

            cv2.rectangle(out, (x1, y1), (x2, y2), color, thick)

            # Corner brackets on the selected face
            if is_best:
                L = 18
                cv2.line(out, (x1, y1), (x1 + L, y1), color, 4)
                cv2.line(out, (x1, y1), (x1, y1 + L), color, 4)
                cv2.line(out, (x2, y1), (x2 - L, y1), color, 4)
                cv2.line(out, (x2, y1), (x2, y1 + L), color, 4)
                cv2.line(out, (x1, y2), (x1 + L, y2), color, 4)
                cv2.line(out, (x1, y2), (x1, y2 - L), color, 4)
                cv2.line(out, (x2, y2), (x2 - L, y2), color, 4)
                cv2.line(out, (x2, y2), (x2, y2 - L), color, 4)

            # Confidence label
            cv2.putText(out, f"{face.score:.2f}", (x1, max(0, y1 - 8)),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2, cv2.LINE_AA)

        # Name banner for recent match
        if time.time() - self.last_match_time < 3.0 and self.last_match_label:
            font = cv2.FONT_HERSHEY_SIMPLEX
            (tw, th), _ = cv2.getTextSize(self.last_match_label, font, 0.9, 2)
            cx = out.shape[1] // 2
            pad = 10
            cv2.rectangle(out, (cx - tw // 2 - pad, 8), (cx + tw // 2 + pad, 8 + th + pad * 2), (0, 200, 100), -1)
            cv2.putText(out, self.last_match_label, (cx - tw // 2, 8 + th + pad), font, 0.9, (0, 0, 0), 2, cv2.LINE_AA)

        # Bottom stats
        cv2.putText(out, f"FPS: {self.fps:.1f}  Faces: {len(faces)}", (10, out.shape[0] - 12),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.55, (200, 200, 200), 1, cv2.LINE_AA)

        return out

    # ------------------------------------------------------------------
    # Client handling
    # ------------------------------------------------------------------
    async def handle_client(self, websocket, path=None):
        self.clients.add(websocket)
        print(f"[WS] Client connected ({len(self.clients)} total)")

        await websocket.send(json.dumps({
            "type": "session_start",
            "class_id": self.class_info["class_id"],
            "class_name": self.class_info["class_name"],
            "scheduled_start_time": self.scheduled_start_time,
        }))

        try:
            async for message in websocket:
                try:
                    cmd = json.loads(message)
                    if cmd.get("action") == "set_threshold":
                        self.match_threshold = float(cmd.get("threshold", 0.55))
                except json.JSONDecodeError:
                    pass
        except websockets.exceptions.ConnectionClosed:
            pass
        finally:
            self.clients.discard(websocket)
            print(f"[WS] Client disconnected ({len(self.clients)} total)")

    # ------------------------------------------------------------------
    # Main camera loop
    # ------------------------------------------------------------------
    async def run_camera(self):
        cap = cv2.VideoCapture(self.camera_index, cv2.CAP_DSHOW)
        if not cap.isOpened():
            raise RuntimeError(f"Cannot open camera {self.camera_index}")

        cap.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
        cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
        cap.set(cv2.CAP_PROP_FPS, 30)
        print(f"[CAMERA] Opened camera {self.camera_index}")

        retries = 0

        try:
            while True:
                ok, frame = cap.read()
                if not ok or frame is None:
                    retries += 1
                    if retries > 5:
                        await self.broadcast({"type": "error", "message": "Camera disconnected"})
                        break
                    await asyncio.sleep(0.2)
                    continue
                retries = 0

                # FPS
                now = time.time()
                dt = now - self._last_t
                if dt > 0:
                    self.fps = 0.9 * self.fps + 0.1 / dt
                self._last_t = now

                # Detect
                faces, det_ms = self.detector.detect(frame)
                best = face_selection(faces)

                # Stats event
                await self.broadcast({
                    "type": "frame_update",
                    "fps": round(self.fps, 1),
                    "faces_detected": len(faces),
                    "detection_ms": round(det_ms, 1),
                })

                # Match + log
                if best is not None and best.normed_embedding is not None:
                    match = self.matcher.match_embedding(
                        best.normed_embedding,
                        threshold=self.match_threshold,
                        match_count=1,
                    )

                    if match is not None:
                        now_ts = time.time()
                        last = self.recent_seen.get(match.student_id, 0.0)

                        self.last_match_label = f"{match.first_name} {match.last_name} ({match.score:.0%})"
                        self.last_match_time = now_ts

                        if now_ts - last >= self.cooldown_seconds:
                            result = self.attendance.log_attendance(
                                student_id=int(match.student_id),
                                class_id=self.class_id,
                                confidence_score=match.score,
                                grace_minutes=self.grace_minutes,
                                scheduled_start_time=self.scheduled_start_time,
                            )
                            self.recent_seen[match.student_id] = now_ts

                            await self.broadcast({
                                "type": "face_detected",
                                "student_id": int(match.student_id),
                                "first_name": match.first_name,
                                "last_name": match.last_name,
                                "confidence": round(match.score, 3),
                                "status": result.status if result.logged else "skipped",
                                "reason": result.reason if not result.logged else None,
                                "logged": result.logged,
                                "timestamp": datetime.now().isoformat(),
                            })

                            if result.logged:
                                print(f"[LOGGED] {match.first_name} {match.last_name} → {result.status} ({match.score:.3f})")

                # Draw overlay + stream frame
                annotated = self.draw_overlay(frame, faces, best)
                await self.broadcast_frame(annotated)

                await asyncio.sleep(0.01)

        finally:
            cap.release()
            print("[CAMERA] Released")

    # ------------------------------------------------------------------
    # Start server
    # ------------------------------------------------------------------
    async def start(self, host: str = "0.0.0.0", port: int = 8765):
        print(f"[SERVER] {self.class_info['class_name']} (ID {self.class_id})")
        print(f"[SERVER] Grace period: {self.grace_minutes} min, Threshold: {self.match_threshold}")
        print(f"[SERVER] Streaming ~{self.stream_fps} FPS, quality={self.stream_quality}")

        async with websockets.serve(
            self.handle_client, host, port,
            max_size=10 * 1024 * 1024,
        ):
            print(f"[SERVER] ws://{host}:{port}")
            await self.run_camera()


async def main():
    p = argparse.ArgumentParser(description="FaceIT Attendance Server")
    p.add_argument("--class-id", type=int, required=True)
    p.add_argument("--grace-minutes", type=int, default=15)
    p.add_argument("--threshold", type=float, default=0.55)
    p.add_argument("--camera", type=int, default=0)
    p.add_argument("--host", type=str, default="0.0.0.0")
    p.add_argument("--port", type=int, default=8765)
    p.add_argument("--stream-fps", type=int, default=8)
    p.add_argument("--stream-quality", type=int, default=55)
    args = p.parse_args()

    server = AttendanceServer(
        class_id=args.class_id,
        grace_minutes=args.grace_minutes,
        match_threshold=args.threshold,
        camera_index=args.camera,
        stream_fps=args.stream_fps,
        stream_quality=args.stream_quality,
    )
    await server.start(host=args.host, port=args.port)


if __name__ == "__main__":
    asyncio.run(main())