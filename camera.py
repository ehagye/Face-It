#!/usr/bin/env python3
"""
Face-IT Camera with Display
- Shows camera feed with detection overlays
- Logs attendance to Supabase in real-time
- Works around OpenCV headless issues
"""

import cv2
import argparse
from pathlib import Path
import sys
from dotenv import load_dotenv
import os

load_dotenv()

sys.path.insert(0, str(Path(__file__).parent / "AI_Facial_Detection"))

from face_detector import InsightFaceDetector
from supabase_matcher import SupabaseMatcher
from attendance_manager import AttendanceManager


class CameraWithDisplay:
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
        self.frame_count = 0

    def draw_detection(self, frame, face, match):
        """Draw face detection box and name on frame"""
        x1, y1, x2, y2 = face.bbox
        
        if match:
            # Green box for recognized students
            color = (0, 255, 0)
            label = f"{match.first_name} {match.last_name} ({match.score:.2f})"
        else:
            # Red box for unknown
            color = (0, 0, 255)
            label = "Unknown"
        
        cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
        cv2.putText(frame, label, (x1, y1 - 10),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2)
        
        return frame

    def run(self):
        print("\n" + "="*60)
        print("Face-IT Camera with Live Detection")
        print("="*60)
        print(f"Class ID: {self.class_id}")
        print(f"Camera: {self.camera_id}")
        print("\nPress 'q' to quit | 's' to save frame")
        print("="*60 + "\n")
        
        fps_counter = 0
        fps_timer = 0
        
        try:
            while True:
                ret, frame = self.cap.read()
                if not ret:
                    print("[ERROR] Failed to read frame")
                    break
                
                # Flip frame for selfie view
                frame = cv2.flip(frame, 1)
                
                # Run detection every 2 frames
                if self.frame_count % 2 == 0:
                    try:
                        faces, elapsed_ms = self.detector.detect(frame)
                        
                        for face in faces:
                            try:
                                match = self.matcher.match_embedding(
                                    face.normed_embedding,
                                    threshold=0.55
                                )
                                
                                frame = self.draw_detection(frame, face, match)
                                
                                if match:
                                    student_id = int(match.student_id)
                                    
                                    # Log once per student per session
                                    if student_id not in self.detected_students:
                                        self.log_attendance(
                                            student_id,
                                            match.first_name,
                                            match.last_name,
                                            match.score
                                        )
                                        self.detected_students.add(student_id)
                            
                            except Exception as e:
                                print(f"[WARN] Face processing error: {e}")
                    
                    except Exception as e:
                        print(f"[WARN] Detection error: {e}")
                
                # FPS counter
                fps_counter += 1
                import time
                if int(time.time()) != fps_timer:
                    fps_timer = int(time.time())
                    print(f"[FPS] {fps_counter} | Logged: {len(self.detected_students)}")
                    fps_counter = 0
                
                # Add stats to frame
                stats = f"Faces: {len(faces) if 'faces' in locals() else 0} | Logged: {len(self.detected_students)}"
                cv2.putText(frame, stats, (10, 30),
                           cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
                
                # Try to display frame
                try:
                    cv2.imshow("Face-IT Camera", frame)
                except Exception as e:
                    print(f"[WARN] Display not available: {e}")
                    print("[INFO] Continuing detection without display...")
                
                # Handle keyboard input
                key = cv2.waitKey(1) & 0xFF
                if key == ord('q'):
                    break
                elif key == ord('s'):
                    filename = f"capture_{self.frame_count}.jpg"
                    cv2.imwrite(filename, frame)
                    print(f"[SAVE] Saved {filename}")
                
                self.frame_count += 1
        
        except KeyboardInterrupt:
            print("\n[INFO] Shutting down...")
        
        finally:
            self.cleanup()

    def log_attendance(self, student_id, first_name, last_name, confidence):
        """Log attendance to Supabase"""
        try:
            from datetime import datetime
            
            student_name = f"{first_name} {last_name}"
            
            payload = {
                "student_id": student_id,
                "class_id": self.class_id,
                "detected_at": datetime.now().isoformat(),
                "status": "on_time",
                "confidence_score": float(confidence),
                "student_name": student_name,
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
        cv2.destroyAllWindows()
        print("[INFO] Camera closed")


def main():
    parser = argparse.ArgumentParser(description="Face-IT Camera with Display")
    parser.add_argument("--class-id", type=int, default=4, help="Class ID (default: 4)")
    parser.add_argument("--camera", type=int, default=0, help="Camera index (default: 0)")
    
    args = parser.parse_args()
    
    try:
        camera = CameraWithDisplay(
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