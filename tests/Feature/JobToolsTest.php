<?php

use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Tools\ListFailedJobsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\QueueStatusTool;
use CleaniqueCoders\LaravelMcpKit\Tools\RetryFailedJobTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ScheduledTasksTool;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedFailedJob(string $connection = 'database'): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => $connection,
        'queue' => 'default',
        'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\SendReport', 'attempts' => 2, 'data' => []]),
        'exception' => "RuntimeException: kaboom in the worker\n#0 /app/Jobs/SendReport.php(10)",
        'failed_at' => now(),
    ]);

    return $uuid;
}

it('lists failed jobs by their uuid', function () {
    $uuid = seedFailedJob();

    TaskServer::actingAs(granted(['view-jobs']))
        ->tool(ListFailedJobsTool::class)
        ->assertOk()
        ->assertSee($uuid)
        ->assertSee('App\\\\Jobs\\\\SendReport')
        ->assertSee('kaboom in the worker');
});

it('blocks a user without the view-jobs ability', function () {
    TaskServer::actingAs(nobody())
        ->tool(ListFailedJobsTool::class)
        ->assertHasErrors();
});

it('never mislabels the integer id as a uuid on the legacy database failer driver', function () {
    // The legacy `database` provider (vs the default `database-uuids`) returns
    // the raw integer primary key as $job->id. Surface it honestly under `id`,
    // and the job's logical uuid (from the payload) under `uuid`.
    config()->set('queue.failed', ['driver' => 'database', 'database' => 'testing', 'table' => 'failed_jobs']);
    app()->forgetInstance('queue.failer');

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['uuid' => 'logical-job-uuid-123', 'displayName' => 'App\\Jobs\\X', 'attempts' => 1, 'data' => []]),
        'exception' => 'boom',
        'failed_at' => now(),
    ]);
    $intId = (string) DB::table('failed_jobs')->value('id');

    TaskServer::actingAs(granted(['view-jobs']))
        ->tool(ListFailedJobsTool::class)
        ->assertOk()
        ->assertSee('"id":"'.$intId.'"')
        ->assertSee('logical-job-uuid-123')
        ->assertDontSee('"uuid":"'.$intId.'"');
});

it('retries a failed job: re-dispatched and removed from the failed store', function () {
    $uuid = seedFailedJob();

    TaskServer::actingAs(granted(['manage-jobs']))
        ->tool(RetryFailedJobTool::class, ['id' => $uuid])
        ->assertOk()
        ->assertSee('re-dispatched');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(1);
});

it('errors when retrying an unknown job uuid', function () {
    TaskServer::actingAs(granted(['manage-jobs']))
        ->tool(RetryFailedJobTool::class, ['id' => 'not-a-real-uuid'])
        ->assertHasErrors();
});

it('blocks a viewer from retrying a job (retry is a write)', function () {
    $uuid = seedFailedJob();

    TaskServer::actingAs(granted(['view-jobs']))
        ->tool(RetryFailedJobTool::class, ['id' => $uuid])
        ->assertHasErrors();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

it('reports queue status', function () {
    seedFailedJob();

    TaskServer::actingAs(granted(['view-jobs']))
        ->tool(QueueStatusTool::class)
        ->assertOk()
        ->assertSee('"connection"')
        ->assertSee('"failed"');
});

it('lists scheduled tasks with their expression and next run', function () {
    app(Schedule::class)->call(fn () => null)->everyMinute();

    TaskServer::actingAs(granted(['view-system']))
        ->tool(ScheduledTasksTool::class)
        ->assertOk()
        ->assertSee('* * * * *');
});

it('blocks a user without view-system from listing scheduled tasks', function () {
    TaskServer::actingAs(nobody())
        ->tool(ScheduledTasksTool::class)
        ->assertHasErrors();
});
