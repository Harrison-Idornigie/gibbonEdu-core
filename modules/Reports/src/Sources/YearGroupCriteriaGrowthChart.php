<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\Reports\Sources;

/**
 * Growth Chart extension for YearGroupCriteria
 * Adds SVG path calculations for the growth chart visualization
 *
 * @version v29
 * @since   v29
 */
class YearGroupCriteriaGrowthChart extends YearGroupCriteria
{
    /**
     * Calculate SVG path for a section of the growth chart
     * @param float $startAngle Starting angle in degrees
     * @param float $endAngle Ending angle in degrees
     * @param float $innerRadius Inner radius of the section
     * @param float $outerRadius Outer radius of the section
     * @return string SVG path data
     */
    private function calculateSectionPath($startAngle, $endAngle, $innerRadius, $outerRadius)
    {
        try {
            // Sanitize and validate inputs
            $startAngle = floatval($startAngle);
            $endAngle = floatval($endAngle);
            $innerRadius = floatval($innerRadius);
            $outerRadius = floatval($outerRadius);

            if ($innerRadius >= $outerRadius) {
                throw new \Exception("Inner radius must be less than outer radius");
            }

            // Convert angles to radians
            $startRad = $startAngle * M_PI / 180;
            $endRad = $endAngle * M_PI / 180;

            // Calculate points with validation
            $x1 = $this->validateCoordinate(cos($startRad) * $outerRadius);
            $y1 = $this->validateCoordinate(sin($startRad) * $outerRadius);
            $x2 = $this->validateCoordinate(cos($endRad) * $outerRadius);
            $y2 = $this->validateCoordinate(sin($endRad) * $outerRadius);
            $x3 = $this->validateCoordinate(cos($endRad) * $innerRadius);
            $y3 = $this->validateCoordinate(sin($endRad) * $innerRadius);
            $x4 = $this->validateCoordinate(cos($startRad) * $innerRadius);
            $y4 = $this->validateCoordinate(sin($startRad) * $innerRadius);

            // Determine arc flag
            $largeArcFlag = ($endAngle - $startAngle > 180) ? 1 : 0;

            // Generate SVG path with sanitized values and explicit string formatting
            $path = sprintf(
                "M%.2f,%.2f A%.2f,%.2f 0 %d 1 %.2f,%.2f L%.2f,%.2f A%.2f,%.2f 0 %d 0 %.2f,%.2f Z",
                $x1, $y1,
                $outerRadius, $outerRadius, $largeArcFlag, $x2, $y2,
                $x3, $y3,
                $innerRadius, $innerRadius, $largeArcFlag, $x4, $y4
            );

            // Clean up the path string
            return trim(preg_replace('/\s+/', ' ', $path));
        } catch (\Exception $e) {
            error_log("SVG path calculation error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Validate and sanitize coordinate values
     * @param float $value The coordinate value to validate
     * @return float Sanitized coordinate value
     */
    private function validateCoordinate($value)
    {
        if (is_nan($value) || is_infinite($value)) {
            throw new \Exception("Invalid coordinate value");
        }
        return round($value, 2);
    }

    /**
     * Calculate label position for the growth chart
     * @param float $angle Angle in degrees
     * @param float $radius Distance from center
     * @return array [x, y] coordinates
     */
    private function calculateLabelPosition($angle, $radius)
    {
        try {
            // Validate input parameters
            if (! is_numeric($angle) || ! is_numeric($radius)) {
                throw new \Exception("Invalid parameters for label position calculation");
            }

            // Normalize angle to 0-360 range
            $angle = fmod($angle, 360);
            if ($angle < 0) {
                $angle += 360;
            }

            // Convert to radians
            $rad = $angle * M_PI / 180;

            // Calculate position with validation
            $x = round(cos($rad) * $radius, 2);
            $y = round(sin($rad) * $radius, 2);

            if (is_nan($x) || is_nan($y)) {
                throw new \Exception("Invalid label position calculation");
            }

            return [
                'x' => $x,
                'y' => $y,
            ];
        } catch (\Exception $e) {
            error_log("Label position calculation error: " . $e->getMessage());
            return ['x' => 0, 'y' => 0];
        }
    }

    /**
     * Override getData to add growth chart calculations
     */
    public function getData($ids = [])
    {
        try {
            $data = parent::getData($ids);
            
            // Validate data structure
            if (!is_array($data)) {
                throw new \Exception("Invalid data structure returned from parent");
            }

            // Define quadrant angles
            $quadrantAngles = [
                'Mental'    => 225,
                'Spiritual' => 315,
                'Physical'  => 45,
                'Emotional' => 135,
            ];

            // Process data arrays with validation
            foreach (['perGroup', 'perStudent'] as $dataKey) {
                if (isset($data[$dataKey]) && is_array($data[$dataKey])) {
                    $processedItems = [];
                    foreach ($data[$dataKey] as $key => $item) {
                        if (!is_array($item)) {
                            error_log("Warning: Invalid item structure in $dataKey");
                            continue;
                        }

                        if (isset($item['category']) && strpos($item['category'], 'Developmental Chart') === 0) {
                            try {
                                $processedItem = $item;
                                $this->processChartItem($processedItem, $quadrantAngles, $data[$dataKey]);
                                
                                // Clean and sanitize all values for serialization
                                $processedItem = $this->sanitizeForSerialization($processedItem);
                                
                                // Ensure SVG path is properly formatted
                                if (!empty($processedItem['svgPath'])) {
                                    $processedItem['svgPath'] = trim(preg_replace('/\s+/', ' ', $processedItem['svgPath']));
                                }
                                
                                $processedItems[$key] = $processedItem;
                            } catch (\Exception $e) {
                                error_log("Error processing chart item: " . $e->getMessage());
                                $processedItems[$key] = $this->sanitizeForSerialization($item);
                            }
                        } else {
                            $processedItems[$key] = $this->sanitizeForSerialization($item);
                        }
                    }
                    $data[$dataKey] = $processedItems;
                    // Add more random key-value pairs for testing
                    $data['randomTest'] = [
                        'key1' => 'value1',
                        'key2' => rand(1, 100),
                        'key3' => [
                            'nestedKey' => 'nestedValue',
                            'randomBoolean' => (bool)random_int(0, 1),
                        ],
                        'key4' => date('Y-m-d H:i:s'),
                    ];

                }
            }

            // Final sanitization of entire data structure
            $data = $this->sanitizeForSerialization($data);

            return $data;
        } catch (\Exception $e) {
            error_log("Error in YearGroupCriteriaGrowthChart getData: " . $e->getMessage());
            return $this->sanitizeForSerialization(parent::getData($ids));
        }
    }

    /**
     * Sanitize data for serialization
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    private function sanitizeForSerialization($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeForSerialization'], $value);
        } elseif (is_object($value)) {
            return null; // Remove objects to prevent serialization issues
        } elseif (is_float($value)) {
            return round($value, 2); // Consistent decimal places
        } elseif (is_string($value)) {
            return trim($value); // Remove extra whitespace
        }
        return $value;
    }

    /**
     * Process a single chart item
     * @param array &$item The item to process
     * @param array $quadrantAngles The quadrant angle definitions
     * @param array $group The full group array for counting items
     */
    private function processChartItem(&$item, $quadrantAngles, $group)
    {
        // Initialize default values for SVG properties
        $item['svgPath']            = '';
        $item['dividerX']           = 0;
        $item['dividerY']           = 0;
        $item['labelX']             = 0;
        $item['labelY']             = 0;
        $item['labelAngle']         = 0;
        $item['quadrantLabelX']     = 0;
        $item['quadrantLabelY']     = 0;
        $item['quadrantLabelAngle'] = 0;

        // Extract dev type from category
        preg_match('/Developmental Chart \((.*?)\)/', $item['category'], $matches);
        $devType = $matches[1] ?? '';

        if (! isset($quadrantAngles[$devType])) {
            error_log("Warning: Invalid development type: $devType");
            return;
        }

        $startAngle = $quadrantAngles[$devType];
        $score      = intval($item['value'] ?? 1);

        // Validate score range
        if ($score < 1 || $score > 3) {
            error_log("Warning: Invalid score value {$score} for {$item['criteriaName']}, defaulting to 1");
            $score = 1;
        }

        // Debug logging
        error_log("Processing chart item for $devType: Score=$score, StartAngle=$startAngle");

        // Calculate section angles with validation
        $itemCount = count(array_filter($group, function ($c) use ($item) {
            return $c['category'] === $item['category'];
        }));

        if ($itemCount === 0) {
            error_log("Warning: No items found for category {$item['category']}");
            return;
        }

        // Get the index of current item within its category
        $categoryItems = array_values(array_filter($group, function ($c) use ($item) {
            return $c['category'] === $item['category'];
        }));
        $itemIndex = array_search($item, $categoryItems);

        if ($itemIndex === false) {
            error_log("Warning: Could not find item index for {$item['criteriaName']}");
            return;
        }

        $sectionAngle = 90 / max(1, $itemCount); // Prevent division by zero
        $sectionStart = $startAngle + ($itemIndex * $sectionAngle);
        $sectionEnd   = $sectionStart + $sectionAngle;

        // Calculate radii based on score
        $innerRadius = $score == 1 ? 0 : ($score == 2 ? 130 : 190);
        $outerRadius = $score == 1 ? 129 : ($score == 2 ? 189 : 249);

        // Add SVG path with validation
        try {
            $svgPath = $this->calculateSectionPath(
                $sectionStart,
                $sectionEnd,
                $innerRadius,
                $outerRadius
            );

            if (! $svgPath) {
                throw new \Exception("Failed to generate SVG path");
            }

            $item['svgPath'] = $svgPath;

            // Add divider line coordinates
            $dividerRad       = $sectionStart * M_PI / 180;
            $item['dividerX'] = round(cos($dividerRad) * 250, 2);
            $item['dividerY'] = round(sin($dividerRad) * 250, 2);

            // Add label positions and angles
            $labelAngle         = $sectionStart + ($sectionAngle / 2);
            $labelPos           = $this->calculateLabelPosition($labelAngle, 225);
            $item['labelX']     = round($labelPos['x'], 2);
            $item['labelY']     = round($labelPos['y'], 2);
            $item['labelAngle'] = round($labelAngle + ($labelAngle > 90 && $labelAngle < 270 ? 180 : 0), 2);

            // Add quadrant label positions
            $quadrantLabelAngle         = $startAngle + 45;
            $quadrantLabelRadius        = $quadrantLabelAngle > 45 && $quadrantLabelAngle < 180 ? 280 : 270;
            $quadrantPos                = $this->calculateLabelPosition($quadrantLabelAngle, $quadrantLabelRadius);
            $item['quadrantLabelX']     = round($quadrantPos['x'], 2);
            $item['quadrantLabelY']     = round($quadrantPos['y'], 2);
            $item['quadrantLabelAngle'] = round($quadrantLabelAngle + ($quadrantLabelAngle > 0 && $quadrantLabelAngle < 180 ? -90 : 90), 2);

        } catch (\Exception $e) {
            error_log("Error processing chart item {$item['criteriaName']}: " . $e->getMessage());
        }
    }
}
