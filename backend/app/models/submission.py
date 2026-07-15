from sqlalchemy import Column, Integer, String, ForeignKey, DateTime, Enum
from sqlalchemy.orm import relationship
from datetime import datetime
from app.database.db_manager import Base
import enum

class SubmissionStatus(enum.Enum):
    PENDING = "pending"
    PROCESSING = "processing"
    COMPLETED = "completed"
    FAILED = "failed"

class Submission(Base):
    __tablename__ = "submissions"
    
    id = Column(Integer, primary_key=True, index=True)
    exam_id = Column(Integer, ForeignKey("exams.id"))
    student_id = Column(Integer, ForeignKey("students.id"))
    answer_sheet_path = Column(String(255))
    status = Column(Enum(SubmissionStatus), default=SubmissionStatus.PENDING)
    processed_at = Column(DateTime)
    
    # Relationships
    exam = relationship("Exam", back_populates="submissions")
    student = relationship("Student")
    scores = relationship("Score", back_populates="submission")