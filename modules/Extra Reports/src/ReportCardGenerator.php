<?php
namespace Gibbon\Module\ExtraReports;

use Gibbon\Services\Format;
use TCPDF;

class ReportCardGenerator {
    private $pdf;
    private $student;
    private $reportingCycle;
    private $sections;
    private $assessmentScale;
    private $chartSections;
    private $pageProperties;

    public function __construct($student, $reportingCycle, $sections, $assessmentScale, $chartSections, $pageProperties) {
        $this->student = $student;
        $this->reportingCycle = $reportingCycle;
        $this->sections = $sections;
        $this->assessmentScale = $assessmentScale;
        $this->chartSections = $chartSections;
        $this->pageProperties = $pageProperties;
        
        // Initialize PDF
        $this->initializePDF();
    }

    private function initializePDF() {
        // Create new PDF document
        $this->pdf = new TCPDF($this->pageProperties['orientation'], 'mm', $this->pageProperties['size']);
        
        // Set document information
        $this->pdf->SetCreator('Gibbon');
        $this->pdf->SetAuthor('School Administration');
        $this->pdf->SetTitle('Student Report Card');
        
        // Remove default header/footer
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        
        // Set margins
        $this->pdf->SetMargins(15, 15, 15);
        
        // Set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, 15);
    }

    public function generatePDF() {
        // Add a page
        $this->pdf->AddPage();
        
        // Add header information
        $this->addHeader();
        
        // Add student information
        $this->addStudentInfo();
        
        // Add assessment sections
        $this->addAssessmentSections();
        
        // Add development chart
        $this->addDevelopmentChart();
        
        // Add footer with assessment scale
        $this->addFooter();
        
        // Output PDF
        return $this->pdf->Output('StudentReport.pdf', 'I');
    }

