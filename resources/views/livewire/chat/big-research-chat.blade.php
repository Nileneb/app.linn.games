<?php

use App\Services\ChatService;
use Illuminate\Support\Facades\Auth;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Livewire\Volt\Component;

new class extends Component {
    public string $message = '';
    public bool   $loading = false;

    public function sendMessage(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $workspaceId = Auth::user()?->activeWorkspaceId();
        $userId      = Auth::id();

        if ($workspaceId === null || $userId === null) {
            return;
        }

        $userMessage   = $this->message;
        $this->message = '';

        // User-Nachricht in DB speichern; Antwort kommt per SSE
        app(ChatService::class)->saveUserMessage($workspaceId, $userId, $userMessage);

        $this->loading = true;

        // JS-Seite öffnet fetch()-Stream gegen /chat/stream
        $this->dispatch('start-sse-chat', message: $userMessage);
    }

    /**
     * Wird vom JS aufgerufen, sobald der SSE-Stream vollständig ist.
     * Speichert die akkumulierte Antwort des Agenten in der DB.
     */
    public function finalizeResponse(string $content): void
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();
        $userId      = Auth::id();

        if ($workspaceId !== null && $userId !== null) {
            app(ChatService::class)->saveAssistantMessage($workspaceId, $userId, $content);
        }

        $this->loading = false;
        $this->dispatch('chat-updated');
    }

    /**
     * Wird vom JS aufgerufen, wenn der SSE-Stream mit einem Fehler abbricht.
     */
    public function markStreamError(string $error): void
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();
        $userId      = Auth::id();

        if ($workspaceId !== null && $userId !== null) {
            app(ChatService::class)->saveAssistantMessage(
                $workspaceId,
                $userId,
                '❌ Fehler: ' . $error,
            );
        }

        $this->loading = false;
        $this->dispatch('chat-updated');
    }

    public function clearHistory(): void
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();

        if ($workspaceId !== null) {
            app(ChatService::class)->clearMessages($workspaceId, Auth::id());
        }

        $this->loading = false;
    }

    public function renderMarkdown(string $content): string
    {
        static $converter = null;
        if ($converter === null) {
            $converter = new GithubFlavoredMarkdownConverter([
                'html_input'         => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }
        return $converter->convert($content)->getContent();
    }

    public function with(): array
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();

        return [
            'chatMessages' => $workspaceId
                ? app(ChatService::class)->getMessages($workspaceId, Auth::id())
                : collect(),
        ];
    }
}; ?>

