<?php

namespace App\Support;

use LogicException;

class GradeMentionResolver
{
    public static function forGrade(int|float|string|null $grade): ?string
    {
        if ($grade === null) {
            return null;
        }

        if (!is_numeric($grade) || !is_finite((float) $grade) || (float) $grade < 0 || (float) $grade > 20) {
            throw new LogicException('A final grade must be a number between 0 and 20.');
        }

        $bands = config('academic.grade_mentions', []);
        if (!is_array($bands)) {
            throw new LogicException('The academic grade mention configuration must be a JSON array.');
        }

        $ranges = [];
        foreach ($bands as $band) {
            if (!is_array($band)
                || !isset($band['min'], $band['max'], $band['label'])
                || !is_numeric($band['min'])
                || !is_numeric($band['max'])
                || !is_string($band['label'])
                || trim($band['label']) === '') {
                throw new LogicException('Each academic grade mention must define numeric min/max bounds and a non-empty label.');
            }

            $minimum = (float) $band['min'];
            $maximum = (float) $band['max'];
            if ($minimum < 0 || $maximum > 20 || $minimum > $maximum) {
                throw new LogicException('Academic grade mention bounds must be ordered within the 0-20 grading scale.');
            }

            foreach ($ranges as $existingRange) {
                if ($minimum <= $existingRange['max'] && $maximum >= $existingRange['min']) {
                    throw new LogicException('Academic grade mention ranges must not overlap.');
                }
            }
            $ranges[] = [
                'min' => $minimum,
                'max' => $maximum,
                'label' => trim($band['label']),
            ];
        }

        foreach ($ranges as $range) {
            if ((float) $grade >= $range['min'] && (float) $grade <= $range['max']) {
                return $range['label'];
            }
        }

        return null;
    }
}
