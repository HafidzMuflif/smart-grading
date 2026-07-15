import os
from dotenv import load_dotenv

load_dotenv()

class Config:
    DATABASE_URL = os.getenv('DATABASE_URL')
    SECRET_KEY = os.getenv('SECRET_KEY')
    OCR_ENGINE = os.getenv('OCR_ENGINE', 'easyocr')
    UPLOAD_DIR = os.getenv('UPLOAD_DIR', './uploads')
    REPORT_DIR = os.getenv('REPORT_DIR', './reports')
    MAX_FILE_SIZE = int(os.getenv('MAX_FILE_SIZE', 10485760))
    ALLOWED_EXTENSIONS = os.getenv('ALLOWED_EXTENSIONS', 'pdf,txt,csv').split(',')
    GEMINI_API_KEY = os.getenv('GEMINI_API_KEY')
    GEMINI_MODEL = os.getenv('GEMINI_MODEL', 'gemini-3.5-flash')
    
    # Create directories if they don't exist
    os.makedirs(UPLOAD_DIR, exist_ok=True)
    os.makedirs(REPORT_DIR, exist_ok=True)