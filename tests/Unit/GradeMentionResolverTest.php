<?php

namespace Tests\Unit;

use App\Support\GradeMentionResolver;
use LogicException;
use Tests\TestCase;

class GradeMentionResolverTest extends TestCase
{
    public function test_it_uses_only_configured_grade_bands(): void
    {
        config()->set('academic.grade_mentions', [
            ['min' => 14, 'max' => 20, 'label' => 'Institutional mention'],
        ]);

        $this->assertSame('Institutional mention', GradeMentionResolver::forGrade('16.50'));
        $this->assertNull(GradeMentionResolver::forGrade(12));
    }

    public function test_it_returns_no_mention_until_institutional_bands_are_configured(): void
    {
        config()->set('academic.grade_mentions', []);

        $this->assertNull(GradeMentionResolver::forGrade(18));
    }

    public function test_it_rejects_overlapping_configured_bands(): void
    {
        config()->set('academic.grade_mentions', [
            ['min' => 10, 'max' => 15, 'label' => 'First'],
            ['min' => 14, 'max' => 20, 'label' => 'Second'],
        ]);

        $this->expectException(LogicException::class);

        GradeMentionResolver::forGrade(12);
    }
}
