<div class="space-y-6">
    {{-- Runtime toggle --}}
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">MCP server</h2>
                <p class="text-sm text-gray-500">Turn the server on or off at runtime — no redeploy.</p>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                {{ $enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $enabled ? 'Enabled' : 'Disabled' }}
            </span>
        </div>

        @unless ($master)
            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                The env master switch <code>MCP_KIT_ENABLED</code> is off, so MCP stays disabled regardless of this toggle.
            </p>
        @endunless

        <div class="mt-4 flex gap-3">
            @if ($enabled)
                <button wire:click="disable" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Disable MCP
                </button>
            @else
                <button wire:click="enable" @disabled(! $master)
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                    Enable MCP
                </button>
            @endif
        </div>
    </section>

    {{-- Health --}}
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">Health</h2>
        <p class="text-sm text-gray-500">Reachability of the core infrastructure and app-defined checks.</p>
        <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($health as $name => $healthy)
                <div class="rounded-lg border border-gray-100 p-3">
                    <dt class="text-xs uppercase tracking-wide text-gray-400">{{ $name }}</dt>
                    <dd class="mt-1 text-sm font-medium {{ $healthy ? 'text-green-700' : 'text-red-700' }}">
                        {{ $healthy ? '✓ healthy' : '✗ down' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Effective configuration (read-only) --}}
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">Effective configuration</h2>
        <p class="text-sm text-gray-500">Read-only — the source of truth is <code>.env</code> / <code>config/mcp-kit.php</code>.</p>
        <dl class="mt-4 divide-y divide-gray-100">
            @foreach ($config as $label => $value)
                <div class="flex justify-between gap-4 py-2 text-sm">
                    <dt class="text-gray-500">{{ $label }}</dt>
                    <dd class="text-right font-mono text-gray-900">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Registered tools --}}
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">Registered tools ({{ count($tools) }})</h2>
        <p class="text-sm text-gray-500">What an agent can call. Package-gated (Tier-2) tools are flagged.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($tools as $tool)
                <span class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium
                    {{ $tool['gated'] ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-700' }}">
                    {{ $tool['name'] }}
                    @if ($tool['gated'])<span class="text-purple-500">·gated</span>@endif
                </span>
            @endforeach
        </div>
    </section>

    {{-- Abilities --}}
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">Abilities</h2>
        <p class="text-sm text-gray-500">The gate each tool checks — defined by the host app's permission system.</p>
        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($abilities as $key => $ability)
                <div class="flex justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 text-sm">
                    <span class="text-gray-500">{{ $key }}</span>
                    <span class="font-mono text-gray-900">{{ $ability }}</span>
                </div>
            @endforeach
        </div>
    </section>
</div>
