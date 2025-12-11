<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function getJobName(): string
    {
        $payload = $this->payload;

        if (isset($payload['displayName'])) {
            return class_basename($payload['displayName']);
        }

        return 'Unknown Job';
    }

    public function getFullJobName(): string
    {
        return $this->payload['displayName'] ?? 'Unknown Job';
    }

    public function getShortException(): string
    {
        $lines = explode("\n", $this->exception ?? '');

        return $lines[0] ?? 'Unknown error';
    }

    public function getMaxTries(): ?int
    {
        return $this->payload['maxTries'] ?? null;
    }

    public function getBackoff(): ?string
    {
        return $this->payload['backoff'] ?? null;
    }

    public function getJobData(): ?array
    {
        return $this->payload['data'] ?? null;
    }

    public function getSerializedCommand(): ?string
    {
        return $this->payload['data']['command'] ?? null;
    }

    public function getDeserializedCommand(): ?array
    {
        $serialized = $this->getSerializedCommand();

        if (! $serialized) {
            return null;
        }

        try {
            $command = unserialize($serialized);

            return $this->extractCommandDetails($command);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function extractCommandDetails(object $command): array
    {
        $details = [
            'class' => get_class($command),
            'properties' => [],
        ];

        $reflection = new \ReflectionObject($command);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($command);

            if ($value instanceof \Illuminate\Contracts\Database\ModelIdentifier) {
                $details['properties'][$property->getName()] = [
                    'type' => 'model',
                    'class' => $value->class,
                    'id' => $value->id,
                    'connection' => $value->connection,
                ];
            } elseif (is_object($value)) {
                $details['properties'][$property->getName()] = [
                    'type' => 'object',
                    'class' => get_class($value),
                ];
            } elseif (is_array($value)) {
                $details['properties'][$property->getName()] = [
                    'type' => 'array',
                    'count' => count($value),
                ];
            } else {
                $details['properties'][$property->getName()] = $value;
            }
        }

        return $details;
    }
}
