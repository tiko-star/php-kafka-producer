<?php

declare(strict_types = 1);

namespace App\Event;

use App\Entity\Student;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The student.created event is dispatched each time a student is created in the system.
 *
 * @package App\Event
 */
class StudentCreatedEvent extends Event
{
    public const NAME = 'student.created';

    protected Student $student;

    public function __construct(Student $student)
    {
        $this->student = $student;
    }

    public function getStudent() : Student
    {
        return $this->student;
    }
}
