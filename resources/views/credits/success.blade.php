<x-layouts.app>
    <div class="flex flex-col items-center justify-center py-24 gap-4">
        <div class="text-4xl">✅</div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Zahlung erfolgreich</h1>
        <p class="text-gray-500 dark:text-gray-400">Deine Credits wurden dem Workspace gutgeschrieben.</p>
        <a href="{{ route('credits.usage') }}"
           class="mt-4 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 transition">
            Zum Guthaben
        </a>
    </div>
</x-layouts.app>
