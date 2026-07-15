import easyocr
import cv2
import numpy as np
from pdf2image import convert_from_path
import os
from typing import List, Dict
import logging

# --- Compatibility patch ---
# easyocr==1.7.0 masih memanggil PIL.Image.ANTIALIAS, yang sudah dihapus
# di Pillow>=10.0 (diganti Image.Resampling.LANCZOS). Tambal di sini
# supaya tidak perlu downgrade Pillow (downgrade akan gagal build di
# Python 3.14).
from PIL import Image
if not hasattr(Image, 'ANTIALIAS'):
    Image.ANTIALIAS = Image.Resampling.LANCZOS

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class OCRService:
    def __init__(self):
        self.reader = easyocr.Reader(['en'], gpu=False)  # Use gpu=True if CUDA available
        
    def extract_text_from_pdf(self, pdf_path: str) -> str:
        """Extract text from PDF using EasyOCR"""
        try:
            # Convert PDF to images
            images = convert_from_path(pdf_path)
            extracted_text = []
            
            for i, image in enumerate(images):
                # Convert PIL image to numpy array
                img_array = np.array(image)
                
                # Convert to RGB if needed
                if len(img_array.shape) == 2:
                    img_array = cv2.cvtColor(img_array, cv2.COLOR_GRAY2RGB)
                elif img_array.shape[2] == 4:
                    img_array = cv2.cvtColor(img_array, cv2.COLOR_RGBA2RGB)
                
                # Perform OCR
                results = self.reader.readtext(img_array, detail=0)
                page_text = ' '.join(results)
                extracted_text.append(f"Page {i+1}:\n{page_text}")
                
                logger.info(f"Extracted text from page {i+1}")
            
            return '\n\n'.join(extracted_text)
            
        except Exception as e:
            logger.error(f"Error in OCR extraction: {str(e)}")
            raise Exception(f"OCR extraction failed: {str(e)}")
    
    def extract_text_from_image(self, image_path: str) -> str:
        """Extract text from image file"""
        try:
            results = self.reader.readtext(image_path, detail=0)
            return ' '.join(results)
        except Exception as e:
            logger.error(f"Error in OCR extraction from image: {str(e)}")
            raise Exception(f"OCR extraction failed: {str(e)}")