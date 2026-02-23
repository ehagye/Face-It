from __future__ import annotations
from dataclasses import dataclass
from typing import List, Tuple
import numpy as np

@dataclass(frozen=True)
class DetectedFace:
    # This is a single detected face result
    bbox: Tuple[int, int, int, int] # (x1, y1, x2, y2)
    score: float                    # detection confidence score
    kps: np.ndarray | None = None   # (5,2) landmarks if available

# Implementing InsightFace dectector wrapper
import time
from insightface.app import FaceAnalysis

class InsightFaceDetector:
    """
    small wrapper around InsightFace for cleaning connection
    gives: detect(frame_bgr) -> List[DetectedFace]
    """

    def __init__(self, ctx_id: int = -1, det_size: tuple[int, int] = (640, 640)):
        """
        ctx_id:
            -1 = CPU
             0+ = GPU index
            
        det_size:
            detection input size (larger is better but costs speed)
        """
        self.app = FaceAnalysis(name="buffalo_l") # this is a common pretrained bundle
        self.app.prepare(ctx_id=ctx_id, det_size=det_size)
    
    def detect(self, frame_bgr: np.ndarray) -> tuple[list[DetectedFace], float]:
        """
        returns faces and elapsed_ms
        """
        t0 = time.perf_counter()
        faces = self.app.get(frame_bgr) # expects BGR numpy image
        elapsed_ms = (time.perf_counter() - t0) * 1000.0

        results: list[DetectedFace] = []
        for f in faces:
            # bbox is the float array created earlier
            x1, y1, x2, y2 = f.bbox.astype(int).tolist()
            score = float(getattr(f, "det_score", 0.0))

            # kps is (5,2) landmarks (eyes, nose, mouth corners) if present
            kps = getattr(f, "kps", None)
            results.append(DetectedFace(bbox=(x1, y1, x2, y2), score=score, kps=kps))
        
        return results, elapsed_ms
    
