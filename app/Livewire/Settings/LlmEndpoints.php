<?php

namespace App\Livewire\Settings;

use App\Models\LlmEndpoint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Workspace-Admins verwalten hier Provider-Configs (Ollama, Anthropic-BYO, OpenAI, Platform).
 * Einträge werden von MayringCoder beim Agent-Call via Service-API abgerufen.
 */
class LlmEndpoints extends Component
{
    public bool $editing = false;
    public ?int $editingId = null;

    public string $provider = 'ollama';
    public string $base_url = '';
    public string $model = '';
    public string $api_key = '';
    public bool $is_default = false;
    public string $agent_scope = '';

    protected function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['ollama', 'anthropic', 'openai', 'platform'])],
            'base_url' => ['required', 'url', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
            'agent_scope' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editing = true;
    }

    public function startEdit(int $id): void
    {
        $endpoint = $this->workspaceEndpoints()->findOrFail($id);
        $this->editingId = $endpoint->id;
        $this->provider = $endpoint->provider;
        $this->base_url = $endpoint->base_url;
        $this->model = $endpoint->model;
        $this->api_key = ''; // leer anzeigen, "leer lassen = behalten"
        $this->is_default = $endpoint->is_default;
        $this->agent_scope = $endpoint->agent_scope ?? '';
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $data = $this->validate();

        $workspace = Auth::user()->currentWorkspace();

        DB::transaction(function () use ($data, $workspace) {
            // Max ein Default pro Workspace (app-level enforcement)
            if ($data['is_default']) {
                LlmEndpoint::where('workspace_id', $workspace->id)
                    ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                    ->update(['is_default' => false]);
            }

            $attributes = [
                'workspace_id' => $workspace->id,
                'provider' => $data['provider'],
                'base_url' => $data['base_url'],
                'model' => $data['model'],
                'is_default' => $data['is_default'],
                'agent_scope' => $data['agent_scope'] !== '' ? $data['agent_scope'] : null,
            ];

            if ($this->editingId) {
                $endpoint = LlmEndpoint::findOrFail($this->editingId);
                $endpoint->fill($attributes);
                if (! empty($data['api_key'])) {
                    $endpoint->api_key = $data['api_key']; // mutator encrypts
                }
                $endpoint->save();
            } else {
                $endpoint = new LlmEndpoint($attributes);
                if (! empty($data['api_key'])) {
                    $endpoint->api_key = $data['api_key'];
                }
                $endpoint->save();
            }
        });

        $this->resetForm();
        $this->dispatch('llm-endpoint-saved');
    }

    public function delete(int $id): void
    {
        $this->workspaceEndpoints()->findOrFail($id)->delete();
        $this->dispatch('llm-endpoint-deleted');
    }

    private function resetForm(): void
    {
        $this->editing = false;
        $this->editingId = null;
        $this->provider = 'ollama';
        $this->base_url = '';
        $this->model = '';
        $this->api_key = '';
        $this->is_default = false;
        $this->agent_scope = '';
    }

    private function workspaceEndpoints()
    {
        return LlmEndpoint::where('workspace_id', Auth::user()->currentWorkspace()->id);
    }

    public function render(): View
    {
        return view('livewire.settings.llm-endpoints', [
            'endpoints' => $this->workspaceEndpoints()->orderByDesc('is_default')->orderBy('agent_scope')->get(),
            'providers' => ['platform', 'ollama', 'anthropic', 'openai'],
        ]);
    }
}
