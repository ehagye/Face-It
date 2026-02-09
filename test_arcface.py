import cv2
from insightface.app import FaceAnalysis

app = FaceAnalysis(name="buffalo_l")
app.prepare(ctx_id=0, det_size=(640, 640))

img = cv2.imread("test.jpg")  # put a clear face image here
faces = app.get(img)

print("Faces detected:", len(faces))

if faces:
    print("Embedding shape:", faces[0].embedding.shape)
