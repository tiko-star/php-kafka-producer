<?php

declare(strict_types = 1);

namespace App\Handler;

use App\Command\ProduceRecordCommand;
use RdKafka\Producer;

class ProduceRecordHandler
{
    protected Producer $producer;

    public function __construct(Producer $producer)
    {
        $this->producer = $producer;
    }

    public function handleProduceRecordCommand(ProduceRecordCommand $command) : void
    {
        $this->produce($command->getRecord(), $command->getKey());
    }

    /**
     * Produce given record into Kafka topic under specified key.
     *
     * @param string $record
     * @param string $key
     */
    protected function produce(string $record, string $key) : void
    {
        $topic = $this->producer->newTopic('students');
        $topic->producev(RD_KAFKA_PARTITION_UA, 0, $record, $key);
        $this->producer->poll(0);

        $result = RD_KAFKA_RESP_ERR_NO_ERROR;

        for ($flushRetries = 0; $flushRetries < 10; $flushRetries++) {
            $result = $this->producer->flush(10000);
            if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
                break;
            }
        }

        if (RD_KAFKA_RESP_ERR_NO_ERROR !== $result) {
            throw new \RuntimeException('Was unable to flush, messages might be lost!');
        }
    }
}
