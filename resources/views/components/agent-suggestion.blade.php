@props(['result'])

@if ($result?->content)
    <details class="group rounded-lg border border-blue-200 dark:border-blue-800" open>
        <summary class="flex cursor-pointer list-none items-center justify-between bg-blue-50/50 px-4 py-3 dark:bg-blue-950/20">
            <span class="flex items-center gap-2">
                <span class="text-xs font-semibold text-blue-700 dark:text-blue-400">🤖 KI-Vorschlag</span>
                <span class="text-xs text-neutral-400">{{ $result->created_at->diffForHumans() }}</span>
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                 class="size-4 text-blue-400 transition-transform group-open:rotate-180">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </summary>
        <div class="prose prose-sm max-h-96 max-w-none overflow-auto px-4 py-3 text-neutral-700 dark:prose-invert dark:text-neutral-300">
            {!! Illuminate\Support\Str::markdown($result->content, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]) !!}
        </div>
    </details>
@endif
