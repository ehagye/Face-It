import sys
import time
from dataclasses import dataclass
from typing import Optional

import cv2
import numpy as np

from AI_Facial_Detection.face_detector import InsightFaceDetector
from AI_Facial_Detection.overlay import draw_faces_overlay
from AI_Facial_Detection.selection import face_selection
from AI_Facial_Detection.supabase_matcher import SupabaseMatcher
from attendance_manager import AttendanceManager


print("Python:", sys.version)
print("OpenCV:", cv2.__version__)
print("Numpy:", np.__version__)


@dataclass
class CameraConfiguration:
    camera_index: int = 0
    width: int = 1280
    height: int = 720
    target_fps: int = 30
    debug_window: str = "Attendance Mode"


class FPSMeter:
    def __init__(self, smoothing: float = 0.9):
        self.smoothing = float(smoothing)
        self._last = None
        self._fps = 0.0

    def update(self) -> float:
        now = time.time()
        if self._last is None:
            self._last = now
            return self._fps

        dt = now - self._last
        self._last = now
        inst = (1.0 / dt) if dt > 0 else 0.0
        self._fps = self._fps * self.smoothing + inst * (1.0 - self.smoothing)
        return self._fps


def create_overlay(frame: np.ndarray, fps: float, text: str = "") -> np.ndarray:
    out = frame.copy()
    message = f"FPS: {fps:5.1f}"
    if text:
        message += f" | {text}"

    cv2.putText(
        out,
        message,
        (10, 30),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.8,
        (0, 255, 0),
        2,
        cv2.LINE_AA
    )

    cv2.putText(
        out,
        "Press 'q' to quit",
        (10, 60),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (0, 255, 0),
        2,
        cv2.LINE_AA
    )

    return out


def open_camera(cfg: CameraConfiguration) -> cv2.VideoCapture:
    cap = cv2.VideoCapture(cfg.camera_index, cv2.CAP_DSHOW)

    if not cap.isOpened():
        raise RuntimeError(f"Could not open camera index {cfg.camera_index}")

    cap.set(cv2.CAP_PROP_FRAME_WIDTH, cfg.width)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, cfg.height)
    cap.set(cv2.CAP_PROP_FPS, cfg.target_fps)

    return cap


def read_frame(cap: cv2.VideoCapture) -> Optional[np.ndarray]:
    ok, frame = cap.read()
    if not ok or frame is None:
        return None
    return frame


def run_attendance(
    section_id: int,
    grace_minutes: int = 10,
    match_threshold: float = 0.55,
    camera_index: int = 0
):
    cfg = CameraConfiguration(camera_index=camera_index)

    fps_meter = FPSMeter()
    retries = 0
    max_retries = 5

    detector = InsightFaceDetector(ctx_id=-1, det_size=(640, 640))
    matcher = SupabaseMatcher()
    attendance = AttendanceManager()

    section = attendance.get_section(section_id)
    if section is None:
        raise RuntimeError(f"Could not find class section with section_id={section_id}")

    print(f"[INFO] Attendance started for section_id={section.section_id}")
    print(f"[INFO] class_id={section.class_id} date={section.section_date} start={section.start_time} end={section.end_time}")

    # helps avoid hammering DB repeatedly for the same student every frame
    recent_seen: dict[int, float] = {}
    cooldown_seconds = 10.0

    cap = None
    try:
        cap = open_camera(cfg)

        while True:
            frame = read_frame(cap)

            if frame is None:
                retries += 1
                print(f"[WARN] Frame read failed ({retries}/{max_retries}). Reopening camera...")
                if cap is not None:
                    cap.release()
                time.sleep(0.2)

                if retries > max_retries:
                    raise RuntimeError("Failed to read too many times from camera.")

                cap = open_camera(cfg)
                continue

            retries = 0
            fps = fps_meter.update()

            faces, det_ms = detector.detect(frame)
            frame = draw_faces_overlay(frame, faces)

            best = face_selection(faces)

            attendance_text = "NO FACE"

            if best is not None and best.normed_embedding is not None:
                match = matcher.match_embedding(
                    best.normed_embedding,
                    threshold=match_threshold,
                    match_count=1
                )

                if match is not None:
                    now_ts = time.time()
                    last_seen = recent_seen.get(match.student_id, 0.0)

                    if now_ts - last_seen >= cooldown_seconds:
                        result = attendance.log_attendance(
                            student_id=int(match.student_id),
                            section=section,
                            confidence_score=match.score,
                            grace_minutes=grace_minutes
                        )

                        recent_seen[match.student_id] = now_ts

                        if result.logged:
                            attendance_text = (
                                f"LOGGED {match.first_name} {match.last_name} "
                                f"{result.status} ({match.score:.3f})"
                            )
                            print(
                                f"[INFO] Logged attendance for {match.first_name} {match.last_name} "
                                f"status={result.status} score={match.score:.3f}"
                            )
                        else:
                            attendance_text = (
                                f"{match.first_name} {match.last_name} "
                                f"{result.reason} ({match.score:.3f})"
                            )
                    else:
                        attendance_text = (
                            f"MATCH {match.first_name} {match.last_name} "
                            f"cooldown ({match.score:.3f})"
                        )
                else:
                    attendance_text = "UNKNOWN"

            display = create_overlay(
                frame,
                fps,
                text=(
                    f"section={section.section_id} | "
                    f"faces={len(faces)} | "
                    f"det={det_ms:.1f}ms | "
                    f"{attendance_text}"
                )
            )

            cv2.imshow(cfg.debug_window, display)

            key = cv2.waitKey(1) & 0xFF
            if key == ord("q"):
                break

    finally:
        if cap is not None:
            cap.release()
        cv2.destroyAllWindows()


if __name__ == "__main__":
    section_id = int(input("Section ID: ").strip())
    grace_minutes = int(input("Grace period in minutes (default 10): ").strip() or "10")
    match_threshold = float(input("Match threshold (default 0.55): ").strip() or "0.55")

    run_attendance(
        section_id=section_id,
        grace_minutes=grace_minutes,
        match_threshold=match_threshold,
        camera_index=0
    )