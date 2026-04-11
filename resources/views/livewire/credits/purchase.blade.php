<div class="max-w-2xl mx-auto py-12 px-4 space-y-8">

    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Credits kaufen</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Guthaben: <span class="font-semibold text-gray-900 dark:text-white">
                {{ number_format(($workspace->credits_balance_cents ?? 0) / 100, 2, ',', '.') }} €
            </span>
        </p>
    </div>

    <div class="grid grid-cols-2 gap-4">
        @foreach ($packages as $index => $package)
            <button
                wire:click="$set('selected', {{ $index }})"
                class="relative rounded-xl border-2 p-6 text-left transition
                    {{ $selected === $index
                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40'
                        : 'border-gray-200 dark:border-white/10 hover:border-gray-300 dark:hover:border-white/20' }}"
            >
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $package['label'] }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ number_format($package['cents'] / 100, 2, ',', '.') }} € Credits
                </div>
                @if ($selected === $index)
                    <div class="absolute top-3 right-3 w-4 h-4 rounded-full bg-primary-500"></div>
                @endif
            </button>
        @endforeach
    </div>

    <form action="{{ route('credits.checkout') }}" method="POST">
        @csrf
        <input type="hidden" name="package" value="{{ $selected }}">
        <button type="submit"
            class="w-full py-3 rounded-xl bg-primary-600 hover:bg-primary-700 text-white font-semibold text-sm transition">
            Weiter zu Stripe →
        </button>
    </form>

    <p class="text-center text-xs text-gray-400 dark:text-gray-600">
        Sichere Zahlung via Stripe. Keine Abonnements — einmalige Aufladung.
    </p>

    <div class="text-center">
        <a href="{{ route('credits.usage') }}" class="text-sm text-primary-600 hover:underline">
            Transaktionshistorie ansehen
        </a>
    </div>

</div>
