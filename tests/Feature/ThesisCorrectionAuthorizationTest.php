<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Thesis;
use App\Models\ThesisCorrection;
use App\Models\User;
use App\Policies\ThesisCorrectionPolicy;
use Tests\TestCase;

class ThesisCorrectionAuthorizationTest extends TestCase
{
    public function test_only_thesis_participants_can_view_correction_history(): void
    {
        $owner = new User();
        $owner->setAttribute('id', 10);
        $owner->setRelation('role', new Role(['slug' => 'student']));

        $otherStudent = new User();
        $otherStudent->setAttribute('id', 20);
        $otherStudent->setRelation('role', new Role(['slug' => 'student']));

        $thesis = new Thesis();
        $thesis->setRelation('student', new Student(['user_id' => 10]));
        $thesis->setRelation('supervisor', new Supervisor(['user_id' => 30]));

        $correction = new ThesisCorrection();
        $correction->setRelation('thesis', $thesis);
        $policy = new ThesisCorrectionPolicy();

        $this->assertTrue($policy->view($owner, $correction));
        $this->assertFalse($policy->view($otherStudent, $correction));
    }
}
