from sqlalchemy import Column, Integer, Float, String, ForeignKey, Text
from sqlalchemy.orm import relationship
from app.database.db_manager import Base

class Score(Base):
    __tablename__ = "scores"
    
    id = Column(Integer, primary_key=True, index=True)
    submission_id = Column(Integer, ForeignKey("submissions.id"))
    question_number = Column(Integer)
    score = Column(Float)
    max_score = Column(Float)
    feedback = Column(Text)
    
    # Relationship
    submission = relationship("Submission", back_populates="scores")