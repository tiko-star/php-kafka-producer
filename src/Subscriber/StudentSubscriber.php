<?php

declare(strict_types = 1);

namespace App\Subscriber;

use App\Command\ProduceRecordCommand;
use App\Entity\Student;
use App\Event\StudentCreatedEvent;
use App\Event\StudentDeletedEvent;
use App\Event\StudentUpdatedEvent;
use League\Tactician\CommandBus;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function json_encode;

class StudentSubscriber implements EventSubscriberInterface
{
    protected CommandBus $commandBus;

    public function __construct(CommandBus $commandBus)
    {
        $this->commandBus = $commandBus;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * ['eventName' => 'methodName']
     *  * ['eventName' => ['methodName', $priority]]
     *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
     *
     * The code must not depend on runtime state as it will only be called at compile time.
     * All logic depending on runtime state must be put into the individual methods handling the events.
     *
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents() : array
    {
        return [
            StudentCreatedEvent::NAME => 'onStudentCreated',
            StudentUpdatedEvent::NAME => 'onStudentUpdated',
            StudentDeletedEvent::NAME => 'onStudentDeleted',
        ];
    }

    /**
     * Produce created student into Kafka topic.
     *
     * @param \App\Event\StudentCreatedEvent $event
     */
    public function onStudentCreated(StudentCreatedEvent $event) : void
    {
        $student = $event->getStudent();
        $record = $this->createRecord($student, 'CREATED');

        $this->commandBus->handle(new ProduceRecordCommand($record, (string) $student->getId()));
    }

    /**
     * Produce updated student into Kafka topic.
     *
     * @param \App\Event\StudentUpdatedEvent $event
     */
    public function onStudentUpdated(StudentUpdatedEvent $event) : void
    {
        $student = $event->getStudent();
        $record = $this->createRecord($student, 'UPDATED');

        $this->commandBus->handle(new ProduceRecordCommand($record, (string) $student->getId()));
    }

    /**
     * Produce deleted student into Kafka topic.
     *
     * @param \App\Event\StudentDeletedEvent $event
     */
    public function onStudentDeleted(StudentDeletedEvent $event) : void
    {
        $student = $event->getStudent();
        $student->setId($event->getId());
        $record = $this->createRecord($student, 'DELETED');

        $this->commandBus->handle(new ProduceRecordCommand($record, (string) $student->getId()));
    }

    /**
     * Create Kafka record for the given student and action.
     *
     * @param \App\Entity\Student $student
     * @param string              $action
     *
     * @return string
     */
    protected function createRecord(Student $student, string $action) : string
    {
        return json_encode(['student' => $student, 'action' => $action]);
    }
}
