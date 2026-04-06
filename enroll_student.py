import os
import time
import numpy as np
import cv2
from supabase import create_client, Client
from dotenv import load_dotenv

from AI_Facial_Detection.face_detector import InsightFaceDetector
from AI_Facial_Detection.selection import face_selection
from AI_Facial_Detection.overlay import draw_faces_overlay

# ----------------------------
# Setting up Supabase client
# ----------------------------
load_dotenv()
SUPABASE_URL = os.environ["SUPABASE_URL"]
SUPABASE_SERVICE_ROLE_KEY = os.environ["SUPABASE_SERVICE_ROLE_KEY"]
supabase: Client = create_client(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY)

# ----------------------------
# normalize vector (helps computation time)
# ----------------------------
def l2_normalize(v: np.ndarray, eps: float = 1e-9) -> np.ndarray:
    v = v.astype(np.float32)
    return v / (np.linalg.norm(v) + eps)

# ----------------------------
# Enrollment function
# ----------------------------
def enroll(student_id: str, first_name: str, last_name: str, camera_index: int = 0):
    cap = cv2.VideoCapture(camera_index, cv2.CAP_DSHOW)
    if not cap.isOpened():
        raise RuntimeError("camera could not be opened")
    
    detector = InsightFaceDetector(ctx_id=-1, det_size=(640, 640))

    samples: list[np.ndarray] = []
    target_samples = 10

    print("\nEnrollment controls:")
    print("Press c to capture sample embedding")
    print("Press u to upload once you have enough samples")
    print("Press q to quit\n")

    try:
        while True:
            ok, frame = cap.read()
            if not ok or frame is None:
                continue

            faces, det_ms = detector.detect(frame)
            frame_vis = draw_faces_overlay(frame, faces)

            best = face_selection(faces)
            status = f"samples={len(samples)}/{target_samples} | det={det_ms:.1f}ms"
            if best is None or best.normed_embedding is None:
                status += " | no-face"
            else:
                status += " | face-ok"

            cv2.putText(
                frame_vis, 
                status, 
                (10, 90), 
                cv2.FONT_HERSHEY_SIMPLEX, 
                0.7, 
                (0,255,0), 
                2, 
                cv2.LINE_AA
            )

            cv2.putText(
                frame_vis, 
                "c=capture  u=upload  q=quit", 
                (10, 120), 
                cv2.FONT_HERSHEY_SIMPLEX, 
                0.7, 
                (0,255,0), 
                2, 
                cv2.LINE_AA
            )

            cv2.imshow("Enrollment", frame_vis)
            key = cv2.waitKey(1) & 0xff

            if key == ord("q"):
                break

            if key == ord("c"):
                if best is not None and best.normed_embedding is not None:
                    samples.append(best.normed_embedding.copy())
                    print(f"Captured sample {len(samples)}/{target_samples}")
                else:
                    print("No face embedding available to capture")
            
            if key == ord("u"):
                if len(samples) < 3:
                    print("Capture at least 3 samples before uploading (10 recommended).")
                    continue

                # Average and renormalize for better overall embedding
                mean_emb = np.mean(np.vstack(samples), axis=0)
                mean_emb = l2_normalize(mean_emb)

                # Supabase expects a JSON list for data
                info_out = {
                    "student_id": student_id,
                    "first_name": first_name,
                    "last_name": last_name,
                    "face_encoding": mean_emb.tolist(),
                }

                # Insert data into students table
                resp = (
                    supabase.table("students")
                    .upsert(info_out, on_conflict="student_id")
                    .execute()
                )

                # checking to make sure it worked
                if getattr(resp, "data", None) is None:
                    raise RuntimeError(f"Upload failed: {info_out}")

                print(f"Uploaded enrollment for {student_id} ({first_name} {last_name})")
                break
    finally:
        cap.release()
        cv2.destroyAllWindows()

if __name__ == "__main__":
    student_id = input("Student ID: ").strip()
    first_name = input("First name: ").strip()
    last_name = input("Last name: ").strip()

    enroll(student_id, first_name, last_name, camera_index=0)