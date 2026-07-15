import re
from typing import Dict, List, Tuple
import logging

logger = logging.getLogger(__name__)

class GradingService:
    def __init__(self):
        self.similarity_threshold = 0.6
        
    def grade_answer(self, student_answer: str, key_answer: str, rubric: Dict) -> Dict:
        """Grade a single answer based on key and rubric"""
        try:
            # Clean and normalize text
            student_clean = self._clean_text(student_answer)
            key_clean = self._clean_text(key_answer)
            
            # Calculate similarity
            similarity = self._calculate_similarity(student_clean, key_clean)
            
            # Extract keywords from key answer
            keywords = self._extract_keywords(key_clean)
            matched_keywords = self._count_matched_keywords(student_clean, keywords)
            
            # Calculate score based on rubric
            score = self._calculate_score(similarity, matched_keywords, len(keywords), rubric)
            
            # Generate feedback
            feedback = self._generate_feedback(similarity, matched_keywords, len(keywords))
            
            return {
                'score': score,
                'feedback': feedback,
                'similarity': similarity,
                'keywords_matched': matched_keywords,
                'total_keywords': len(keywords)
            }
        except Exception as e:
            logger.error(f"Error in grading: {str(e)}")
            raise Exception(f"Grading failed: {str(e)}")
    
    def _clean_text(self, text: str) -> str:
        """Clean and normalize text"""
        # Convert to lowercase
        text = text.lower()
        # Remove extra whitespace
        text = ' '.join(text.split())
        # Remove special characters but keep letters, numbers, and spaces
        text = re.sub(r'[^a-zA-Z0-9\s]', '', text)
        return text
    
    def _calculate_similarity(self, text1: str, text2: str) -> float:
        """Calculate similarity between two texts using Jaccard similarity"""
        if not text1 or not text2:
            return 0.0
        
        words1 = set(text1.split())
        words2 = set(text2.split())
        
        intersection = len(words1.intersection(words2))
        union = len(words1.union(words2))
        
        if union == 0:
            return 0.0
        
        return intersection / union
    
    def _extract_keywords(self, text: str) -> List[str]:
        """Extract important keywords from text"""
        # Simple keyword extraction - split and filter common words
        common_words = {'the', 'a', 'an', 'is', 'are', 'was', 'were', 'and', 'or', 'but', 'for', 'nor', 'on', 'at', 'to', 'by', 'in', 'of', 'from'}
        words = text.split()
        keywords = [word for word in words if word not in common_words and len(word) > 3]
        return keywords
    
    def _count_matched_keywords(self, text: str, keywords: List[str]) -> int:
        """Count how many keywords appear in the text"""
        words = set(text.split())
        matched = sum(1 for keyword in keywords if keyword in words)
        return matched
    
    def _calculate_score(self, similarity: float, matched: int, total: int, rubric: Dict) -> float:
        """Calculate score based on rubric"""
        # Default rubric if not provided
        max_score = rubric.get('max_score', 100)
        keyword_weight = rubric.get('keyword_weight', 0.6)
        similarity_weight = rubric.get('similarity_weight', 0.4)
        
        # Keyword score
        keyword_score = (matched / total) if total > 0 else 0
        
        # Combined score
        combined_score = (keyword_score * keyword_weight) + (similarity * similarity_weight)
        
        # Scale to max_score
        final_score = combined_score * max_score
        
        return round(min(final_score, max_score), 2)
    
    def _generate_feedback(self, similarity: float, matched: int, total: int) -> str:
        """Generate feedback based on grading results"""
        if similarity >= 0.8 and matched >= total * 0.7:
            return "Excellent answer! Good understanding of the topic."
        elif similarity >= 0.6 and matched >= total * 0.5:
            return "Good answer, but some key points are missing."
        elif similarity >= 0.4 and matched >= total * 0.3:
            return "Adequate answer. More details and key concepts needed."
        else:
            return "Needs improvement. Please review the material and key concepts."