<?php

namespace CleaniqueCoders\LaravelMcpKit\Models;

use CleaniqueCoders\LaravelMcpKit\Database\Factories\TaskFactory;
use CleaniqueCoders\LaravelMcpKit\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A task in the demo MCP server.
 *
 * Note the dual-key idea borrowed from the production reference: the
 * auto-increment `id` stays internal, while the public `uuid` is what
 * every MCP tool accepts and returns. Agents never see the integer id.
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property string|null $assignee
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'mcp_kit_tasks';

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'status',
        'assignee',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
    ];

    /**
     * Generate a uuid for the `uuid` column (not the primary key, which
     * stays an auto-increment integer).
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }
}
