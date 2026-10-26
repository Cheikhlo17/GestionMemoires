<?php

return [
    'grade_mentions' => json_decode(
        (string) env('ACADEMIC_GRADE_MENTIONS', '[]'),
        true,
        512,
        JSON_THROW_ON_ERROR
    ),
];
