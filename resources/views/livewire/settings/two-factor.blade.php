<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\Response;

new class extends Component {
    #[Locked]
    public bool $twoFactorEnabled;

    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showModal = false;

    public bool $showVerificationStep = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        abort_unless(Features::enabled(Features::twoFactorAuthentication()), Response::HTTP_FORBIDDEN);

        if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
            $disableTwoFactorAuthentication(auth()->user());
        }

        $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function enable(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        $enableTwoFactorAuthentication(auth()->user());

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }

        $this->loadSetupData();

        $this->showModal = true;
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user();

        try {
            $this->qrCodeSvg = $user?->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->closeModal();

        $this->twoFactorEnabled = true;
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showModal',
            'showVerificationStep',
        );

        $this->resetErrorBag();

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }
    }

    /**
     * Get the current modal configuration state.
     */
    public function getModalConfigProperty(): array
    {
        if ($this->twoFactorEnabled) {
            return [
                'title' => __('Two-Factor Authentication Enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify Authentication Code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable Two-Factor Authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }
} ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        :heading="__('Two Factor Authentication')"
        :subheading="__('Manage your two-factor authentication settings')"
    >
        <div class="flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
            @if ($twoFactorEnabled)
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ __('Enabled') }}</span>
                    </div>

                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('With two-factor authentication enabled, you will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                    </p>

                    <livewire:settings.two-factor.recovery-codes :$requiresConfirmation/>

                    <div class="flex justify-start">
                        <button wire:click="disable" class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                            {{ __('Disable 2FA') }}
                        </button>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ __('Disabled') }}</span>
                    </div>

                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                    </p>

                    <button wire:click="enable" class="inline-flex items-center gap-2 rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                        {{ __('Enable 2FA') }}
                    </button>
                </div>
            @endif
        </div>
    </x-settings.layout>

    {{-- 2FA Setup Modal --}}
    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center" x-data @keydown.escape.window="$wire.closeModal()">
        <div class="fixed inset-0 bg-black/50" @click="$wire.closeModal()"></div>
        <div class="relative z-10 w-full max-w-md rounded-lg border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
            <div class="space-y-6">
                <div class="flex flex-col items-center space-y-4">
                    <div class="p-0.5 w-auto rounded-full border border-stone-100 dark:border-stone-600 bg-white dark:bg-stone-800 shadow-sm">
                        <div class="p-2.5 rounded-full border border-stone-200 dark:border-stone-600 overflow-hidden bg-stone-100 dark:bg-stone-200 relative">
                            <div class="flex items-stretch absolute inset-0 w-full h-full divide-x [&>div]:flex-1 divide-stone-200 dark:divide-stone-300 justify-around opacity-50">
                                @for ($i = 1; $i <= 5; $i++)
                                    <div></div>
                                @endfor
                            </div>

                            <div class="flex flex-col items-stretch absolute w-full h-full divide-y [&>div]:flex-1 inset-0 divide-stone-200 dark:divide-stone-300 justify-around opacity-50">
                                @for ($i = 1; $i <= 5; $i++)
                                    <div></div>
                                @endfor
                            </div>

                            <svg class="relative z-20 h-6 w-6 text-stone-700 dark:text-stone-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z"/></svg>
                        </div>
                    </div>

                    <div class="space-y-2 text-center">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->modalConfig['title'] }}</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $this->modalConfig['description'] }}</p>
                    </div>
                </div>

                @if ($showVerificationStep)
                    <div class="space-y-6">
                        <div class="flex flex-col items-center space-y-3 justify-center" x-data="{
                            digits: ['','','','','',''],
                            focusNext(i) { if (i < 5) this.$refs['otp'+i+1]?.focus(); },
                            handleInput(i, e) {
                                this.digits[i] = e.target.value.slice(-1);
                                $wire.set('code', this.digits.join(''));
                                if (e.target.value) this.focusNext(i);
                            },
                            handleKeydown(i, e) {
                                if (e.key === 'Backspace' && !this.digits[i] && i > 0) this.$refs['otp'+(i-1)]?.focus();
                            },
                            handlePaste(e) {
                                const text = (e.clipboardData || window.clipboardData).getData('text').slice(0,6);
                                text.split('').forEach((c, i) => { if (i < 6) this.digits[i] = c; });
                                $wire.set('code', this.digits.join(''));
                                e.preventDefault();
                            }
                        }">
                            <div class="mx-auto flex gap-2">
                                <template x-for="(d, i) in digits" :key="i">
                                    <input :x-ref="'otp'+i" type="text" inputmode="numeric" maxlength="1" :value="digits[i]" @input="handleInput(i, $event)" @keydown="handleKeydown(i, $event)" @paste="handlePaste($event)" class="h-12 w-10 rounded-md border border-zinc-300 bg-white text-center text-lg text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <button wire:click="resetVerification" class="flex-1 rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                {{ __('Back') }}
                            </button>

                            <button wire:click="confirmTwoFactor" x-bind:disabled="$wire.code.length < 6" class="flex-1 rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                                {{ __('Confirm') }}
                            </button>
                        </div>
                    </div>
                @else
                    @error('setupData')
                        <div class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-400">{{ $message }}</div>
                    @enderror

                    <div class="flex justify-center">
                        <div class="relative w-64 overflow-hidden border rounded-lg border-stone-200 dark:border-stone-700 aspect-square">
                            @empty($qrCodeSvg)
                                <div class="absolute inset-0 flex items-center justify-center bg-white dark:bg-stone-700 animate-pulse">
                                    <svg class="h-5 w-5 animate-spin text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </div>
                            @else
                                <div class="flex items-center justify-center h-full p-4">
                                    <div class="bg-white p-3 rounded">
                                        {!! $qrCodeSvg !!}
                                    </div>
                                </div>
                            @endempty
                        </div>
                    </div>

                    <div>
                        <button wire:click="showVerificationIfNecessary" {{ $errors->has('setupData') ? 'disabled' : '' }} class="inline-flex w-full items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                            {{ $this->modalConfig['buttonText'] }}
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div class="relative flex items-center justify-center w-full">
                            <div class="absolute inset-0 w-full h-px top-1/2 bg-stone-200 dark:bg-stone-600"></div>
                            <span class="relative px-2 text-sm bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-400">
                                {{ __('or, enter the code manually') }}
                            </span>
                        </div>

                        <div
                            class="flex items-center space-x-2"
                            x-data="{
                                copied: false,
                                async copy() {
                                    try {
                                        await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                        this.copied = true;
                                        setTimeout(() => this.copied = false, 1500);
                                    } catch (e) {
                                        console.warn('Could not copy to clipboard');
                                    }
                                }
                            }"
                        >
                            <div class="flex items-stretch w-full border rounded-xl dark:border-stone-700">
                                @empty($manualSetupKey)
                                    <div class="flex items-center justify-center w-full p-3 bg-stone-100 dark:bg-stone-700">
                                        <svg class="h-5 w-5 animate-spin text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    </div>
                                @else
                                    <input
                                        type="text"
                                        readonly
                                        value="{{ $manualSetupKey }}"
                                        class="w-full p-3 bg-transparent outline-none text-stone-900 dark:text-stone-100"
                                    />

                                    <button
                                        @click="copy()"
                                        class="px-3 transition-colors border-l cursor-pointer border-stone-200 dark:border-stone-600"
                                    >
                                        <svg x-show="!copied" class="h-5 w-5 text-zinc-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
                                        <svg x-show="copied" class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd"/></svg>
                                    </button>
                                @endempty
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</section>
