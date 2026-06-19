<?php

namespace CleaniqueCoders\LaravelMcpKit\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Scaffold a new prompt pre-shaped as a read-first, human-gated runbook — the
 * kit's default for any operator workflow.
 */
#[AsCommand(
    name: 'mcp-kit:make-prompt',
    description: 'Create a new MCP Kit prompt (read-first runbook) class'
)]
class MakePromptCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Prompt';

    protected function getStub(): string
    {
        return file_exists($custom = $this->laravel->basePath('stubs/mcp-kit-prompt.stub'))
            ? $custom
            : __DIR__.'/../../stubs/mcp-kit-prompt.stub';
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Prompts";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the prompt already exists'],
        ];
    }

    /**
     * @param  string  $name
     *
     * @throws FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $base = class_basename($name);
        $stripped = (string) Str::of($base)->beforeLast('Prompt');
        $stripped = $stripped !== '' ? $stripped : $base;

        return str_replace(
            ['{{ name }}', '{{ title }}'],
            [Str::snake($stripped), Str::headline($stripped)],
            $stub,
        );
    }
}
