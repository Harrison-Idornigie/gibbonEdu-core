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

        // Debug log the incoming item
        error_log("Processing item: " . json_encode([
            'name' => $item['criteriaName'] ?? 'unknown',
            'category' => $item['category'] ?? 'unknown',
            'value' => $item['value'] ?? 'unknown'
        ]));

        // Extract dev type from category with strict validation
        if (!preg_match('/^Developmental Chart \((Mental|Spiritual|Physical|Emotional)\)$/', $item['category'] ?? '', $matches)) {
            error_log("Invalid category format: " . ($item['category'] ?? 'unknown'));
            return;
        }
        $devType = $matches[1];

        // Ensure value is an integer between 1-3
        $score = max(1, min(3, intval($item['value'] ?? 1)));
        $item['value'] = $score; // Update the value to ensure it's an integer

        $startAngle = $quadrantAngles[$devType];

        // Get all items for this category
        $categoryItems = array_values(array_filter($group, function ($c) use ($item) {
            return ($c['category'] ?? '') === $item['category'];
        }));

        $itemCount = count($categoryItems);
        if ($itemCount === 0) {
            error_log("No items found for category: " . ($item['category'] ?? 'unknown'));
            return;
        }

        // Find item index by criteriaName
        $itemIndex = array_search(
            $item['criteriaName'],
            array_column($categoryItems, 'criteriaName')
        );

        if ($itemIndex === false) {
            error_log("Could not find index for item: " . ($item['criteriaName'] ?? 'unknown'));
            return;
        }

        // Calculate angles
        $sectionAngle = 90 / $itemCount;
        $sectionStart = $startAngle + ($itemIndex * $sectionAngle);
        $sectionEnd = $sectionStart + $sectionAngle;

        // Calculate radii based on score
        $innerRadius = $score == 1 ? 0 : ($score == 2 ? 130 : 190);
        $outerRadius = $score == 1 ? 129 : ($score == 2 ? 189 : 249);

        // Debug log calculations
        error_log("Calculation parameters: " . json_encode([
            'devType' => $devType,
            'score' => $score,
            'itemCount' => $itemCount,
            'itemIndex' => $itemIndex,
            'sectionAngle' => $sectionAngle,
            'sectionStart' => $sectionStart,
            'sectionEnd' => $sectionEnd,
            'innerRadius' => $innerRadius,
            'outerRadius' => $outerRadius
        ]));

        try {
            // Generate SVG path
            $svgPath = $this->calculateSectionPath(
                $sectionStart,
                $sectionEnd,
                $innerRadius,
                $outerRadius
            );

            if (empty($svgPath)) {
                throw new \Exception("Failed to generate SVG path");
            }

            $item['svgPath'] = $svgPath;

            // Calculate divider line
            $dividerRad = $sectionStart * M_PI / 180;
            $item['dividerX'] = $this->validateCoordinate(cos($dividerRad) * 250);
            $item['dividerY'] = $this->validateCoordinate(sin($dividerRad) * 250);

            // Calculate label position
            $labelAngle = $sectionStart + ($sectionAngle / 2);
            $labelRad = $labelAngle * M_PI / 180;
            $labelRadius = 225; // Consistent label distance
            $item['labelX'] = $this->validateCoordinate(cos($labelRad) * $labelRadius);
            $item['labelY'] = $this->validateCoordinate(sin($labelRad) * $labelRadius);
            $item['labelAngle'] = $this->validateCoordinate(
                $labelAngle + ($labelAngle > 90 && $labelAngle < 270 ? 180 : 0)
            );

            // Calculate quadrant label
            $quadrantLabelAngle = $startAngle + 45;
            $quadrantLabelRad = $quadrantLabelAngle * M_PI / 180;
            $quadrantRadius = $quadrantLabelAngle > 45 && $quadrantLabelAngle < 180 ? 280 : 270;
            $item['quadrantLabelX'] = $this->validateCoordinate(cos($quadrantLabelRad) * $quadrantRadius);
            $item['quadrantLabelY'] = $this->validateCoordinate(sin($quadrantLabelRad) * $quadrantRadius);
            $item['quadrantLabelAngle'] = $this->validateCoordinate(
                $quadrantLabelAngle + ($quadrantLabelAngle > 0 && $quadrantLabelAngle < 180 ? -90 : 90)
            );

            // Debug log final values
            error_log("Final SVG values: " . json_encode([
                'svgPath' => $item['svgPath'],
                'divider' => [$item['dividerX'], $item['dividerY']],
                'label' => [$item['labelX'], $item['labelY'], $item['labelAngle']],
                'quadrantLabel' => [
                    $item['quadrantLabelX'],
                    $item['quadrantLabelY'],
                    $item['quadrantLabelAngle']
                ]
            ]));

        } catch (\Exception $e) {
            error_log("Error in SVG calculations: " . $e->getMessage());
            // Keep default 0 values on error
        }
    }
}