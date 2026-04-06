<?php

use Livewire\Volt\Component;

new class extends Component {
    public bool $isOpen = false;
    public bool $isMinimized = true;
    public bool $isStreaming = false;
    public string $streamingContent = '';
    public array $recentMessages = [];

    public function mount(): void
    {
        // Listen for streaming start events
        $this->on('streaming-started', function () {
            $this->isOpen = true;
            $this->isMinimized = false;
            $this->isStreaming = true;
            $this->streamingContent = '';
        });

        // Listen for streaming chunks
        $this->on('streaming-chunk', function ($chunk) {
            $this->streamingContent .= $chunk;
        });

        // Listen for streaming complete
        $this->on('streaming-complete', function () {
            $this->isStreaming = false;
            $this->recentMessages[] = [
                'content' => $this->streamingContent,
                'timestamp' => now(),
            ];
        });
    }

    public function toggleOpen(): void
    {
        $this->isOpen = !$this->isOpen;
        if ($this->isOpen) {
            $this->isMinimized = false;
        }
    }

    public function toggleMinimize(): void
    {
        $this->isMinimized = !$this->isMinimized;
    }

    public function clearMessages(): void
    {
        $this->recentMessages = [];
        $this->streamingContent = '';
    }
}; ?>

<div class="fixed bottom-4 right-4 z-50 font-sans">
    <!-- Minimized Icon -->
    @if($isMinimized && $isOpen)
        <button wire:click="toggleMinimize" class="mb-2 w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 shadow-lg hover:shadow-xl transition-shadow flex items-center justify-center text-white hover:scale-110 transform transition-transform" title="Öffnen">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
            </svg>
        </button>
    @endif

    <!-- Floating Panel -->
    @if($isOpen && !$isMinimized)
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-2xl border border-zinc-200 dark:border-zinc-700 w-96 max-h-96 flex flex-col overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm0-14c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6z"/>
                    </svg>
                    <h3 class="font-semibold text-sm">{{ __('Research Outputs') }}</h3>

                    @if($isStreaming)
                        <span class="ml-auto inline-flex items-center gap-1">
                            <span class="inline-flex h-2 w-2 rounded-full bg-green-400 animate-pulse"></span>
                            <span class="text-xs">{{ __('Streaming…') }}</span>
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-1">
                    <button wire:click="toggleMinimize" class="p-1 hover:bg-white hover:bg-opacity-20 rounded transition">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                    <button wire:click="toggleOpen" class="p-1 hover:bg-white hover:bg-opacity-20 rounded transition">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <!-- Streaming Content -->
                @if($isStreaming && $streamingContent)
                    <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-3 border border-blue-200 dark:border-blue-700">
                        <div class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap break-words font-mono text-xs max-h-48 overflow-y-auto">
                            {{ $streamingContent }}<span class="animate-pulse">▌</span>
                        </div>
                    </div>
                @elseif($isStreaming)
                    <div class="flex items-center justify-center h-16">
                        <svg class="w-5 h-5 text-blue-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span class="ml-2 text-sm text-gray-500">{{ __('Processing…') }}</span>
                    </div>
                @endif

                <!-- Recent Messages -->
                @forelse($recentMessages as $message)
                    <div class="bg-zinc-100 dark:bg-zinc-700 rounded-lg p-3">
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                            {{ $message['timestamp']?->format('H:i:s') }}
                        </div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300 line-clamp-3">
                            {{ Str::limit($message['content'], 150) }}
                        </div>
                    </div>
                @empty
                    @if(!$isStreaming)
                        <div class="text-center py-8 text-zinc-400 dark:text-zinc-500">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="w-8 h-8 mx-auto mb-2 opacity-50">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <p class="text-xs">{{ __('Noch keine Ausgaben') }}</p>
                        </div>
                    @endif
                @endforelse
            </div>

            <!-- Footer -->
            @if($recentMessages || $streamingContent)
                <div class="border-t border-zinc-200 dark:border-zinc-700 px-4 py-2 bg-zinc-50 dark:bg-zinc-900">
                    <button wire:click="clearMessages" class="text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition">
                        {{ __('Löschen') }}
                    </button>
                </div>
            @endif
        </div>
    @endif

    <!-- Closed State Icon -->
    @if(!$isOpen)
        <button wire:click="toggleOpen" class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 shadow-lg hover:shadow-xl transition-shadow flex items-center justify-center text-white hover:scale-110 transform transition-transform" title="Öffnen">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 18H4V4h16v16zm-5.04-6.71l-2.75 3.54-2.83-2.75c-.6-.59-1.6-.58-2.24.02-1.12 1.12-1.12 2.91 0 4.03.56.56 1.31.87 2.12.87.8 0 1.56-.31 2.12-.87l2.83-2.75 2.75 3.54c.37.48.91.75 1.48.75.53 0 1.04-.23 1.41-.64 1.01-1.02 1.01-2.69-.01-3.71l-3.68-4.73z"/>
            </svg>
        </button>
    @endif
</div>
