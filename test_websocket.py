#!/usr/bin/env python3
"""
WebSocket Test Client for Face-IT Dashboard
Simulates face detection events to test dashboard without needing the full backend
"""

import asyncio
import json
import websockets
import sys
from datetime import datetime

# Configuration
WS_SERVER = "ws://localhost:8765"

# Sample test data
SAMPLE_STUDENTS = [
    {"student_id": 1, "first_name": "Alice", "last_name": "Johnson"},
    {"student_id": 2, "first_name": "Bob", "last_name": "Smith"},
    {"student_id": 3, "first_name": "Charlie", "last_name": "Brown"},
    {"student_id": 4, "first_name": "Diana", "last_name": "Prince"},
    {"student_id": 5, "first_name": "Evan", "last_name": "Davis"},
]

async def send_face_detected_event(ws, student_index=0, confidence=0.95, status="on_time"):
    """Send a face_detected event"""
    student = SAMPLE_STUDENTS[student_index % len(SAMPLE_STUDENTS)]
    
    event = {
        "type": "face_detected",
        "logged": True,
        "student_id": student["student_id"],
        "first_name": student["first_name"],
        "last_name": student["last_name"],
        "confidence": confidence,
        "status": status
    }
    
    print(f"[SEND] {student['first_name']} {student['last_name']} ({confidence*100:.0f}%) - {status}")
    await ws.send(json.dumps(event))


async def send_frame_update(ws, fps=30):
    """Send a frame_update event (FPS counter)"""
    event = {
        "type": "frame_update",
        "fps": fps
    }
    await ws.send(json.dumps(event))


async def send_error_event(ws, message="Unknown error occurred"):
    """Send an error event"""
    event = {
        "type": "error",
        "message": message
    }
    print(f"[ERROR] {message}")
    await ws.send(json.dumps(event))


async def test_basic_connection():
    """Test 1: Basic WebSocket connection"""
    print("\n" + "="*50)
    print("TEST 1: Basic WebSocket Connection")
    print("="*50)
    
    try:
        async with websockets.connect(WS_SERVER) as ws:
            print(f"✓ Connected to {WS_SERVER}")
            
            # Send ping, wait for response
            await ws.send(json.dumps({"type": "ping"}))
            response = await asyncio.wait_for(ws.recv(), timeout=2)
            print(f"✓ Received response: {response}")
            
    except asyncio.TimeoutError:
        print("✗ Timeout waiting for response (server may not echo)")
    except Exception as e:
        print(f"✗ Failed to connect: {e}")
        return False
    
    return True


async def test_single_detection():
    """Test 2: Send single face detection"""
    print("\n" + "="*50)
    print("TEST 2: Single Face Detection Event")
    print("="*50)
    
    try:
        async with websockets.connect(WS_SERVER) as ws:
            print("✓ Connected")
            await send_face_detected_event(ws, student_index=0, confidence=0.92, status="on_time")
            await asyncio.sleep(1)
            print("✓ Event sent successfully")
            
    except Exception as e:
        print(f"✗ Failed: {e}")
        return False
    
    return True


async def test_multiple_detections():
    """Test 3: Send multiple detections (simulating a class)"""
    print("\n" + "="*50)
    print("TEST 3: Multiple Face Detections")
    print("="*50)
    
    try:
        async with websockets.connect(WS_SERVER) as ws:
            print(f"✓ Connected\n")
            
            # Simulate 5 students being detected
            for i in range(5):
                confidence = 0.85 + (i * 0.03)  # Vary confidence
                await send_face_detected_event(ws, student_index=i, confidence=confidence)
                await asyncio.sleep(0.5)
            
            print("\n✓ All detections sent")
            
    except Exception as e:
        print(f"✗ Failed: {e}")
        return False
    
    return True


async def test_continuous_stream():
    """Test 4: Continuous stream with FPS updates"""
    print("\n" + "="*50)
    print("TEST 4: Continuous Stream (30 seconds)")
    print("="*50)
    print("Simulating 30 FPS video stream...\n")
    
    try:
        async with websockets.connect(WS_SERVER) as ws:
            print("✓ Connected\n")
            
            start_time = datetime.now()
            frame_count = 0
            detection_count = 0
            
            while (datetime.now() - start_time).total_seconds() < 30:
                # Send FPS update every frame
                await send_frame_update(ws, fps=30)
                frame_count += 1
                
                # Random face detection every 2 seconds
                if frame_count % 60 == 0:
                    await send_face_detected_event(
                        ws,
                        student_index=detection_count % len(SAMPLE_STUDENTS),
                        confidence=0.88 + (frame_count % 10) * 0.01
                    )
                    detection_count += 1
                
                await asyncio.sleep(1/30)  # ~33ms per frame
            
            print(f"\n✓ Stream completed:")
            print(f"  - Frames sent: {frame_count}")
            print(f"  - Detections: {detection_count}")
            
    except Exception as e:
        print(f"✗ Failed: {e}")
        return False
    
    return True


