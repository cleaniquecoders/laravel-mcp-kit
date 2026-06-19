<?php

use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Tools\ListMyAbilitiesTool;
use CleaniqueCoders\LaravelMcpKit\Tools\WhoAmITool;

it('lets any authenticated user call whoami, even with no abilities', function () {
    TaskServer::actingAs(nobody())
        ->tool(WhoAmITool::class)
        ->assertOk()
        ->assertSee('"authenticated":true');
});

it('reports the abilities an admin holds in whoami', function () {
    TaskServer::actingAs(admin())
        ->tool(WhoAmITool::class)
        ->assertOk()
        ->assertSee('mcp-kit.view-logs')
        ->assertSee('mcp-kit.manage-tokens');
});

it('rejects whoami when there is no authenticated user', function () {
    TaskServer::tool(WhoAmITool::class)
        ->assertHasErrors();
});

it('lists every ability with a granted flag for the user', function () {
    TaskServer::actingAs(granted(['view-logs']))
        ->tool(ListMyAbilitiesTool::class)
        ->assertOk()
        ->assertSee('"key":"view-logs"')
        ->assertSee('"granted":true');
});

it('shows zero granted abilities for a user with no grants', function () {
    TaskServer::actingAs(nobody())
        ->tool(ListMyAbilitiesTool::class)
        ->assertOk()
        ->assertSee('"granted_count":0');
});

it('never leaks the internal integer id from whoami', function () {
    TaskServer::actingAs(admin())
        ->tool(WhoAmITool::class)
        ->assertOk()
        ->assertDontSee('"id"');
});
