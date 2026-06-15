<?php

namespace CleaniqueCoders\LaravelMcpKit\Enums;

/**
 * The lifecycle of a task. Kept deliberately small so the training can
 * focus on MCP mechanics, not domain modelling.
 */
enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
