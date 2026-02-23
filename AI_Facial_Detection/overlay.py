import cv2
import numpy as np
from .face_detector import DetectedFace

def draw_faces_overlay(frame_bgr: np.ndarray, faces: list[DetectedFace]) -> np.ndarray:
    out = frame_bgr.copy()

    for face in faces:
        x1, y1, x2, y2 = face.bbox

        # Visual of the Bounding Box
        cv2.rectangle(out, (x1, y1), (x2, y2), (0, 255, 0), 2)

        # Confidence label
        label = f"{face.score:.2f}"
        cv2.putText(out, label, (x1, max(0, y1 - 8)),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2, cv2.LINE_AA)
        
        # Landmarks
        if face.kps is not None:
            for (lx, ly) in face.kps.astype(int):
                cv2.circle(out, (lx, ly), 2, (0, 255, 255), -1)
        
    return out
    