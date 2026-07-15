from sqlalchemy import Column, Integer, String, Enum
from app.database.db_manager import Base
import enum

class UserRole(enum.Enum):
    ADMIN = "admin"
    DOSEN = "dosen"

class User(Base):
    __tablename__ = "users"
    
    id = Column(Integer, primary_key=True, index=True)
    username = Column(String(50), unique=True, nullable=False)
    password_hash = Column(String(255), nullable=False)
    email = Column(String(100), unique=True, nullable=False)
    role = Column(Enum(UserRole), default=UserRole.DOSEN)