    private function addHeader() {
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 10, 'Student Report Card - ' . $this->reportingCycle, 0, 1, 'C');
        $this->pdf->Ln(5);
    }

    private function addStudentInfo() {
        $this->pdf->SetFont('helvetica', '', 12);
        $this->pdf->Cell(0, 10, 'Student Name: ' . $this->student['fullName'], 0, 1);
        $this->pdf->Cell(0, 10, 'Grade: ' . $this->student['grade'], 0, 1);
        $this->pdf->Cell(0, 10, 'Academic Year: ' . $this->student['academicYear'], 0, 1);
        $this->pdf->Ln(5);
    }

    private function addAssessmentSections() {
        foreach ($this->sections as $sectionKey => $section) {
            $this->pdf->SetFont('helvetica', 'B', 14);
            $this->pdf->Cell(0, 10, $section['title'], 0, 1);
            
            $this->pdf->SetFont('helvetica', '', 12);
            foreach ($section['items'] as $item) {
                // Get score from database
                $score = $this->getScoreForItem($sectionKey, $item);
                
                // Set color based on score
                switch ($score) {
                    case 3:
                        $this->pdf->SetFillColor(0, 255, 0); // Green
                        break;
                    case 2:
                        $this->pdf->SetFillColor(255, 255, 0); // Yellow
                        break;
                    case 1:
                        $this->pdf->SetFillColor(255, 0, 0); // Red
                        break;
                    default:
                        $this->pdf->SetFillColor(255, 255, 255); // White
                }
                
                // Add filled checkbox and item text
                $this->pdf->Cell(10, 10, '■', 0, 0, 'L', true);
                $this->pdf->Cell(0, 10, $item, 0, 1);
            }
            $this->pdf->Ln(5);
        }
    }

    private function getScoreForItem($section, $item) {
        global $pdo;
        
        $data = ['gibbonPersonID' => $this->student['gibbonPersonID'],
                'reportingPeriod' => $this->reportingCycle,
                'section' => $section,
                'item' => $item];
                
        $sql = "SELECT score 
                FROM extraReportAssessment 
                WHERE gibbonPersonID=:gibbonPersonID 
                AND reportingPeriod=:reportingPeriod 
                AND section=:section 
                AND item=:item";
                
        $result = $pdo->selectOne($sql, $data);
        return $result['score'] ?? 0;
    }

    private function addDevelopmentChart() {
        // Center point and dimensions
        $centerX = 150;
        $centerY = 150;
        $outerRadius = 80;
        $innerRadius = 40;
        $spokeLength = $outerRadius;

        // Define chart sections (clockwise from top)
        $chartSections = [
            'Mental' => ['angle' => 0],
            'E.Y.E Testing' => ['angle' => 45],
            'Focus' => ['angle' => 90],
            'Emotional' => ['angle' => 135],
            'E.Y.E Testing' => ['angle' => 180],
            'WITS' => ['angle' => 225],
            'Spiritual' => ['angle' => 270],
            'Indigenous Pedagogies' => ['angle' => 315]
        ];

        // Draw outer circle sections
        foreach ($chartSections as $section => $props) {
            $startAngle = $props['angle'];
            $endAngle = $startAngle + 45; // Each section is 45 degrees
            
            // Get average score for this section
            $score = $this->getAverageSectionScore($section);
            
            // Set color based on score
            switch ($score) {
                case 3:
                    $this->pdf->SetFillColor(0, 200, 0); // Green
                    break;
                case 2:
                    $this->pdf->SetFillColor(255, 255, 0); // Yellow
                    break;
                case 1:
                    $this->pdf->SetFillColor(255, 0, 0); // Red
                    break;
                default:
                    $this->pdf->SetFillColor(255, 255, 255); // White
            }
            
            // Draw filled sector
            $this->pdf->PieSector($centerX, $centerY, $outerRadius, $startAngle, $endAngle, 'FD');
            
            // Add section label
            $labelAngle = deg2rad($startAngle + 22.5); // Midpoint of the 45-degree section
            $labelX = $centerX + cos($labelAngle) * ($outerRadius + 15);
            $labelY = $centerY + sin($labelAngle) * ($outerRadius + 15);
            
            // Rotate text based on position
            $textAngle = $startAngle + 22.5;
            if ($textAngle > 90 && $textAngle < 270) {
                $textAngle += 180; // Flip text for better readability
            }
            
            $this->pdf->StartTransform();
            $this->pdf->Rotate($textAngle, $labelX, $labelY);
            $this->pdf->SetFont('helvetica', '', 8);
            $this->pdf->Text($labelX, $labelY, $section);
            $this->pdf->StopTransform();
        }

        // Draw spokes (dividing lines)
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->SetDrawColor(0, 0, 0);
        foreach ($chartSections as $section => $props) {
            $angle = deg2rad($props['angle']);
            $endX = $centerX + cos($angle) * $spokeLength;
            $endY = $centerY + sin($angle) * $spokeLength;
            $this->pdf->Line($centerX, $centerY, $endX, $endY);
        }

        // Draw inner circles for special indicators
        $this->drawSpecialIndicator($centerX, $centerY, $innerRadius, 'Dramatic Play');
        $this->drawSpecialIndicator($centerX - 100, $centerY + 100, $innerRadius, 'Read Alouds');
    }

    private function drawSpecialIndicator($x, $y, $radius, $label) {
        // Draw outer circle
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->Circle($x, $y, $radius);

        // Draw cross lines
        $this->pdf->Line($x - $radius, $y, $x + $radius, $y);
        $this->pdf->Line($x, $y - $radius, $x, $y + $radius);

        // Add label
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->Text($x - 15, $y + $radius + 5, $label);
    }

    private function getAverageSectionScore($section) {
        global $pdo;
        
        $data = ['gibbonPersonID' => $this->student['gibbonPersonID'],
                'reportingPeriod' => $this->reportingCycle,
                'section' => $section];
                
        $sql = "SELECT AVG(score) as avgScore 
                FROM extraReportAssessment 
                WHERE gibbonPersonID=:gibbonPersonID 
                AND reportingPeriod=:reportingPeriod 
                AND section=:section";
                
        $result = $pdo->selectOne($sql, $data);
        return round($result['avgScore'] ?? 0);
    }

    private function addFooter() {
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 10, 'Assessment Scale:', 0, 1);
        
        $this->pdf->SetFont('helvetica', '', 12);
        foreach ($this->assessmentScale as $color => $description) {
            $this->pdf->Cell(0, 10, "■ $description", 0, 1);
        }
    }
}
?>
