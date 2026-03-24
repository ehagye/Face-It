import numpy as np

def cosine_similarity(a: np.ndarray, b: np.ndarray, eps: float = 1e-9) -> float:
    """
    This function is going to calculate the cosine similarity score of incoming
    faces in order to see how confident the algorithm is in the match.

    Cosine similarity = (a·b) / (||a|| * ||b||)
    (With L2-normalization with becomes only (a·b))
    """

    a = a.astype(np.float32)
    b = b.astype(np.float32)

    denominator = (np.linalg.norm(a) * np.linalg.norm(b)) + eps
    return float(np.dot(a,b) / denominator)