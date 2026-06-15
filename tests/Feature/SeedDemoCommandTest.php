<?php

use CleaniqueCoders\LaravelMcpKit\Models\Task;

it('seeds demo tasks', function () {
    $this->artisan('mcp-kit:demo')
        ->assertSuccessful();

    expect(Task::count())->toBe(5);
});

it('wipes existing tasks with --fresh', function () {
    Task::factory()->count(3)->create();

    $this->artisan('mcp-kit:demo', ['--fresh' => true])
        ->assertSuccessful();

    // 3 old ones deleted, 5 seeded.
    expect(Task::count())->toBe(5);
});
