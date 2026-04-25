#!/usr/bin/env python3
"""
Attendance Server for Face-IT
Receives face detection events from camera_final.py via WebSocket
Logs them to Supabase attendance_logs table
"""

import asyncio
import websockets
import json
import os
from datetime import datetime
from dotenv import load_dotenv

load_dotenv()

from AI_Facial_Detection.attendance_manager import AttendanceManager
from AI_Facial_Detection.supabase_matcher import SupabaseMatcher


class AttendanceServer:
    def __init__(self, class_id=4, port=8765):
        self.class_id = class_id
        self.port = port
        self.attendance_manager = AttendanceManager()
        self.matcher = SupabaseMatcher()
        self.clients = set()
        self.logged_students = set()  # Track who we've already logged this session
        
        print(f"[SERVER] Initialized for class_id={class_id}")

    async def handle_client(self, websocket):
        """Handle incoming WebSocket connection"""
        self.clients.add(websocket)
        print(f"[SERVER] New connection. Total: {len(self.clients)}")
        
        try:
            async for message in websocket:
                await self.process_message(json.loads(message), websocket)
        except websockets.exceptions.ConnectionClosed:
            print(f"[SERVER] Connection closed. Total: {len(self.clients) - 1}")
        finally:
            self.clients.remove(websocket)

    async def process_message(self, data, websocket):
        """Process incoming message from camera"""
        msg_type = data.get("type")
        
        if msg_type == "session_start":
            print(f"[SESSION] Started for class_id={data.get('class_id')}")
            # Reset logged students for new session
            self.logged_students.clear()
        
        elif msg_type == "student_detected":
            await self.handle_detection(data)
    
    async def handle_detection(self, data):
        """Log detected student to Supabase"""
        student_id = data.get("student_id")
        first_name = data.get("first_name")
        last_name = data.get("last_name")
        confidence = data.get("confidence")
        class_id = data.get("class_id", self.class_id)
        
        # Only log once per student per session
        if student_id in self.logged_students:
            print(f"[SKIP] {first_name} {last_name} already logged this session")
            return
        
        try:
            # Insert into attendance_logs
            payload = {
                "student_id": int(student_id),
                "class_id": int(class_id),
                "detected_at": datetime.now().isoformat(),
                "status": "on_time",  # Default status
                "confidence_score": float(confidence),
                "section_id": int(class_id),  # Using class_id as section_id for now
            }
            
            # Use Supabase client to insert
            response = self.attendance_manager.supabase.table("attendance_logs").insert(payload).execute()
            
            if response.data:
                self.logged_students.add(student_id)
                print(f"[LOG] {first_name} {last_name} (confidence: {confidence:.2f})")
                
                # Broadcast to all connected clients
                message = {
                    "type": "attendance_logged",
                    "student_id": student_id,
                    "first_name": first_name,
                    "last_name": last_name,
                    "confidence": confidence,
                    "timestamp": datetime.now().isoformat()
                }
                
                await self.broadcast(json.dumps(message))
            else:
                print(f"[ERROR] Failed to log {first_name} {last_name}")
        
        except Exception as e:
            print(f"[ERROR] {e}")

    async def broadcast(self, message):
        """Send message to all connected clients"""
        if self.clients:
            await asyncio.gather(
                *[client.send(message) for client in self.clients],
                return_exceptions=True
            )

    async def run(self):
        """Start the WebSocket server"""
        async with websockets.serve(self.handle_client, "localhost", self.port):
            print(f"[SERVER] Listening for connections on port {self.port}...")
            await asyncio.Future()  # Run forever


def main():
    import argparse
    
    parser = argparse.ArgumentParser(description="Face-IT Attendance Server")
    parser.add_argument("--class-id", type=int, default=4, help="Class ID (default: 4)")
    parser.add_argument("--port", type=int, default=8765, help="WebSocket port (default: 8765)")
    
    args = parser.parse_args()
    
    server = AttendanceServer(class_id=args.class_id, port=args.port)
    
    try:
        asyncio.run(server.run())
    except KeyboardInterrupt:
        print("\n[SERVER] Shutting down...")


if __name__ == "__main__":
    main()