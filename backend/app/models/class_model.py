from sqlalchemy import Column, Integer, String
from sqlalchemy.orm import relationship
from app.database.db_manager import Base

class Class(Base):
    __tablename__ = "classes"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(100), nullable=False)
    course_name = Column(String(100), nullable=False)
    
    # Relationships
    students = relationship("Student", back_populates="class_ref")
    exams = relationship("Exam", back_populates="class_ref")