<?php

namespace CleaniqueCoders\LaravelMcpKit\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Scaffold a new resource pre-wired with the kit's auth check (a resource is
 * read-only context, but it is still gated on the token holder — never an open
 * door).
 */
#[AsCommand(
    name: 'mcp-kit:make-resource',
    description: 'Create a new auth-gated MCP Kit resource class'
)]
class MakeResourceCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Resource';

    protected function getStub(): string
    {
        return file_exists($custom = $this->laravel->basePath('stubs/mcp-kit-resource.stub'))
            ? $custom
            : __DIR__.'/../../stubs/mcp-kit-resource.stub';
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Resources";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the resource already exists'],
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
        $stripped = (string) Str::of($base)->beforeLast('Resource');
        $stripped = $stripped !== '' ? $stripped : $base;

        return str_replace(
            ['{{ name }}', '{{ title }}'],
            [Str::snake($stripped), Str::headline($stripped)],
            $stub,
        );
    }
}
