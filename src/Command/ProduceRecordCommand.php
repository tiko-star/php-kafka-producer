<?php

declare(strict_types = 1);

namespace App\Command;

class ProduceRecordCommand
{
    protected string $record;

    protected string $key;

    public function __construct(string $record, string $key)
    {
        $this->record = $record;
        $this->key = $key;
    }

    public function getRecord() : string
    {
        return $this->record;
    }

    public function getKey() : string
    {
        return $this->key;
    }
}
