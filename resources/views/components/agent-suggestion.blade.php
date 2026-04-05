@props(['result'])

@if ($result?->content)
    <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-950/20">
        <div class="mb-2 flex items-center gap-2">
            <span class="text-xs font-semibold text-blue-700 dark:text-blue-400">🤖 KI-Vorschlag</span>
            <span class="text-xs text-neutral-400">{{ $result->created_at->diffForHumans() }}</span>
        </div>
        <div class="prose prose-sm max-h-96 max-w-none overflow-auto text-neutral-700 dark:prose-invert dark:text-neutral-300">
            {!! Illuminate\Support\Str::markdown($result->content, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]) !!}
        </div>
    </div>
@endif
