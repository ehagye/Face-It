"""
live_attendance.py

Runs on the professor's local machine alongside the web dashboard.
Opens a local camera, runs InsightFace detection + Supabase matching
on each frame, and updates attendance records in real time.

Displays an OpenCV window with:
  - Green box = recognized student (match found in DB)
  - Red box   = unknown face (no match above threshold)

Usage:
    python live_attendance.py                  # default camera 0
    python live_attendance.py --camera 1       # USB camera
    python live_attendance.py --camera 1 --class-id 4
"""

import argparse
import time
import cv2
import numpy as np

from AI_Facial_Detection.face_detector import InsightFaceDetector
from AI_Facial_Detection.selection import face_selection
from AI_Facial_Detection.supabase_matcher import SupabaseMatcher


def draw_match_boxes(frame, faces, matcher, threshold=0.55):
    """
    Draw a simple colored bounding box on each detected face.
    Green = matched student, Red = unknown.
    Returns list of matched student IDs for attendance logging.
    """
    matched_ids = []

    for face in faces:
        x1, y1, x2, y2 = face.bbox

        if face.normed_embedding is not None:
            result = matcher.match_embedding(face.normed_embedding, threshold=threshold)
        else:
            result = None

        if result:
            # Green box + name for recognized students
            color = (0, 255, 0)
            label = f"{result.first_name} {result.last_name} ({result.score:.2f})"
            matched_ids.append(result.student_id)
        else:
            # Red box for unknown faces
            color = (0, 0, 255)
            label = "Unknown"

        cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
        cv2.putText(
            frame, label,
            (x1, y1 - 10),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.6, color, 2, cv2.LINE_AA
        )

    return matched_ids


def log_attendance(matcher, student_id, class_id=None):
    """
    Mark a student as present in the attendance table.
    Uses upsert so duplicate detections don't create duplicate rows.
    """
    import datetime

    record = {
        "student_id": student_id,
        "date": datetime.date.today().isoformat(),
        "status": "present",
    }
    if class_id:
        record["class_id"] = class_id

    try:
        matcher.supabase.table("attendance").upsert(
            record, on_conflict="student_id,date"
        ).execute()
    except Exception as e:
        print(f"  Attendance log failed for {student_id}: {e}")


def main():
    parser = argparse.ArgumentParser(description="Live attendance via face recognition")
    parser.add_argument("--camera", type=int, default=0, help="Camera index (default 0)")
    parser.add_argument("--threshold", type=float, default=0.55, help="Match threshold")
    parser.add_argument("--class-id", type=str, default=None, help="Class ID for attendance log")
    args = parser.parse_args()

    # --- Initialize detector and matcher ---
    print("Loading InsightFace model...")
    detector = InsightFaceDetector(ctx_id=-1, det_size=(640, 640))
    matcher = SupabaseMatcher()

    # --- Open camera ---
    cap = cv2.VideoCapture(args.camera, cv2.CAP_DSHOW)
    if not cap.isOpened():
        raise RuntimeError(f"Could not open camera {args.camera}")

    print(f"Camera {args.camera} opened. Press q to quit.\n")

    # Track which students have already been logged this session
    # so we don't spam Supabase with duplicate upserts every frame
    logged_this_session = set()

    try:
        while True:
            ok, frame = cap.read()
            if not ok or frame is None:
                continue

            # Run face detection
            faces, det_ms = detector.detect(frame)

            # Draw boxes and get matches
            matched_ids = draw_match_boxes(frame, faces, matcher, args.threshold)

            # Log attendance for any new matches
            for sid in matched_ids:
                if sid not in logged_this_session:
                    log_attendance(matcher, sid, args.class_id)
                    logged_this_session.add(sid)
                    print(f"  Marked {sid} as present")

            # Show frame count and detection time
            info = f"Faces: {len(faces)} | {det_ms:.0f}ms | Logged: {len(logged_this_session)}"
            cv2.putText(frame, info, (10, 30),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)

            cv2.imshow("Face-IT Live Attendance", frame)
            if cv2.waitKey(1) & 0xFF == ord("q"):
                break

    finally:
        cap.release()
        cv2.destroyAllWindows()
        print(f"\nSession ended. {len(logged_this_session)} student(s) logged.")


if __name__ == "__main__":
    main()