<div class="flex h-full flex-col">
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Dashboard Chat') }}</h3>
        @if($chatMessages->isNotEmpty())
            <button wire:click="clearHistory" wire:confirm="{{ __('Chat-Verlauf wirklich löschen?') }}"
                    class="inline-flex items-center gap-1 rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 hover:border-red-300 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30">
                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                {{ __('Verlauf löschen') }}
            </button>
        @endif
    </div>

    {{-- Messages --}}
    <div id="chat-scroll-container" class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
        @forelse($chatMessages as $msg)
            @if($msg->role === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                        <div class="whitespace-pre-wrap break-words">{{ $msg->content }}</div>
                        <div class="mt-1 text-[10px] opacity-50">{{ $msg->created_at?->format('H:i') }}</div>
                    </div>
                </div>
            @else
                <div class="flex justify-start gap-2">
                    {{-- Bot-Avatar --}}
                    <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                        <x-app-logo-icon class="h-3.5 w-3.5 fill-current" />
                    </div>
                    <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100">
                        <div class="chat-markdown break-words">{!! $this->renderMarkdown($msg->content) !!}</div>
                        <div class="mt-1 text-[10px] opacity-50">{{ $msg->created_at?->format('H:i') }}</div>
                    </div>
                </div>
            @endif
        @empty
            <div class="flex h-full items-center justify-center text-sm text-zinc-400 dark:text-zinc-500">
                {{ __('Stelle eine Frage …') }}
            </div>
        @endforelse

        {{-- SSE-Streaming-Bubble: zeigt Chunks in Echtzeit an --}}
        @if($loading)
            <div class="flex justify-start gap-2">
                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                    <x-app-logo-icon class="h-3.5 w-3.5 fill-current" />
                </div>
                <div class="max-w-[85%] rounded-lg bg-zinc-100 px-3 py-2 text-sm text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100 min-h-[2rem]">
                    {{-- JS schreibt Chunks direkt in #streaming-text --}}
                    <span id="streaming-text" class="whitespace-pre-wrap break-words"></span><span id="streaming-cursor" class="animate-pulse ml-0.5 text-zinc-400 dark:text-zinc-500">▍</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Input --}}
    <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
        <form wire:submit="sendMessage" class="flex gap-2">
            <input
                wire:model="message"
                type="text"
                placeholder="{{ __('Nachricht eingeben …') }}"
                autocomplete="off"
                wire:loading.attr="disabled"
                wire:target="sendMessage"
                class="flex-1 rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-400/50 disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:border-zinc-500 dark:focus:ring-zinc-600/50"
            />
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="sendMessage"
                class="inline-flex items-center justify-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                    <path d="M3.105 2.288a.75.75 0 0 0-.826.95l1.414 4.926A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95l14.095-5.146a.75.75 0 0 0 0-1.413L3.105 2.288Z" />
                </svg>
            </button>
        </form>
    </div>
</div>

@script
<script>
    const container = document.getElementById('chat-scroll-container');
    let activeAbortController = null;

    function scrollToBottom() {
        if (container) container.scrollTop = container.scrollHeight;
    }

    scrollToBottom();

    $wire.on('chat-updated', () => {
        setTimeout(scrollToBottom, 50);
    });

    /**
     * SSE-Streaming via fetch() ReadableStream.
     *
     * Ausgelöst wenn sendMessage() $this->dispatch('start-sse-chat', message: ...) aufruft.
     * Chunks kommen als SSE: data: {"chunk":"c","index":0,"type":"content"}\n\n
     * Abschluss:           data: {"status":"done","type":"complete"}\n\n
     * Fehler:              data: {"status":"error","error":"...","type":"error"}\n\n
     */
    $wire.on('start-sse-chat', async ({ message }) => {
        // Laufende Verbindung abbrechen falls vorhanden
        if (activeAbortController) {
            activeAbortController.abort();
        }
        activeAbortController = new AbortController();

        scrollToBottom();

        const streamingText   = document.getElementById('streaming-text');
        const streamingCursor = document.getElementById('streaming-cursor');
        let accumulated = '';

        try {
            const response = await fetch('{{ route('chat.stream') }}', {
                method: 'POST',
                signal: activeAbortController.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Accept': 'text/event-stream',
                },
                body: JSON.stringify({ message }),
            });

            if (! response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const reader  = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer    = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });

                // Zeilen trennen; letztes unvollständiges Segment im Buffer behalten
                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                for (const line of lines) {
                    if (! line.startsWith('data: ')) continue;

                    try {
                        const data = JSON.parse(line.slice(6));

                        if (data.type === 'content' && data.chunk !== undefined) {
                            accumulated += data.chunk;
                            if (streamingText) streamingText.textContent = accumulated;
                            scrollToBottom();
                        } else if (data.type === 'complete') {
                            if (streamingCursor) streamingCursor.style.display = 'none';
                            // Einmaliger Livewire-Aufruf am Ende — speichert in DB & rendert neu
                            await $wire.finalizeResponse(accumulated);
                        } else if (data.type === 'error') {
                            await $wire.markStreamError(data.error ?? 'Unbekannter Fehler');
                        }
                    } catch (_parseError) {
                        // Fehlerhafte SSE-Zeile überspringen
                    }
                }
            }
        } catch (fetchError) {
            if (fetchError.name !== 'AbortError') {
                await $wire.markStreamError(fetchError.message ?? 'Verbindungsfehler');
            }
        } finally {
            activeAbortController = null;
        }
    });
</script>
@endscript
