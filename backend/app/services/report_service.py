from reportlab.lib import colors
from reportlab.lib.pagesizes import letter, A4
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer, Image
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.pdfgen import canvas
from datetime import datetime
import os
from typing import List, Dict
import logging

logger = logging.getLogger(__name__)

class ReportService:
    def __init__(self, output_dir: str):
        self.output_dir = output_dir
        os.makedirs(output_dir, exist_ok=True)
        
    def generate_grading_report(self, exam_data: Dict, scores: List[Dict], output_filename: str = None) -> str:
        """Generate PDF report for grading results"""
        try:
            if not output_filename:
                timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
                output_filename = f"grading_report_{timestamp}.pdf"
            
            filepath = os.path.join(self.output_dir, output_filename)
            
            # Create PDF document
            doc = SimpleDocTemplate(filepath, pagesize=A4)
            styles = getSampleStyleSheet()
            story = []
            
            # Add title
            title_style = ParagraphStyle(
                'CustomTitle',
                parent=styles['Heading1'],
                fontSize=24,
                textColor=colors.HexColor('#2E4057')
            )
            story.append(Paragraph("Smart Grading - Exam Report", title_style))
            story.append(Spacer(1, 0.25*inch))
            
            # Add exam info
            story.append(Paragraph(f"<b>Exam:</b> {exam_data.get('title', 'N/A')}", styles['Normal']))
            story.append(Paragraph(f"<b>Class:</b> {exam_data.get('class_name', 'N/A')}", styles['Normal']))
            story.append(Paragraph(f"<b>Date:</b> {datetime.now().strftime('%Y-%m-%d %H:%M')}", styles['Normal']))
            story.append(Spacer(1, 0.5*inch))
            
            # Add summary statistics
            story.append(Paragraph("Summary Statistics", styles['Heading2']))
            stats = self._calculate_statistics(scores)
            stats_data = [
                ['Statistic', 'Value'],
                ['Total Students', str(stats['total_students'])],
                ['Average Score', f"{stats['average_score']:.2f}"],
                ['Highest Score', f"{stats['highest_score']:.2f}"],
                ['Lowest Score', f"{stats['lowest_score']:.2f}"],
                ['Pass Rate', f"{stats['pass_rate']:.1f}%"]
            ]
            stats_table = Table(stats_data)
            stats_table.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, 0), colors.grey),
                ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
                ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
                ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
                ('FONTSIZE', (0, 0), (-1, 0), 14),
                ('BOTTOMPADDING', (0, 0), (-1, 0), 12),
                ('BACKGROUND', (0, 1), (-1, -1), colors.beige),
                ('GRID', (0, 0), (-1, -1), 1, colors.black)
            ]))
            story.append(stats_table)
            story.append(Spacer(1, 0.5*inch))
            
            # Add detailed scores
            story.append(Paragraph("Detailed Scores", styles['Heading2']))
            story.append(Spacer(1, 0.25*inch))
            
            # Create detailed scores table
            table_data = [['No', 'Student Name', 'NIM', 'Score', 'Status']]
            for idx, score in enumerate(scores, 1):
                status = 'PASS' if score.get('score', 0) >= 60 else 'FAIL'
                table_data.append([
                    str(idx),
                    score.get('student_name', 'N/A'),
                    score.get('nim', 'N/A'),
                    f"{score.get('score', 0):.2f}",
                    status
                ])
            
            scores_table = Table(table_data)
            scores_table.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, 0), colors.grey),
                ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
                ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
                ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
                ('FONTSIZE', (0, 0), (-1, 0), 12),
                ('BOTTOMPADDING', (0, 0), (-1, 0), 12),
                ('BACKGROUND', (0, 1), (-1, -1), colors.beige),
                ('GRID', (0, 0), (-1, -1), 1, colors.black),
                ('FONTSIZE', (0, 1), (-1, -1), 10),
            ]))
            story.append(scores_table)
            
            # Build PDF
            doc.build(story)
            logger.info(f"Report generated: {filepath}")
            return filepath
            
        except Exception as e:
            logger.error(f"Error generating report: {str(e)}")
            raise Exception(f"Report generation failed: {str(e)}")
    
    def _calculate_statistics(self, scores: List[Dict]) -> Dict:
        """Calculate statistics from scores"""
        if not scores:
            return {
                'total_students': 0,
                'average_score': 0,
                'highest_score': 0,
                'lowest_score': 0,
                'pass_rate': 0
            }
        
        score_values = [s.get('score', 0) for s in scores]
        total_students = len(score_values)
        average_score = sum(score_values) / total_students if total_students > 0 else 0
        highest_score = max(score_values) if score_values else 0
        lowest_score = min(score_values) if score_values else 0
        pass_count = sum(1 for s in score_values if s >= 60)
        pass_rate = (pass_count / total_students) * 100 if total_students > 0 else 0
        
        return {
            'total_students': total_students,
            'average_score': average_score,
            'highest_score': highest_score,
            'lowest_score': lowest_score,
            'pass_rate': pass_rate
        }