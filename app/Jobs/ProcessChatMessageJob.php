<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Services\InsufficientCreditsException;
use App\Services\LangdockAgentException;
use App\Services\LangdockAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessChatMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(
        private readonly string $userMessageId,
        private readonly string $workspaceId,
        private readonly int    $userId,
        private readonly array  $context,
    ) {}

    public function handle(): void
    {
        $userMessage = ChatMessage::find($this->userMessageId);

        if ($userMessage === null) {
            return;
        }

        $history = ChatMessage::where('workspace_id', $this->workspaceId)
            ->where('user_id', $this->userId)
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->filter(fn (ChatMessage $m) => $m->content !== null)
            ->take(-20)
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();

        try {
            $result = app(LangdockAgentService::class)->callByConfigKey(
                'agent_id',
                $history,
                60,
                $this->context,
            );

            ChatMessage::create([
                'user_id'      => $this->userId,
                'workspace_id' => $this->workspaceId,
                'role'         => 'assistant',
                'content'      => $result['content'],
            ]);
        } catch (InsufficientCreditsException $e) {
            ChatMessage::create([
                'user_id'      => $this->userId,
                'workspace_id' => $this->workspaceId,
                'role'         => 'assistant',
                'content'      => __('Guthaben aufgebraucht. Bitte den Admin kontaktieren.'),
            ]);
        } catch (LangdockAgentException $e) {
            ChatMessage::create([
                'user_id'      => $this->userId,
                'workspace_id' => $this->workspaceId,
                'role'         => 'assistant',
                'content'      => __('Fehler bei der Verarbeitung. Bitte versuche es erneut.'),
            ]);
        } catch (\Throwable $e) {
            ChatMessage::create([
                'user_id'      => $this->userId,
                'workspace_id' => $this->workspaceId,
                'role'         => 'assistant',
                'content'      => __('Verbindung fehlgeschlagen. Bitte versuche es später erneut.'),
            ]);
        }
    }
}
