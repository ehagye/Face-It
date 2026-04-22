# importing needed libraries
import sys
import time
from dataclasses import dataclass
from typing import Optional, Tuple
import cv2
import numpy as np

# importing other folders
from AI_Facial_Detection.face_detector import InsightFaceDetector
from AI_Facial_Detection.overlay import draw_faces_overlay
from AI_Facial_Detection.similarity import cosine_similarity
from AI_Facial_Detection.selection import face_selection
from AI_Facial_Detection.supabase_matcher import SupabaseMatcher

print([l for l in cv2.getBuildInformation().splitlines() if "GUI:" in l][0])

# printing out environment versions
print("Python:", sys.version)
print("Opencv:", cv2.__version__)
print("Numpy:", np.__version__)

# camera settings saved all in one place
@dataclass
class CameraConfiguration:
    camera_index: int = 0
    width: int = 1280
    height: int = 720
    target_fps: int = 30
    debug_window: str = "Camera Test"

# Tracks the frames per second with smoothing
class FPSMeter:
    def __init__(self, smoothing: float = 0.9):
        self.smoothing = float(smoothing)
        self._last = None
        self._fps = 0.0

    def update(self) -> float:
        # get timestamp on first call
        now = time.time()
        if self._last is None:
            self._last = now
            return self._fps

        dt = now - self._last
        self._last = now
        
        # protect against dt=0
        inst = (1.0 / dt) if dt > 0 else 0.0

        # smoothing equation:
        # new = old * smoothing + inst*(1-smoothing)
        self._fps = self._fps * self.smoothing + inst * (1.0 - self.smoothing)
        return self._fps

# Draws the debug text on top of the image
def create_overlay(frame: np.ndarray, fps: float, text: str = "") -> np.ndarray:
    out = frame.copy() # for later ML use
    message = f"FPS: {fps:5.1f}"
    if text:
        message += f" | {text}"
    cv2.putText(
        out, 
        message, 
        (10,30),                   # (x,y)
        cv2.FONT_HERSHEY_SIMPLEX, 
        0.9,                       # scale
        (0, 255, 0),               # color
        2,                         # thickness
        cv2.LINE_AA                # anit-aliased for cleaner look
    )
    
    # quit hint
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

    # raise error if camera can't be opened
    if not cap.isOpened():
        raise RuntimeError(f"Could not open the camera on index {cfg.camera_index}")
    
    for i in range(30):
        ok, frame = cap.read()
        if ok and frame is not None:
            break
        time.sleep(0.05)
    else:
        cap.release()
        raise RuntimeError("Camera opened but no frames received (permission/busy/index/backend issue).")

    # requesting camera settings
    cap.set(cv2.CAP_PROP_FRAME_WIDTH, cfg.width)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, cfg.height)
    cap.set(cv2.CAP_PROP_FPS, cfg.target_fps)

    return cap

# attempts to read a single frame from the camera
def read_frame(cap: cv2.VideoCapture, cfg: CameraConfiguration) -> Optional[np.ndarray]:
    ok, frame = cap.read()

    # skips over bad frames
    if not ok or frame is None:
        return None
    
    return frame

def camera_test(cfg: CameraConfiguration, max_retries: int = 5):
    fps_meter = FPSMeter()
    retires = 0
    max_retries = 5

    cap = None
    try:
        # opening camera
        cap = open_camera(cfg)
        detector = InsightFaceDetector(ctx_id=-1, det_size=(640, 640))
        matcher = SupabaseMatcher()

        while True:
            frame = read_frame(cap, cfg)

            # If frame read failed try to reopen the camera
            if frame is None:
                retires += 1
                print(f"Frame could not be read ({retires}/{max_retries}). trying to reopen camera again")

                # a little buffer after reconnecting
                if cap is not None:
                    cap.release()
                time.sleep(0.2)

                # to many failures and it quits
                if retires > max_retries:
                    raise RuntimeError("Failed to read too many times.")
                cap = open_camera(cfg)
                continue

            retires = 0
            fps = fps_meter.update()

            faces, det_ms = detector.detect(frame)
            frame = draw_faces_overlay(frame, faces)

            # picking the best face to compute cosine similarity
            best = face_selection(faces)

            match_text = "NO FACE"

            if best is not None and best.normed_embedding is not None:
                match = matcher.match_embedding(best.normed_embedding, threshold=0.55, match_count=1)

                if match is not None:
                    match_text = f"MATCH={match.first_name} {match.last_name} ({match.score:.3f})"
                else:
                    match_text = "UNKNOWN"
            
            # creating the overlay
            display = create_overlay(
                frame,
                fps,
                text=f"{cfg.width}x{cfg.height} | faces={len(faces)} | det={det_ms:.1f}ms | {match_text}"
            )
            
            cv2.imshow(cfg.debug_window, display)

            # q to quit the camera
            key = cv2.waitKey(1) & 0xFF
            if key == ord("q"):
                break

    finally:
        if cap is not None:
            cap.release()
        cv2.destroyAllWindows()

cfg = CameraConfiguration(camera_index=0, width=1280, height=720, target_fps=30)
camera_test(cfg)