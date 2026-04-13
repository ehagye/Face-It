from __future__ import annotations

import os
from dataclasses import dataclass
from datetime import datetime, timedelta, date, time
from typing import Optional

from dotenv import load_dotenv
from supabase import create_client, Client

@dataclass(frozen=True)
class ClassSection:
    section_id: int
    class_id: int
    section_date: date
    start_time: time
    end_time: time
    location: str | None = None


@dataclass(frozen=True)
class AttendanceResult:
    logged: bool
    reason: str
    status: str | None = None

class AttendanceManager:
    def __init__(self):
        load_dotenv()

        supabase_url = os.environ["SUPABASE_URL"]
        supabase_service_role_key = os.environ["SUPABASE_SERVICE_ROLE_KEY"]

        self.supabase: Client = create_client(supabase_url, supabase_service_role_key)
    
    def get_section(self, section_id: int) -> Optional[ClassSection]:
        response = (
            self.supabase.table("class_sections")
            .select("section_id, class_id, section_date, start_time, end_time, location")
            .eq("section_id", section_id)
            .limit(1)
            .execute()
        )

        rows = response.data or []
        if not rows:
            return None
        
        row = rows[0]
        return ClassSection(
            section_id = row["section_id"],
            class_id = row["class_id"],
            section_date=date.fromisoformat(row["section_date"]),
            start_time=time.fromisoformat(row["start_time"]),
            end_time=time.fromisoformat(row["end_time"]),
            location=row.get("location"),
        )
    
    def parse_section_datetimes(self, section: ClassSection) -> tuple [datetime, datetime]:
        start_dt = datetime.combine(section.section_date, section.start_time)
        end_dt = datetime.combine(section.section_date, section.end_time)
        return start_dt, end_dt
    
    def compute_status(
            self,
            section: ClassSection,
            detected_at: datetime,
            grace_minutes: int = 10
    ) -> Optional[str]:
        start_dt, end_dt = self.parse_section_datetimes(section)
        grace_end = start_dt + timedelta(minutes=grace_minutes)

        if detected_at > end_dt:
            return None
        
        if detected_at < start_dt:
            return "early"
        
        if detected_at <= grace_end:
            return "on_time"
        
        return "late"

    def attendance_exists(self, student_id: int, section_id: int) -> bool:
        response = (
            self.supabase.table("attendance_logs")
            .select("log_id")
            .eq("student_id", student_id)
            .eq("section_id", section_id)
            .limit(1)
            .execute()
        )

        rows = response.data or []
        return len(rows) > 0

    def log_attendance(
        self,
        student_id: int,
        section: ClassSection,
        confidence_score: float,
        grace_minutes: int = 10,
        detected_at: datetime | None = None
    ) -> AttendanceResult:
        now = detected_at or datetime.now()

        status = self.compute_status(section, now, grace_minutes=grace_minutes)
        if status is None:
            return AttendanceResult(
                logged=False,
                reason="ignored_after_end"
            )

        if self.attendance_exists(student_id, section.section_id):
            return AttendanceResult(
                logged=False,
                reason="already_logged",
                status=status
            )

        payload = {
            "student_id": student_id,
            "class_id": section.class_id,
            "detected_at": now.isoformat(),
            "status": status,
            "confidence_score": confidence_score,
            "section_id": section.section_id,
        }

        response = (
            self.supabase.table("attendance_logs")
            .insert(payload)
            .execute()
        )

        if not getattr(response, "data", None):
            return AttendanceResult(
                logged=False,
                reason="insert_failed",
                status=status
            )

        return AttendanceResult(
            logged=True,
            reason="logged",
            status=status
        )

