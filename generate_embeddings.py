
"""
Runs AFTER process_enroll.php
Pulls uploaded face photos from Supabase Storage, runs them
through InsightFace to get 512-dim embeddings, averages them,
and writes the result back to the student's face_encoding column.

This is the same logic as enroll_student.py (capture → embed →
average → normalize → upsert) but reads from Storage instead
of a live webcam.

Usage:
    python generate_embeddings.py          # process all pending
    python generate_embeddings.py 12345    # process one student
"""

import os
import sys
import numpy as np
import cv2
from supabase import create_client, Client
from dotenv import load_dotenv

from AI_Facial_Detection.face_detector import InsightFaceDetector
from AI_Facial_Detection.selection import face_selection

# Supabase client setup (same as enroll_student.py)
load_dotenv()
SUPABASE_URL = os.environ["SUPABASE_URL"]
SUPABASE_SERVICE_ROLE_KEY = os.environ["SUPABASE_SERVICE_ROLE_KEY"]
supabase: Client = create_client(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY)

# Same normalization helper from enroll_student.py
def l2_normalize(v: np.ndarray, eps: float = 1e-9) -> np.ndarray:
    v = v.astype(np.float32)
    return v / (np.linalg.norm(v) + eps)


def download_image(storage_path: str) -> np.ndarray | None:
    """Download a JPEG from Supabase Storage and decode it into an OpenCV image."""
    try:
        data = supabase.storage.from_("faces").download(storage_path)
        arr = np.frombuffer(data, dtype=np.uint8)
        img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
        return img
    except Exception as e:
        print(f"  Failed to download {storage_path}: {e}")
        return None


def process_student(student_id: str, detector: InsightFaceDetector):
    """Generate and upload face embedding for one student."""
    print(f"\nProcessing student {student_id}...")

    # Fetch all photo paths for this student from the student_faces table
    resp = (
        supabase.table("student_faces")
        .select("photo_path")
        .eq("student_id", student_id)
        .execute()
    )
    paths = [row["photo_path"] for row in (resp.data or [])]

    if not paths:
        print(f"  No photos found for {student_id}, skipping.")
        return

    # Run each photo through InsightFace and collect embeddings
    embeddings = []
    for path in paths:
        img = download_image(path)
        if img is None:
            continue

        faces, _ = detector.detect(img)
        best = face_selection(faces)

        if best is not None and best.normed_embedding is not None:
            embeddings.append(best.normed_embedding.copy())
            print(f"  Got embedding from {path}")
        else:
            print(f"  No face detected in {path}")

    if len(embeddings) < 3:
        print(f"  Only {len(embeddings)} usable embeddings — need at least 3. Skipping.")
        return

    # Average and normalize, same as enroll_student.py
    mean_emb = np.mean(np.vstack(embeddings), axis=0)
    mean_emb = l2_normalize(mean_emb)

    # Update the student row with the computed embedding
    supabase.table("students").update({
        "face_encoding": mean_emb.tolist()
    }).eq("student_id", student_id).execute()

    print(f"  Uploaded embedding for {student_id} ({len(embeddings)} samples averaged)")


def main():
    detector = InsightFaceDetector(ctx_id=-1, det_size=(640, 640))

    # If a student ID is passed as argument, process just that one
    if len(sys.argv) > 1:
        process_student(int(sys.argv[1]), detector)
    return

    # Otherwise find all students missing embeddings
    resp = (
        supabase.table("students")
        .select("student_id")
        .is_("face_encoding", "null")
        .execute()
    )
    pending = [row["student_id"] for row in (resp.data or [])]

    if not pending:
        print("No students pending embedding generation.")
        return

    print(f"Found {len(pending)} student(s) to process.")
    for sid in pending:
        process_student(sid, detector)

    print("\nDone.")


if __name__ == "__main__":
    main()