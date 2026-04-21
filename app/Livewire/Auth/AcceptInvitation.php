<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class AcceptInvitation extends Component
{
    public string $token;

    public string $password = '';

    public string $password_confirmation = '';

    public ?User $invitedUser = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invitedUser = User::where('invitation_token', $token)
            ->where('invitation_expires_at', '>', now())
            ->where('status', 'invited')
            ->first();
    }

    public function accept(): void
    {
        $this->validate([
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $this->invitedUser->update([
            'password' => Hash::make($this->password),
            'status' => 'active',
            'invitation_token' => null,
            'invitation_expires_at' => null,
        ]);

        session()->flash('status', 'Einladung angenommen. Du kannst dich jetzt einloggen.');
        $this->redirect(route('login'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.accept-invitation');
    }
}
