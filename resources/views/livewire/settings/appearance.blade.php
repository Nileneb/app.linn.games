<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div id="appearance-toggle" class="inline-flex rounded-md border border-zinc-200 dark:border-zinc-700">
            <button type="button" data-mode="light"
                class="appearance-btn inline-flex items-center gap-2 rounded-s-md px-4 py-2 text-sm font-medium transition-colors">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
                {{ __('Light') }}
            </button>
            <button type="button" data-mode="dark"
                class="appearance-btn inline-flex items-center gap-2 border-x border-zinc-200 px-4 py-2 text-sm font-medium transition-colors dark:border-zinc-700">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
                {{ __('Dark') }}
            </button>
            <button type="button" data-mode="system"
                class="appearance-btn inline-flex items-center gap-2 rounded-e-md px-4 py-2 text-sm font-medium transition-colors">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25"/></svg>
                {{ __('System') }}
            </button>
        </div>

        <script>
            (function () {
                var ACTIVE = 'bg-zinc-200 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100';
                var INACTIVE = 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800';

                function applyTheme(mode) {
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.classList.toggle(
                        'dark',
                        mode === 'dark' || (mode === 'system' && prefersDark)
                    );
                }

                function highlightButtons(mode) {
                    document.querySelectorAll('#appearance-toggle .appearance-btn').forEach(function (btn) {
                        var isActive = btn.getAttribute('data-mode') === mode;
                        ACTIVE.split(' ').forEach(function (c) { btn.classList.toggle(c, isActive); });
                        INACTIVE.split(' ').forEach(function (c) { btn.classList.toggle(c, !isActive); });
                    });
                }

                function setAppearance(mode) {
                    if (['light', 'dark', 'system'].indexOf(mode) === -1) return;
                    localStorage.setItem('appearance', mode);
                    applyTheme(mode);
                    highlightButtons(mode);
                }

                // Click-Handler registrieren
                document.querySelectorAll('#appearance-toggle .appearance-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        setAppearance(btn.getAttribute('data-mode'));
                    });
                });

                // Initialen Zustand setzen
                var saved = localStorage.getItem('appearance') || 'system';
                applyTheme(saved);
                highlightButtons(saved);
            })();
        </script>
    </x-settings.layout>
</section>
