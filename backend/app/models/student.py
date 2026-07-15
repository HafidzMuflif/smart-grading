from sqlalchemy import Column, Integer, String, ForeignKey
from sqlalchemy.orm import relationship
from app.database.db_manager import Base

class Student(Base):
    __tablename__ = "students"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(100), nullable=False)
    nim = Column(String(20), unique=True, nullable=False, index=True)
    class_id = Column(Integer, ForeignKey("classes.id"))
    
    # Relationship
    class_ref = relationship("Class", back_populates="students")