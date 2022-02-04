<?php

declare(strict_types = 1);

namespace App\Event;

use App\Entity\Student;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The student.deleted event is dispatched each time a student is created in the system.
 *
 * @package App\Event
 */
class StudentDeletedEvent extends Event
{
    public const NAME = 'student.deleted';

    protected Student $student;
    protected int $id;

    public function __construct(Student $student, int $id)
    {
        $this->student = $student;
        $this->id = $id;
    }

    public function getStudent() : Student
    {
        return $this->student;
    }

    public function getId() : int
    {
        return $this->id;
    }
}
