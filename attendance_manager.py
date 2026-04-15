"""
attendance_manager.py

Manages attendance logging against the simplified Supabase schema:

  classes:
    class_id (int4, PK)
    class_name (text)
    scheduled_start_time (time)
    professor_id (int4, FK → professors)

  attendance_logs:
    log_id (int4, PK, auto)
    student_id (int4)
    class_id (int4)
    detected_at (timestamptz)
    status (text)            — 'on_time', 'late', 'absent'
    confidence_score (numeric)

Grace period: 15 minutes past scheduled_start_time.
  detected ≤ start + 15 min  →  on_time
  detected > start + 15 min  →  late
"""

from __future__ import annotations

import os
from dataclasses import dataclass
from datetime import datetime, timedelta, date, time as dt_time
from typing import Optional

from dotenv import load_dotenv
from supabase import create_client, Client


@dataclass(frozen=True)
class AttendanceResult:
    logged: bool
    reason: str
    status: str | None = None


class AttendanceManager:
    def __init__(self):
        load_dotenv()
        url = os.environ["SUPABASE_URL"]
        key = os.environ["SUPABASE_SERVICE_ROLE_KEY"]
        self.supabase: Client = create_client(url, key)
        print("[DB] Supabase client initialized")

    # ------------------------------------------------------------------
    # Class lookup
    # ------------------------------------------------------------------
    def get_class(self, class_id: int) -> Optional[dict]:
        """
        Fetch a row from the classes table.
        Returns dict with keys: class_id, class_name, scheduled_start_time, professor_id
        """
        response = (
            self.supabase.table("classes")
            .select("class_id, class_name, scheduled_start_time, professor_id")
            .eq("class_id", class_id)
            .limit(1)
            .execute()
        )
        rows = response.data or []
        if not rows:
            return None

        row = rows[0]
        print(f"[DB] Found class: {row['class_name']} (ID: {row['class_id']})")
        return row

    # ------------------------------------------------------------------
    # Status computation
    # ------------------------------------------------------------------
    def compute_status(
        self,
        scheduled_start_time: str,
        detected_at: datetime,
        grace_minutes: int = 15,
    ) -> str:
        """
        Compare detected_at against the class's scheduled_start_time.
        scheduled_start_time is a time string like '09:00:00' from Supabase.
        """
        # Parse the scheduled start time
        start_time = dt_time.fromisoformat(scheduled_start_time)
        today = detected_at.date()
        start_dt = datetime.combine(today, start_time)

        # Make start_dt offset-aware if detected_at is offset-aware
        if detected_at.tzinfo is not None:
            start_dt = start_dt.replace(tzinfo=detected_at.tzinfo)

        grace_end = start_dt + timedelta(minutes=grace_minutes)

        if detected_at <= grace_end:
            return "on_time"
        return "late"

    # ------------------------------------------------------------------
    # Duplicate check
    # ------------------------------------------------------------------
    def attendance_exists(self, student_id: int, class_id: int, today: date) -> bool:
        """
        Check if this student already has an attendance log for this class today.
        Prevents duplicate entries within the same day.
        """
        day_start = datetime.combine(today, dt_time.min).isoformat()
        day_end = datetime.combine(today, dt_time.max).isoformat()

        response = (
            self.supabase.table("attendance_logs")
            .select("log_id")
            .eq("student_id", student_id)
            .eq("class_id", class_id)
            .gte("detected_at", day_start)
            .lte("detected_at", day_end)
            .limit(1)
            .execute()
        )
        rows = response.data or []
        return len(rows) > 0

    # ------------------------------------------------------------------
    # Log attendance
    # ------------------------------------------------------------------
    def log_attendance(
        self,
        student_id: int,
        class_id: int,
        confidence_score: float,
        grace_minutes: int = 15,
        detected_at: datetime | None = None,
        scheduled_start_time: str | None = None,
    ) -> AttendanceResult:
        """
        Insert an attendance record.

        If scheduled_start_time is provided, use it for status calculation.
        Otherwise, fetch it from the classes table.
        """
        now = detected_at or datetime.now()

        # Check for existing record today
        if self.attendance_exists(student_id, class_id, now.date()):
            return AttendanceResult(
                logged=False,
                reason="already_logged",
            )

        # Get scheduled_start_time if not provided
        if scheduled_start_time is None:
            class_info = self.get_class(class_id)
            if class_info is None:
                return AttendanceResult(logged=False, reason="class_not_found")
            scheduled_start_time = class_info["scheduled_start_time"]

        status = self.compute_status(scheduled_start_time, now, grace_minutes)

        payload = {
            "student_id": student_id,
            "class_id": class_id,
            "detected_at": now.isoformat(),
            "status": status,
            "confidence_score": float(confidence_score),
        }

        response = (
            self.supabase.table("attendance_logs")
            .insert(payload)
            .execute()
        )

        if not getattr(response, "data", None):
            return AttendanceResult(logged=False, reason="insert_failed", status=status)

        return AttendanceResult(logged=True, reason="logged", status=status)