async def test_error_handling():
    """Test 5: Error event handling"""
    print("\n" + "="*50)
    print("TEST 5: Error Event Handling")
    print("="*50)
    
    try:
        async with websockets.connect(WS_SERVER) as ws:
            print("✓ Connected\n")
            
            errors = [
                "GPU out of memory",
                "Camera feed lost",
                "Invalid frame format"
            ]
            
            for error_msg in errors:
                await send_error_event(ws, error_msg)
                await asyncio.sleep(0.5)
            
            print("\n✓ All error events sent")
            
    except Exception as e:
        print(f"✗ Failed: {e}")
        return False
    
    return True


async def test_interactive_mode():
    """Test 6: Interactive mode - send events manually"""
    print("\n" + "="*50)
    print("TEST 6: Interactive Mode")
    print("="*50)
    print("Commands:")
    print("  1 - Send face detection")
    print("  2 - Send FPS update")
    print("  3 - Send error")
    print("  q - Quit\n")
    
    try:
        async with websockets.connect(WS_SERVER) as ws:
            print("✓ Connected to server\n")
            
            loop = asyncio.get_event_loop()
            
            while True:
                cmd = await loop.run_in_executor(None, input, "Enter command: ")
                
                if cmd == "1":
                    idx = await loop.run_in_executor(None, input, "Student index (0-4): ")
                    conf = await loop.run_in_executor(None, input, "Confidence (0-1): ")
                    try:
                        await send_face_detected_event(ws, int(idx), float(conf))
                    except:
                        print("✗ Invalid input")
                        
                elif cmd == "2":
                    await send_frame_update(ws, 30)
                    print("[SEND] FPS update")
                    
                elif cmd == "3":
                    msg = await loop.run_in_executor(None, input, "Error message: ")
                    await send_error_event(ws, msg)
                    
                elif cmd == "q":
                    break
                else:
                    print("✗ Unknown command")
                
                await asyncio.sleep(0.1)
            
            print("\n✓ Interactive mode closed")
            
    except Exception as e:
        print(f"✗ Failed: {e}")
        return False
    
    return True


async def run_all_tests():
    """Run all tests in sequence"""
    print("\n" + "█"*50)
    print("  FACE-IT DASHBOARD WEBSOCKET TEST SUITE")
    print("█"*50)
    print(f"Server: {WS_SERVER}\n")
    
    results = []
    
    # Run tests
    results.append(("Connection", await test_basic_connection()))
    results.append(("Single Detection", await test_single_detection()))
    results.append(("Multiple Detections", await test_multiple_detections()))
    results.append(("Continuous Stream", await test_continuous_stream()))
    results.append(("Error Handling", await test_error_handling()))
    
    # Summary
    print("\n" + "="*50)
    print("TEST SUMMARY")
    print("="*50)
    
    for test_name, passed in results:
        status = "✓ PASS" if passed else "✗ FAIL"
        print(f"{status:8} {test_name}")
    
    passed_count = sum(1 for _, p in results if p)
    total_count = len(results)
    
    print(f"\nTotal: {passed_count}/{total_count} passed")
    print("="*50 + "\n")


def main():
    if len(sys.argv) > 1:
        test_mode = sys.argv[1]
    else:
        print("\nUsage: python test_websocket.py [mode]")
        print("\nModes:")
        print("  all         - Run all tests (default)")
        print("  connection  - Test connection only")
        print("  detection   - Test single detection")
        print("  multiple    - Test multiple detections")
        print("  stream      - Test continuous stream")
        print("  errors      - Test error handling")
        print("  interactive - Interactive test mode\n")
        test_mode = "all"
    
    try:
        if test_mode == "connection":
            asyncio.run(test_basic_connection())
        elif test_mode == "detection":
            asyncio.run(test_single_detection())
        elif test_mode == "multiple":
            asyncio.run(test_multiple_detections())
        elif test_mode == "stream":
            asyncio.run(test_continuous_stream())
        elif test_mode == "errors":
            asyncio.run(test_error_handling())
        elif test_mode == "interactive":
            asyncio.run(test_interactive_mode())
        else:  # all
            asyncio.run(run_all_tests())
    except KeyboardInterrupt:
        print("\n\n✗ Test interrupted by user")
    except Exception as e:
        print(f"\n✗ Test failed: {e}")


if __name__ == "__main__":
    main()