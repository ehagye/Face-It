from __future__ import annotations

import os
from dataclasses import dataclass
from typing import Optional

import numpy as np
from python_dotenv import load_dotenv
from supabase import create_client, Client


@dataclass(frozen=True)
class StudentMatch:
    # this will be the best match returned from supabase
    student_id: str
    first_name: str
    last_name: str
    score: float

class SupabaseMatcher:
    # Handles the database part of matching of face embeddings against the students table

    def __init__(self):
        load_dotenv()

        supabase_url = os.environ["SUPABASE_URL"]
        supabase_service_role_key = os.environ["SUPABASE_SERVICE_ROLE_KEY"]

        self.supabase: Client = create_client(supabase_url, supabase_service_role_key)
    
    def match_embedding(
            self,
            normed_embedding: np.ndarray,
            threshold: float = 0.55,
            match_count: int = 1
    ) -> Optional[StudentMatch]:
        # sends one embedding to Supabase and asks for the best match
        # returns StudentMatch if a match is found above the threshold

        if normed_embedding is None:
            return None
        
        query_embedding = np.asarray(normed_embedding, dtype=np.float32).tolist()

        response = self.supabase.rpc(
            "match_students",
            {
                "query_embedding": query_embedding,
                "match_threshold": threshold,
                "match_count": match_count,
            }
        ).execute()

        data = getattr(response, "data", None)

        if not data:
            return None
        
        best = data[0]

        return StudentMatch(
            student_id=best["student_id"],
            first_name=best["first_name"],
            last_name=best["last_name"],
            score=float(best["score"]),
        )

