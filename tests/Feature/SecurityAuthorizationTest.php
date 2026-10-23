<?php

namespace Tests\Feature;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Thesis;
use App\Models\ThesisArchive;
use App\Models\User;
use App\Policies\DefenseSchedulePolicy;
use App\Policies\ThesisArchivePolicy;
use App\Policies\ThesisPolicy;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    public function test_public_registration_rejects_a_requested_role(): void
    {
        $validator = Validator::make(
            ['role_id' => 1],
            (new RegisterRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role_id', $validator->errors()->messages());
    }

    public function test_students_cannot_schedule_defenses_or_change_thesis_status(): void
    {
        $student = new User();
        $student->setAttribute('id', 10);
        $student->setRelation('role', new Role(['slug' => 'student']));

        $thesis = new Thesis(['status' => Thesis::STATUS_SUBMITTED]);
        $thesis->setRelation('student', new Student(['user_id' => 11]));
        $thesis->setRelation('supervisor', new Supervisor());

        $this->assertFalse((new DefenseSchedulePolicy())->create($student));
        $this->assertFalse((new ThesisPolicy())->changeStatus($student, $thesis));
    }

    public function test_thesis_visibility_is_limited_to_its_student_and_supervisor(): void
    {
        $student = new User();
        $student->setAttribute('id', 10);
        $student->setRelation('role', new Role(['slug' => 'student']));

        $thesis = new Thesis();
        $thesis->setRelation('student', new Student(['user_id' => 10]));
        $thesis->setRelation('supervisor', new Supervisor(['user_id' => 20]));
        $policy = new ThesisPolicy();

        $this->assertTrue($policy->view($student, $thesis));

        $otherStudent = new User();
        $otherStudent->setAttribute('id', 30);
        $otherStudent->setRelation('role', new Role(['slug' => 'student']));

        $this->assertFalse($policy->view($otherStudent, $thesis));
    }

    public function test_private_archives_are_not_visible_to_students(): void
    {
        $student = new User();
        $student->setAttribute('id', 30);
        $student->setRelation('role', new Role(['slug' => 'student']));

        $thesis = new Thesis(['department_id' => 7]);
        $archive = new ThesisArchive(['visibility' => ThesisArchive::VISIBILITY_PRIVATE]);
        $archive->setRelation('thesis', $thesis);
        $policy = new ThesisArchivePolicy();

        $this->assertFalse($policy->view($student, $archive));

        $archive->visibility = ThesisArchive::VISIBILITY_PUBLIC;
        $this->assertTrue($policy->view($student, $archive));
    }
}
