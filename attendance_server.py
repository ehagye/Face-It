"""
attendance_server.py - WebSocket server for real-time face detection (FIXED)
"""
import os
os.environ['DISPLAY'] = ''
os.environ['QT_QPA_PLATFORM'] = 'offscreen'

import argparse
import asyncio
import json
import time
import cv2
import threading
from datetime import datetime

from AI_Facial_Detection.face_detector import InsightFaceDetector
from AI_Facial_Detection.supabase_matcher import SupabaseMatcher
from AI_Facial_Detection.attendance_manager import AttendanceManager


class AttendanceServer:
    def __init__(self, class_id: int, grace_minutes: int = 15, 
                 match_threshold: float = 0.55, camera_index: int = 0):
        self.class_id = class_id
        self.grace_minutes = grace_minutes
        self.match_threshold = match_threshold
        self.camera_index = camera_index
        
        print("Loading InsightFace model...")
        self.detector = InsightFaceDetector(ctx_id=-1, det_size=(640, 640))
        self.matcher = SupabaseMatcher()
        self.attendance_manager = AttendanceManager()
        
        self.class_info = self.attendance_manager.get_class(class_id)
        if self.class_info is None:
            raise RuntimeError(f"Class {class_id} not found")
        
        print(f"[CLASS] {self.class_info.get('class_name')} (ID: {class_id})")
        
        self.logged_this_session = set()
        self.clients = set()
        self.fps = 0.0
        self.frame_count = 0
        self.running = True
        self.loop = None
    
    async def broadcast(self, event: dict):
        """Broadcast event to all connected clients"""
        if self.clients:
            message = json.dumps(event)
            # Create copy of clients set to avoid "Set changed during iteration" error
            clients_copy = list(self.clients)
            disconnected = []
            
            for client in clients_copy:
                try:
                    await client.send(message)
                except Exception as e:
                    print(f"[WS] Broadcast error: {e}")
                    disconnected.append(client)
            
            # Clean up disconnected clients
            for client in disconnected:
                self.clients.discard(client)
    
    async def handle_client(self, websocket):
        """Handle incoming WebSocket client connection"""
        self.clients.add(websocket)
        print(f"[WS] Client connected ({len(self.clients)} total)")
        
        try:
            # Send session start message
            await websocket.send(json.dumps({
                "type": "session_start",
                "class_id": self.class_id,
                "class_name": self.class_info.get('class_name'),
                "scheduled_start_time": str(self.class_info.get('scheduled_start_time', ''))
            }))
            
            # Listen for client messages
            async for message in websocket:
                try:
                    data = json.loads(message)
                    if data.get('action') == 'set_threshold':
                        self.match_threshold = data.get('threshold', 0.55)
                        print(f"[THRESHOLD] Set to {self.match_threshold:.2f}")
                except json.JSONDecodeError:
                    print(f"[WS] Invalid JSON received")
                    
        except Exception as e:
            print(f"[WS] Client error: {e}")
        finally:
            self.clients.discard(websocket)
            print(f"[WS] Client disconnected ({len(self.clients)} remain)")
    
    def run_camera_thread(self):
        """Run camera detection in separate thread"""
        cap = cv2.VideoCapture(self.camera_index)
        if not cap.isOpened():
            print(f"[ERROR] Could not open camera {self.camera_index}")
            return
        
        print(f"[CAMERA] Opened camera {self.camera_index}")
        last_fps_time = time.time()
        
        try:
            while self.running:
                ret, frame = cap.read()
                if not ret or frame is None:
                    time.sleep(0.01)
                    continue
                
                faces, det_ms = self.detector.detect(frame)
                
                for face in faces:
                    if face.normed_embedding is None:
                        continue
                    
                    result = self.matcher.match_embedding(
                        face.normed_embedding, 
                        threshold=self.match_threshold
                    )
                    
                    if result:
                        student_id = result.student_id
                        
                        if student_id not in self.logged_this_session:
                            attendance_result = self.attendance_manager.log_attendance(
                                student_id,
                                self.class_id,
                                result.score,
                                self.grace_minutes
                            )
                            
                            if attendance_result and attendance_result.logged:
                                self.logged_this_session.add(student_id)
                                
                                # Use create_task instead of run_coroutine_threadsafe
                                asyncio.run_coroutine_threadsafe(
                                    self.broadcast({
                                        "type": "face_detected",
                                        "student_id": student_id,
                                        "first_name": result.first_name,
                                        "last_name": result.last_name,
                                        "confidence": result.score,
                                        "status": attendance_result.status,
                                        "logged": True,
                                        "timestamp": datetime.now().isoformat()
                                    }),
                                    self.loop
                                )
                                
                                print(f"[LOGGED] {result.first_name} {result.last_name}")
                
                self.frame_count += 1
                now = time.time()
                if now - last_fps_time > 1.0:
                    self.fps = self.frame_count / (now - last_fps_time)
                    self.frame_count = 0
                    last_fps_time = now
                    
                    asyncio.run_coroutine_threadsafe(
                        self.broadcast({
                            "type": "frame_update",
                            "fps": self.fps,
                            "faces_detected": len(faces),
                            "detection_ms": det_ms
                        }),
                        self.loop
                    )
                
                time.sleep(0.001)
        
        except Exception as e:
            print(f"[CAMERA] Error: {e}")
        finally:
            cap.release()
            print("[CAMERA] Released")
    
    async def start(self, host: str = "0.0.0.0", port: int = 8765):
        """Start the WebSocket server"""
        import os
        port = int(os.environ.get("PORT", port))
        
        import websockets
        
        # Get the running event loop
        self.loop = asyncio.get_event_loop()
        
        # Start camera thread
        camera_thread = threading.Thread(target=self.run_camera_thread, daemon=True)
        camera_thread.start()
        print(f"[CAMERA] Thread started")
        
        # Start WebSocket server
        print(f"[SERVER] Starting WebSocket on ws://0.0.0.0:{port}")
        async with websockets.serve(self.handle_client, host, port):
            print(f"[SERVER] Listening for connections...")
            try:
                await asyncio.Future()  # Run forever
            except KeyboardInterrupt:
                print("\n[SERVER] Shutting down...")
                self.running = False


async def main():
    """Main entry point"""
    parser = argparse.ArgumentParser()
    parser.add_argument("--class-id", type=int, required=True)
    parser.add_argument("--grace-minutes", type=int, default=15)
    parser.add_argument("--threshold", type=float, default=0.55)
    parser.add_argument("--camera", type=int, default=0)
    parser.add_argument("--host", default="0.0.0.0")
    parser.add_argument("--port", type=int, default=8765)
    
    args = parser.parse_args()
    
    try:
        server = AttendanceServer(
            class_id=args.class_id,
            grace_minutes=args.grace_minutes,
            match_threshold=args.threshold,
            camera_index=args.camera
        )
        
        await server.start(host=args.host, port=args.port)
    except KeyboardInterrupt:
        print("\n[SERVER] Interrupted by user")
    except Exception as e:
        print(f"\n[SERVER] Fatal error: {e}")
        import traceback
        traceback.print_exc()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\nShutdown complete")