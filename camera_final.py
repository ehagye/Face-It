#!/usr/bin/env python3
"""
Face-IT Camera for CODD (No Display)
- Detects faces from camera
- Logs attendance to Supabase with correct schema
- No display window needed
"""

import cv2
import argparse
from pathlib import Path
import sys
from dotenv import load_dotenv

load_dotenv()

sys.path.insert(0, str(Path(__file__).parent / "AI_Facial_Detection"))

from face_detector import InsightFaceDetector
from supabase_matcher import SupabaseMatcher
from attendance_manager import AttendanceManager


class CameraAttendance:
    def __init__(self, class_id=4, camera_id=0):
        self.class_id = class_id
        self.camera_id = camera_id
        
        print("[INIT] Loading face detection model...")
        self.detector = InsightFaceDetector(ctx_id=-1, det_size=(640, 640))
        self.matcher = SupabaseMatcher()
        self.attendance_manager = AttendanceManager()
        
        self.cap = cv2.VideoCapture(camera_id)
        if not self.cap.isOpened():
            raise RuntimeError(f"Cannot open camera {camera_id}")
        
        print(f"[CAMERA] Opened camera {camera_id}")
        self.detected_students = set()

    def run(self):
        print("\n" + "="*50)
        print("Face-IT Attendance Logger")
        print("="*50)
        print(f"Class ID: {self.class_id}")
        print(f"Camera: {self.camera_id}")
        print("\nDetecting faces... Press Ctrl+C to quit")
        print("="*50 + "\n")
        
        frame_count = 0
        
        try:
            while True:
                ret, frame = self.cap.read()
                if not ret:
                    print("[ERROR] Failed to read frame")
                    break
                
                # Run detection every 3 frames
                if frame_count % 3 == 0:
                    try:
                        faces, elapsed_ms = self.detector.detect(frame)
                        
                        for face in faces:
                            try:
                                # Match face
                                match = self.matcher.match_embedding(
                                    face.normed_embedding,
                                    threshold=0.55
                                )
                                
                                if match:
                                    student_id = int(match.student_id)
                                    first_name = match.first_name
                                    last_name = match.last_name
                                    confidence = match.score
                                    
                                    # Log only once per student per session
                                    if student_id not in self.detected_students:
                                        self.log_attendance(
                                            student_id,
                                            first_name,
                                            last_name,
                                            confidence
                                        )
                                        self.detected_students.add(student_id)
                            
                            except Exception as e:
                                print(f"[WARN] Failed to process face: {e}")
                    
                    except Exception as e:
                        print(f"[WARN] Detection error: {e}")
                
                frame_count += 1
        
        except KeyboardInterrupt:
            print("\n[INFO] Shutting down...")
        
        finally:
            self.cleanup()

    def log_attendance(self, student_id, first_name, last_name, confidence):
        """Log attendance to Supabase"""
        try:
            from datetime import datetime
            
            student_name = f"{first_name} {last_name}"
            
            # Correct schema for attendance_logs
            payload = {
                "student_id": student_id,
                "class_id": self.class_id,  # numeric class_id
                "detected_at": datetime.now().isoformat(),
                "status": "on_time",
                "confidence_score": float(confidence),
                "student_name": student_name,  # include student_name
            }
            
            response = self.attendance_manager.supabase.table("attendance_logs").insert(payload).execute()
            
            if response.data:
                print(f"[LOG] {student_name} (confidence: {confidence:.2f})")
            else:
                print(f"[ERROR] Failed to log {student_name}")
        
        except Exception as e:
            print(f"[ERROR] Failed to log attendance: {e}")

    def cleanup(self):
        self.cap.release()
        print("[INFO] Camera closed")


def main():
    parser = argparse.ArgumentParser(description="Face-IT Attendance Logger")
    parser.add_argument("--class-id", type=int, default=4, help="Class ID (default: 4)")
    parser.add_argument("--camera", type=int, default=0, help="Camera index (default: 0)")
    
    args = parser.parse_args()
    
    try:
        camera = CameraAttendance(
            class_id=args.class_id,
            camera_id=args.camera
        )
        camera.run()
    except Exception as e:
        print(f"[FATAL] {e}")
        import traceback
        traceback.print_exc()


if __name__ == "__main__":
    main()