<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MCP Settings — Workbench</title>
    {{-- Build-free styling for the harness. The publishable UI (issue #16) uses Flux. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">
    <div class="mx-auto max-w-4xl px-4 py-10">
        <header class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">MCP Settings</h1>
                <p class="text-sm text-gray-500">
                    Workbench preview of the MCP Configuration UI ·
                    signed in as <span class="font-medium">{{ auth()->user()?->email }}</span>
                </p>
            </div>
            <a href="/" class="text-sm text-indigo-600 hover:underline">← back</a>
        </header>

        @livewire('mcp-settings')
    </div>

    @livewireScripts
</body>
</html>
