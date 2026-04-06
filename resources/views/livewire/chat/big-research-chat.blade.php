<?php

use App\Jobs\ProcessChatMessageJob;
use App\Services\ChatService;
use Illuminate\Support\Facades\Auth;
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

        if ($workspaceId === null) {
            return;
        }

        $userMessage   = $this->message;
        $this->message = '';

        $userMsg = app(ChatService::class)->saveUserMessage($workspaceId, Auth::id(), $userMessage);

        $this->loading = true;

        ProcessChatMessageJob::dispatch(
            $userMsg->id,
            $workspaceId,
            Auth::id(),
            [
                'source'       => 'dashboard_chat',
                'user_id'      => Auth::id(),
                'workspace_id' => $workspaceId,
            ],
        );

        $this->dispatch('chat-loading-started');
    }

    public function clearHistory(): void
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();

        if ($workspaceId !== null) {
            app(ChatService::class)->clearMessages($workspaceId, Auth::id());
        }

        $this->loading = false;
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
            <button wire:click="clearHistory" wire:confirm="{{ __('Chat-Verlauf wirklich löschen?') }}" class="text-xs text-zinc-400 hover:text-red-500 dark:hover:text-red-400">
                {{ __('Verlauf löschen') }}
            </button>
        @endif
    </div>

    {{-- Messages --}}
    <div id="chat-scroll-container" class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
        @forelse($chatMessages as $msg)
            <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm {{ $msg->role === 'user'
                    ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                    : 'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' }}">
                    <div class="whitespace-pre-wrap break-words">{{ $msg->content }}</div>
                    <div class="mt-1 text-[10px] opacity-50">{{ $msg->created_at?->format('H:i') }}</div>
                </div>
            </div>
        @empty
            <div class="flex h-full items-center justify-center text-sm text-zinc-400 dark:text-zinc-500">
                {{ __('Stelle eine Frage …') }}
            </div>
        @endforelse

        {{-- Loading indicator --}}
        @if($loading)
            <div class="flex justify-start">
                <div class="max-w-[85%] rounded-lg bg-zinc-100 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                    <span class="inline-flex items-center gap-1">
                        <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ __('Denkt nach …') }}
                    </span>
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
                class="flex-1 rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                @disabled($loading)
            />
            <button
                type="submit"
                @disabled($loading)
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
    let timeoutHandle = null;

    function scrollToBottom() {
        if (container) container.scrollTop = container.scrollHeight;
    }

    scrollToBottom();

    // Subscribe once to the private WebSocket channel
    const workspaceId = '{{ Auth::user()?->activeWorkspaceId() }}';
    const userId = {{ Auth::id() }};
    window.Echo.private(`chat.${workspaceId}.${userId}`)
        .listen('.chat.response', () => {
            clearTimeout(timeoutHandle);
            $wire.set('loading', false);
            setTimeout(scrollToBottom, 300);
        });

    $wire.on('chat-updated', () => {
        clearTimeout(timeoutHandle);
        setTimeout(scrollToBottom, 50);
    });

    $wire.on('chat-loading-started', () => {
        scrollToBottom();
        // 90s frontend failsafe — stops spinner if queue-worker is down or response never arrives
        timeoutHandle = setTimeout(() => {
            $wire.set('loading', false);
        }, 90000);
    });
</script>
@endscript
