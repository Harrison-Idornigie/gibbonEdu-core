<?php
use Gibbon\Domain\School\SchoolYearTermGateway;
use Gibbon\Services\Format;

require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_view.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('View Assessments'), 'report_cards_view.php')
        ->add(__('View Assessment Details'));

    $gibbonPersonID         = $_GET['gibbonPersonID'] ?? '';
    $gibbonSchoolYearTermID = $_GET['gibbonSchoolYearTermID'] ?? '';
    $template               = $_GET['template'] ?? '';

    if (empty($gibbonPersonID) || empty($gibbonSchoolYearTermID) || empty($template)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    try {
        $data = [
            'gibbonPersonID'     => $gibbonPersonID,
            'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'),
        ];

        $sql = "SELECT gibbonPerson.gibbonPersonID, surname, preferredName, gibbonFormGroup.name as formGroup
                FROM gibbonPerson
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID
                AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID";

        $result = $connection2->prepare($sql);
        $result->execute($data);
        $student = $result->fetch();

        if (empty($student)) {
            $page->addError(__('The specified student cannot be found.'));
            return;
        }

        $data = [
            'gibbonPersonID'         => $gibbonPersonID,
            'gibbonSchoolYearTermID' => $gibbonSchoolYearTermID,
            'template'               => $template,
        ];

        $sql = "SELECT assessmentData, timestamp
                FROM extraReportAssessment
                WHERE gibbonPersonIDStudent=:gibbonPersonID
                AND gibbonSchoolYearTermID=:gibbonSchoolYearTermID
                AND template=:template";

        $result = $connection2->prepare($sql);
        $result->execute($data);
        $assessment = $result->fetch();

        if (empty($assessment)) {
            $page->addError(__('The specified assessment cannot be found.'));
            return;
        }

        $termGateway = $container->get(SchoolYearTermGateway::class);
        $termData    = $termGateway->getByID($gibbonSchoolYearTermID);
        $termName    = $termData['name'] ?? $gibbonSchoolYearTermID;

        $templateFile = __DIR__ . "/templates/reportCards/{$template}Report.php";
        if (! file_exists($templateFile)) {
            $page->addError(__('The specified template cannot be found.'));
            return;
        }
        require $templateFile;

        echo "<div class='container mx-auto px-4'>";
        echo "<div class='bg-white shadow-md rounded-lg p-6 mb-8'>";
        echo "<h2 class='text-3xl font-bold mb-4'>" . Format::name('', $student['preferredName'], $student['surname'], 'Student') . " <span class='text-gray-600'>({$student['formGroup']})</span></h2>";
        echo "<div class='grid grid-cols-3 gap-4 text-gray-700'>";
        echo "<div><strong>" . __('Term') . ":</strong> {$termName}</div>";
        echo "<div><strong>" . __('Template') . ":</strong> " . ucfirst($template) . "</div>";
        echo "<div><strong>" . __('Last Updated') . ":</strong> " . Format::dateTime($assessment['timestamp']) . "</div>";
        echo "</div>";
        echo "</div>";

        echo "<div class='bg-gray-100 rounded-lg p-4 mb-6'>";
        echo "<h3 class='text-xl font-semibold mb-3'>" . __('Legend') . "</h3>";
        echo "<div class='flex flex-col space-y-2'>";
        echo "<div class='flex items-center'><span class='inline-block w-3 h-3 mr-2 rounded-full bg-green-500'></span><span class='text-sm'>" . __('Meeting the MINIMUM Standards') . "</span></div>";
        echo "<div class='flex items-center'><span class='inline-block w-3 h-3 mr-2 rounded-full bg-yellow-500'></span><span class='text-sm'>" . __('Meets some of the MINIMUM Standards') . "</span></div>";
        echo "<div class='flex items-center'><span class='inline-block w-3 h-3 mr-2 rounded-full bg-red-500'></span><span class='text-sm'>" . __('Does not meet the MINIMUM Standards') . "</span></div>";
        echo "</div>";
        echo "</div>";

        $jsonData = json_decode($assessment['assessmentData'], true);
        $scoreMap = getAssessmentScores();

        echo "<div class='bg-white shadow-md rounded-lg p-6 mb-8'>";
        echo "<h3 class='text-2xl font-semibold mb-6'>" . __('Regular Assessment') . "</h3>";
        echo "<div class='grid grid-cols-2 gap-6'>";
        foreach ($sections as $sectionKey => $section) {
            echo "<div class='border rounded-lg p-4'>";
            echo "<h4 class='text-xl font-medium mb-4'>{$section['title']}</h4>";

            if (isset($section['items'])) {
                echo "<table class='w-full'>";
                foreach ($section['items'] as $item) {
                    $score     = $jsonData[$sectionKey][$item]['score'] ?? '';
                    $scoreText = $scoreMap[$score] ?? '';

                    echo "<tr class='border-b'>";
                    echo "<td class='py-2'><span class='inline-block w-4 h-4 mr-2 rounded-full " . ($score == 3 ? 'bg-green-500' : ($score == 2 ? 'bg-yellow-500' : 'bg-red-500')) . "'></span>{$item}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            echo "</div>";
        }
        echo "</div>";
        echo "</div>";

        if (isset($developmentSections)) {
            echo "<h3 class='text-center text-xl font-bold mb-4'>" . __('Growth Chart') . "</h3>";
            
            // Container with white background and padding
            echo '<div class="bg-white rounded-lg p-2 shadow-sm mx-auto" style="width: fit-content;">';
            
            // Start SVG container with reduced size
            echo '<div class="relative p-2" style="width: 700px; height: 700px;">';
            echo '<svg class="absolute inset-0 m-2 p-2" viewBox="-260 -260 520 520">';
            
            // Base circles
            echo '<circle cx="0" cy="0" r="250" fill="none" stroke="#22c55e" stroke-width="2"/>';
            echo '<circle cx="0" cy="0" r="190" fill="none" stroke="#facc15" stroke-width="2"/>';
            echo '<circle cx="0" cy="0" r="130" fill="none" stroke="#ef4444" stroke-width="2"/>';
            
            // Cross lines
            // Draw main quadrant dividing lines
            echo '<line x1="-250" y1="0" x2="250" y2="0" stroke="black" stroke-width="2"/>';  // Horizontal line
            echo '<line x1="0" y1="-250" x2="0" y2="250" stroke="black" stroke-width="2"/>';  // Vertical line
            
            // Process each quadrant
            $quadrantAngles = [
                'mental' => 90,    // Right quadrant
                'emotional' => 0,  // Top quadrant
                'spiritual' => 180, // Bottom quadrant
                'physical' => 270  // Left quadrant
            ];
            
            foreach ($developmentSections as $sectionKey => $section) {
                // Clean section key for display
                $displayKey = str_replace(['_chart', '_(chart)', '_( chart)', ' (chart)', ' ( chart)'], '', $sectionKey);
                $displayKey = trim($displayKey);
                $quadrantStart = $quadrantAngles[strtolower($displayKey)];
                
                // Calculate number of items and angle per section
                $items = isset($section['subsections']) ? count($section['subsections']) : 0;
                $sectionAngle = 90 / $items;
                
                $i = 0;
                if (isset($section['subsections'])) {
                    foreach ($section['subsections'] as $subsectionKey => $subsectionName) {
                        $score = $jsonData['development'][$sectionKey][$subsectionName]['score'] ?? 1;
                        
                        // Calculate section angles
                        $startAngle = $quadrantStart + ($i * $sectionAngle);
                        $endAngle = $startAngle + $sectionAngle;
                        
                        // Determine color and radius based on score
                        $fillColor = $score == 3 ? '#22c55e' : ($score == 2 ? '#facc15' : '#ef4444');
                        $innerRadius = $score == 1 ? 0 : ($score == 2 ? 130 : 190);
                        $outerRadius = $score == 1 ? 129 : ($score == 2 ? 189 : 249);
                        
                        // Draw section
                        $path = calculateSectionPath($startAngle, $endAngle, $innerRadius, $outerRadius);
                        echo sprintf('<path d="%s" fill="%s" opacity="0.9"/>', $path, $fillColor);
                        
                        // Add section divider line
                        if ($i > 0) { // Don't draw divider for first section in quadrant
                            echo sprintf('<line x1="0" y1="0" x2="%f" y2="%f" stroke="black" stroke-width="0.5" transform="rotate(%f)"/>',
                                0, -250, $startAngle
                            );
                        }
                        
                        // Add label with improved positioning
                        $labelAngle = $startAngle + ($sectionAngle / 2);
                        $labelRad = ($labelAngle - 90) * M_PI / 180;
                        $labelX = cos($labelRad) * 215;
                        $labelY = sin($labelRad) * 215;
                        
                        // Adjust text rotation for better readability
                        $textRotation = $labelAngle;
                        if ($labelAngle > 90 && $labelAngle < 270) {
                            $textRotation += 180;
                        }
                        
                        // Word wrap long text
                        $words = explode(' ', $subsectionName);
                        $lines = [''];
                        $currentLine = 0;
                        $maxLineLength = 10; // Reduced from 15 to 10 characters
                        
                        foreach ($words as $word) {
                            // Special handling for long words
                            if (strlen($word) > $maxLineLength) {
                                // If current line isn't empty, start a new line
                                if ($lines[$currentLine] !== '') {
                                    $currentLine++;
                                    $lines[$currentLine] = '';
                                }
                                // Add the long word on its own line
                                $lines[$currentLine] = $word;
                                $currentLine++;
                                $lines[$currentLine] = '';
                                continue;
                            }
                            
                            // If adding this word would make the line too long, start a new line
                            if (strlen($lines[$currentLine] . ' ' . $word) > $maxLineLength) {
                                $currentLine++;
                                $lines[$currentLine] = '';
                            }
                            $lines[$currentLine] .= ($lines[$currentLine] !== '' ? ' ' : '') . $word;
                        }
                        
                        // Remove empty last line if it exists
                        if (end($lines) === '') {
                            array_pop($lines);
                        }
                        
                        // Calculate vertical offset for centering multiple lines
                        $lineCount = count($lines);
                        $lineHeight = 11; // Slightly reduced line height
                        $totalHeight = ($lineCount - 1) * $lineHeight;
                        $startY = $labelY - ($totalHeight / 2);
                        
                        // Create text element with multiple tspan elements for each line
                        echo sprintf('<text x="%f" y="%f" text-anchor="middle" font-size="9" transform="rotate(%f %f %f)">', 
                            $labelX, $labelY, $textRotation, $labelX, $labelY);
                        
                        foreach ($lines as $index => $line) {
                            $dy = $index === 0 ? 0 : $lineHeight;
                            echo sprintf('<tspan x="%f" dy="%d">%s</tspan>', 
                                $labelX, $dy, htmlspecialchars(trim($line)));
                        }
                        
                        echo '</text>';
                        
                        $i++;
                    }
                }
                
                // Add quadrant label
                $quadrantLabelAngle = $quadrantStart + 45;
                $quadrantLabelRad = ($quadrantLabelAngle - 90) * M_PI / 180;
                $quadrantLabelX = cos($quadrantLabelRad) * 275;
                $quadrantLabelY = sin($quadrantLabelRad) * 275;
                
                // Adjust quadrant label rotation and baseline
                $quadrantTextRotation = $quadrantLabelAngle;
                $dominantBaseline = 'middle'; // Default to middle alignment
                
                if ($quadrantLabelAngle > 90 && $quadrantLabelAngle < 270) {
                    $quadrantTextRotation += 180;
                }
                
                // Adjust baseline based on quadrant position
                if ($quadrantStart == 0) { // Top quadrant (Emotional)
                    $dominantBaseline = 'text-after-edge';
                } else if ($quadrantStart == 180) { // Bottom quadrant (Spiritual)
                    $dominantBaseline = 'text-before-edge';
                }
                
                echo sprintf('<text x="%f" y="%f" text-anchor="middle" dominant-baseline="%s" font-weight="bold" transform="rotate(%f %f %f)">%s</text>',
                    $quadrantLabelX, $quadrantLabelY, $dominantBaseline, $quadrantTextRotation, $quadrantLabelX, $quadrantLabelY, ucfirst($displayKey)
                );
            }
            
            echo '</svg>';
            echo '</div>';
            echo '</div>';
        }
        echo "</div>";
    } catch (Exception $e) {
        $page->addError($e->getMessage());
    }
}

// Helper function to calculate SVG path for a section
function calculateSectionPath($startAngle, $endAngle, $innerRadius, $outerRadius) {
    // Convert angles to radians
    $startRad = ($startAngle - 90) * M_PI / 180;
    $endRad = ($endAngle - 90) * M_PI / 180;
    
    // Calculate points
    $startOuterX = cos($startRad) * $outerRadius;
    $startOuterY = sin($startRad) * $outerRadius;
    $endOuterX = cos($endRad) * $outerRadius;
    $endOuterY = sin($endRad) * $outerRadius;
    
    // For center sections (red)
    if ($innerRadius == 0) {
        return sprintf("M %f %f L %f %f A %d %d 0 0 1 %f %f Z",
            0, 0,
            $startOuterX, $startOuterY,
            $outerRadius, $outerRadius,
            $endOuterX, $endOuterY
        );
    }
    
    $startInnerX = cos($startRad) * $innerRadius;
    $startInnerY = sin($startRad) * $innerRadius;
    $endInnerX = cos($endRad) * $innerRadius;
    $endInnerY = sin($endRad) * $innerRadius;
    
    return sprintf("M %f %f L %f %f A %d %d 0 0 1 %f %f L %f %f A %d %d 0 0 0 %f %f Z",
        $startInnerX, $startInnerY,
        $startOuterX, $startOuterY,
        $outerRadius, $outerRadius,
        $endOuterX, $endOuterY,
        $endInnerX, $endInnerY,
        $innerRadius, $innerRadius,
        $startInnerX, $startInnerY
    );
}
