<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;

                    this.code = '';
                    this.recovery_code = '';

                    $dispatch('clear-2fa-auth-code');

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : $dispatch('focus-2fa-auth-code');
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    :title="__('Authentication Code')"
                    :description="__('Enter the authentication code provided by your authenticator application.')"
                />
            </div>

            <div x-show="showRecoveryInput">
                <x-auth-header
                    :title="__('Recovery Code')"
                    :description="__('Please confirm access to your account by entering one of your emergency recovery codes.')"
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf

                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <div class="flex items-center justify-center my-5">
                            <div class="mx-auto flex gap-2" x-data="{
                                digits: ['','','','','',''],
                                focusNext(i) {
                                    if (i < 5) this.$refs['otp'+(i+1)].focus();
                                },
                                handleInput(i, e) {
                                    this.digits[i] = e.target.value.slice(-1);
                                    this.code = this.digits.join('');
                                    if (e.target.value) this.focusNext(i);
                                },
                                handleKeydown(i, e) {
                                    if (e.key === 'Backspace' && !this.digits[i] && i > 0) {
                                        this.$refs['otp'+(i-1)].focus();
                                    }
                                },
                                handlePaste(e) {
                                    const text = (e.clipboardData || window.clipboardData).getData('text').slice(0,6);
                                    text.split('').forEach((c, i) => { if (i < 6) this.digits[i] = c; });
                                    this.code = this.digits.join('');
                                    e.preventDefault();
                                    if (text.length >= 6) this.$refs.otp5.focus();
                                }
                            }" @clear-2fa-auth-code.window="digits=['','','','','','']; code=''" @focus-2fa-auth-code.window="$refs.otp0.focus()">
                                <template x-for="(d, i) in digits" :key="i">
                                    <input :x-ref="'otp'+i" type="text" inputmode="numeric" maxlength="1" :value="digits[i]" @input="handleInput(i, $event)" @keydown="handleKeydown(i, $event)" @paste="handlePaste($event)" class="h-12 w-10 rounded-md border border-zinc-300 bg-white text-center text-lg text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                                </template>
                                <input type="hidden" name="code" x-model="code" />
                            </div>
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="my-5">
                            <input
                                type="text"
                                name="recovery_code"
                                x-ref="recovery_code"
                                x-bind:required="showRecoveryInput"
                                autocomplete="one-time-code"
                                x-model="recovery_code"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                            />
                        </div>

                        @error('recovery_code')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                        {{ __('Continue') }}
                    </button>
                </div>

                <div class="mt-5 space-x-0.5 text-sm leading-5 text-center">
                    <span class="opacity-50">{{ __('or you can') }}</span>
                    <div class="inline font-medium underline cursor-pointer opacity-80">
                        <span x-show="!showRecoveryInput" @click="toggleInput()">{{ __('login using a recovery code') }}</span>
                        <span x-show="showRecoveryInput" @click="toggleInput()">{{ __('login using an authentication code') }}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts.auth>
