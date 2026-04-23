#!/usr/bin/env python3
import asyncio
import json
import websockets
import os

class AttendanceServer:
    def __init__(self):
        self.clients = set()
    
    async def handle_client(self, websocket, path):
        self.clients.add(websocket)
        print(f"[WS] Client connected ({len(self.clients)} total)")
        
        await websocket.send(json.dumps({
            "type": "session_start",
            "class_id": 4,
            "class_name": "Artificial Intelligence"
        }))
        
        try:
            async for message in websocket:
                data = json.loads(message)
                await websocket.send(json.dumps(data))
        except:
            pass
        finally:
            self.clients.discard(websocket)
    
    async def start(self):
        port = int(os.environ.get("PORT", 10000))
        print(f"[SERVER] Starting on port {port}")
        async with websockets.serve(self.handle_client, "0.0.0.0", port):
            print(f"[SERVER] Listening...")
            await asyncio.Future()

if __name__ == "__main__":
    server = AttendanceServer()
    asyncio.run(server.start())