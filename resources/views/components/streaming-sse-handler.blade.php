<?php

use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public bool $showStreamingContent = false;
    public string $streamingUrl = '';

    public function mount(): void
    {
        $this->streamingUrl = route('mcp.agent-stream', absolute: true);
    }

    public function startStreaming(string $agentId, array $messages, ?array $context = null): void
    {
        $this->dispatch('streaming-started');
        $this->showStreamingContent = true;

        // Trigger SSE subscription via Alpine/JavaScript
        $this->dispatch('start-sse-stream', agentId: $agentId, messages: $messages, context: $context);
    }
}; ?>

<!-- Hidden Alpine component for SSE streaming -->
<div 
    x-init="init()"
    @start-sse-stream.window="handleStream($event.detail)"
    class="hidden"
>
    <script>
        function init() {
            window.sseStreamHandler = {
                connect: function(url, agentId, messages, context, token) {
                    const eventSource = new EventSource(
                        url + '?' + new URLSearchParams({
                            agent_id: agentId,
                            messages: JSON.stringify(messages),
                            context: JSON.stringify(context || {}),
                            _token: token,
                        })
                    );

                    eventSource.addEventListener('message', (e) => {
                        try {
                            const data = JSON.parse(e.data);

                            if (data.type === 'content') {
                                window.dispatchEvent(new CustomEvent('streaming-chunk', {
                                    detail: { chunk: data.chunk }
                                }));
                            } else if (data.type === 'complete') {
                                window.dispatchEvent(new CustomEvent('streaming-complete', {
                                    detail: { total_chars: data.total_chars }
                                }));
                                eventSource.close();
                            } else if (data.type === 'error') {
                                window.dispatchEvent(new CustomEvent('streaming-error', {
                                    detail: { error: data.error }
                                }));
                                eventSource.close();
                            }
                        } catch (err) {
                            console.error('SSE parse error:', err);
                        }
                    });

                    eventSource.addEventListener('error', () => {
                        eventSource.close();
                        window.dispatchEvent(new CustomEvent('streaming-error', {
                            detail: { error: 'SSE connection lost' }
                        }));
                    });

                    return eventSource;
                }
            };
        }

        function handleStream(detail) {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            console.log('Starting SSE stream for agent:', detail.agentId);
            window.sseStreamHandler.connect(
                @json(route('mcp.agent-stream')),
                detail.agentId,
                detail.messages,
                detail.context,
                token
            );
        }
    </script>
</div>
