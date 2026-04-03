<?php

use App\Jobs\ProcessChatMessageJob;
use App\Models\ChatMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string  $message            = '';
    public bool    $loading            = false;
    public ?string $pendingUserMsgId   = null;

    private function activeWorkspaceId(): ?string
    {
        return Auth::user()?->activeWorkspaceId();
    }

    public function getChatMessages(): Collection
    {
        $workspaceId = $this->activeWorkspaceId();

        if ($workspaceId === null) {
            return collect();
        }

        return ChatMessage::where('workspace_id', $workspaceId)
            ->where('user_id', Auth::id())
            ->orderBy('created_at')
            ->limit(50)
            ->get();
    }

    public function sendMessage(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $workspaceId = $this->activeWorkspaceId();

        if ($workspaceId === null) {
            return;
        }

        $userMessage = $this->message;
        $this->message = '';

        $userMsg = ChatMessage::create([
            'user_id'      => Auth::id(),
            'workspace_id' => $workspaceId,
            'role'         => 'user',
            'content'      => $userMessage,
        ]);

        $this->pendingUserMsgId = $userMsg->id;
        $this->loading          = true;

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

    public function checkForResponse(): void
    {
        if (! $this->loading || $this->pendingUserMsgId === null) {
            return;
        }

        $userMsg = ChatMessage::find($this->pendingUserMsgId);

        if ($userMsg === null) {
            $this->loading        = false;
            $this->pendingUserMsgId = null;
            return;
        }

        $hasResponse = ChatMessage::where('workspace_id', $userMsg->workspace_id)
            ->where('user_id', Auth::id())
            ->where('role', 'assistant')
            ->where('created_at', '>=', $userMsg->created_at)
            ->exists();

        if ($hasResponse) {
            $this->loading          = false;
            $this->pendingUserMsgId = null;
            $this->dispatch('chat-updated');
        }
    }

    public function clearHistory(): void
    {
        $workspaceId = $this->activeWorkspaceId();

        if ($workspaceId !== null) {
            ChatMessage::where('workspace_id', $workspaceId)
                ->where('user_id', Auth::id())
                ->delete();
        }

        $this->loading          = false;
        $this->pendingUserMsgId = null;
    }

    public function with(): array
    {
        return ['chatMessages' => $this->getChatMessages()];
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

        {{-- Loading indicator with 3s poll --}}
        @if($loading)
            <div wire:poll.3s="checkForResponse" class="flex justify-start">
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

    $wire.on('chat-updated', () => {
        clearTimeout(timeoutHandle);
        setTimeout(scrollToBottom, 50);
    });

    $wire.on('chat-loading-started', () => {
        scrollToBottom();
        // 90s frontend failsafe — stops spinner if queue-worker is down or response never arrives
        timeoutHandle = setTimeout(() => {
            $wire.set('loading', false);
            $wire.set('pendingUserMsgId', null);
        }, 90000);
    });
</script>
@endscript
