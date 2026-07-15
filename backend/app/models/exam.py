from sqlalchemy import Column, Integer, String, ForeignKey, DateTime
from sqlalchemy.orm import relationship
from datetime import datetime
from app.database.db_manager import Base

class Exam(Base):
    __tablename__ = "exams"
    
    id = Column(Integer, primary_key=True, index=True)
    title = Column(String(200), nullable=False)
    class_id = Column(Integer, ForeignKey("classes.id"))
    answer_key_path = Column(String(255))
    rubric_path = Column(String(255))
    created_at = Column(DateTime, default=datetime.utcnow)
    
    # Relationships
    class_ref = relationship("Class", back_populates="exams")
    submissions = relationship("Submission", back_populates="exam")