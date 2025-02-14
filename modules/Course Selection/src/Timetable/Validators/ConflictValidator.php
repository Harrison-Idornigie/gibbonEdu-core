<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Validators;

/**
 * Validator that looks for timetabling conflicts
 *
 * @version v14
 * @since   4th May 2017
 */
class ConflictValidator extends Validator
{
    /**
     * @param   object  &$node
     * @param   int     &$treeDepth
     * @return  bool
     */
    public function validateNode(&$node, $treeDepth) : bool
    {
        $this->performance['nodeValidations']++;

        $node->number = $this->performance['nodeValidations'];

        // Flag values in this node if there are any full classes
        foreach ($node->values as $key => &$value) {
            if ($this->environment->getEnrolmentCount($value['gibbonCourseClassID']) >= $this->settings->maximumStudents) {
                // FLAGGED: Incomplete
                $className = $this->environment->getClassValue($value['gibbonCourseClassID'], 'className');
                $this->createFlag($value, 'Full', 'Full class: '.$className);
            }
        }

        $gibbonPersonID = current($node->values)['gibbonPersonID'];
        $enrolmentTTDays = $this->environment->getStudentValue($gibbonPersonID, 'ttDays');

        

        // Put together a set of conflicting classes
        $nodeIndex = -1;
        $node->conflicts = array_reduce($node->values, function($conflicts, $item) use (&$node, &$enrolmentTTDays, &$nodeIndex) {
            $nodeIndex++;

            if (!empty($item['flag'])) return $conflicts; // Don't conflict with courses already ruled out

            $item['nodeIndex'] = $nodeIndex;

            // Look for conflicts with pre-enrolled classes
            if ($this->inArrayWithArray($item['ttDays'], $enrolmentTTDays)) {
                $item['blocked'] = true;
                $conflicts[$item['gibbonCourseClassID']] = $item;
            }

            // Look for other classes that have conflicting TT days as this one
            if (is_array($item['ttDays'])) {
                foreach ($node->values as $otherIndex => $other) {
                    if ($item['gibbonCourseClassID'] == $other['gibbonCourseClassID']) continue;

                    if ($this->inArrayWithArray($item['ttDays'], $other['ttDays'])) {
                        @$node->values[$nodeIndex]['conflicts']++;
                        @$item['conflicts']++;
                        $conflicts[$item['gibbonCourseClassID']] = $item;
                    }
                }
            }

            return $conflicts;
        }, array());

        return (count($node->conflicts) <= $this->settings->timetableConflictTollerance);
    }


    public function resolveConflicts(&$node)
    {
        // print_r($node);

        if (empty($node->conflicts) || count($node->conflicts) == 0) return;

        $gibbonPersonID = current($node->values)['gibbonPersonID'];
        $enrolmentTTDays = $this->environment->getStudentValue($gibbonPersonID, 'ttDays');

        // Group the conflicts by TT Column Row (Period)
        $groupedByRow = collect($node->conflicts)
            ->map(function ($item) {
                // Pull in any additional data needed for resolving conflicts  
                $item['priority'] = $this->environment->getClassValue($item['gibbonCourseClassID'], 'priority');
                $item['className'] = $item['courseNameShort'].'.'.$item['classNameShort'];
                $item['ttDaysGroup'] = implode('-', $item['ttDays']);

                return $item;
            })->groupBy('ttColumnRow');

        // Not resolving? Just flag them.
        if ($this->settings->autoResolveConflicts == 'N') {
            foreach ($groupedByRow as $groupedConflicts) {
                $groupedConflicts = collect($groupedConflicts);

                foreach ($groupedConflicts as $item) {
                    $itemObject = &$node->values[$item['nodeIndex']];
                    $classNames = $groupedConflicts->whereNotIn('gibbonCourseClassID', [$item['gibbonCourseClassID']])->pluck('className')->implode(', ');

                    $this->createFlag($itemObject, 'Conflict', 'Conflicts with '.$classNames);
                }
            }
            return;
        }
        

        // Sub-group by TT Days Group (Days + Periods), select the top priority for each, then order by priority
        // and start 'accepting' courses in order if they don't conflict with existing accepted ones
        foreach ($groupedByRow as $groupedConflicts) {
            $groupedByDay = collect($groupedConflicts)
                ->groupBy('ttDaysGroup')
                ->map(function ($item) {
                    return collect($item)->sortBy('priority');
                });

            // Flag the non-top-priority classes
            $groupedByDay
                ->map(function ($item) {
                    return $item->slice(1)->map(function ($subItem) use ($item) {
                        $subItem['resolvedWith'] = $item->first()['className'] ?? 'Unknown';
                        return $subItem;
                    });
                })
                ->flatten(1)
                ->filter()
                ->each(function ($item) use (&$node) {
                    // FLAGGED resolved with higher-priority class
                    $itemObject = &$node->values[$item['nodeIndex']];
                    $this->createFlag($itemObject, 'Conflict', 'Conflicts with higher-priority '.$item['resolvedWith']);
                });

            // Sort the remaining classes by priority + conflicts
            $groupedByDay = $groupedByDay
                ->map(function ($item) {
                    return $item->first();
                })
                ->sortBy('conflicts')
                ->sortBy('priority')
                ->values();

            $keepNodes = collect();

            foreach ($groupedByDay as $item) {
                $itemObject = &$node->values[$item['nodeIndex']];
                $itemObject['className'] = $item['className'];

                if ($this->inArrayWithArray($item['ttDays'], $enrolmentTTDays)) {
                    // FLAGGED conflicts with existing enrolment
                    $this->createFlag($itemObject, 'Conflict', 'Conflicts with existing enrolment');
                } else {
                    $keepThisNode = true;
                    foreach ($keepNodes as $keepIndex => $keepObject) {
                        // FLAGGED if this conflicts with a valid, higher-priority course
                        if ($this->inArrayWithArray($item['ttDays'], $keepObject['ttDays'])) {
                            $classNames = $keepNodes->pluck('className')->implode(', ');
                            $this->createFlag($itemObject, 'Conflict', 'Resolved with '.$classNames);
                            $keepThisNode = false;
                        }
                    }

                    // Keep this class if it has no conflicts (resolves cross-conflicts in order)
                    if ($keepThisNode) {
                        $keepNodes[$item['nodeIndex']] = $itemObject;
                    }
                }
            }
        }
    }

    protected function createFlag(&$value, $flag, $reason = '')
    {
        $value['flag'] = $flag;
        $value['reason'] = $reason;
    }

    protected function removeFlag(&$value)
    {
        $value['flag'] = null;
        $value['reason'] = null;
    }

    protected function inArrayWithArray(array $needle, array $haystack) {
        if (empty($needle) || empty($haystack)) return false;

        foreach ($needle as $value) {
            if (in_array($value, $haystack, true)) return true;
        }

        return false;
    }
}
