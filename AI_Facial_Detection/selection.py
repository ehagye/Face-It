from __future__ import annotations
from typing import Optional
from .face_detector import DetectedFace

def face_selection(faces: list[DetectedFace]) -> Optional[DetectedFace]:
    """
    Picks the most relevant face in frame to test the algorithm
    This is going to be done by selecting the face with the largest bounding box (the closest face)
    """

    if not faces:
        return None
    
    def area(face: DetectedFace) -> int:
        x1, y1, x2, y2 = face.bbox
        return max(0, x2 - x1) * max(0, y2 - y1)
    
    return max(faces, key=area)