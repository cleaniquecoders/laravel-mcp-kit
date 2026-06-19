<?php

namespace CleaniqueCoders\LaravelMcpKit\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Scaffold a new tool that follows the kit's gate-first pattern — it extends
 * McpKitTool (auth → validate → act) rather than the bare laravel/mcp Tool, so
 * a new tool starts safe by default instead of as a back door.
 */
#[AsCommand(
    name: 'mcp-kit:make-tool',
    description: 'Create a new gate-first MCP Kit tool class'
)]
class MakeToolCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Tool';

    protected function getStub(): string
    {
        return file_exists($custom = $this->laravel->basePath('stubs/mcp-kit-tool.stub'))
            ? $custom
            : __DIR__.'/../../stubs/mcp-kit-tool.stub';
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Tools";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the tool already exists'],
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
        $stripped = (string) Str::of($base)->beforeLast('Tool');
        $snake = Str::snake($stripped !== '' ? $stripped : $base);

        return str_replace(
            ['{{ name }}', '{{ ability }}', '{{ title }}'],
            [$snake, $snake, Str::headline($stripped !== '' ? $stripped : $base)],
            $stub,
        );
    }
}
