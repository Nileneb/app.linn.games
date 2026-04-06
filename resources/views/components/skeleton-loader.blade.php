@props([
    'count' => 3,
    'type' => 'table', // table, card, text
    'rows' => 3,
])

<div class="space-y-{{ $type === 'card' ? '4' : '2' }} animate-pulse">
    @for ($i = 0; $i < $count; $i++)
        @if ($type === 'table')
            <div class="flex items-center gap-4">
                <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex-1">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                    <div class="h-3 bg-gray-100 dark:bg-gray-800 rounded w-1/2"></div>
                </div>
            </div>
        @elseif ($type === 'card')
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded w-2/3 mb-4"></div>
                @for ($j = 0; $j < $rows; $j++)
                    <div class="h-4 bg-gray-100 dark:bg-gray-800 rounded mb-3 {{ $j === $rows - 1 ? '' : 'w-full' }} {{ $j === $rows - 1 ? 'w-2/3' : '' }}"></div>
                @endfor
            </div>
        @else
            <!-- text lines -->
            @for ($j = 0; $j < $rows; $j++)
                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded {{ $j === $rows - 1 ? 'w-3/4' : 'w-full' }}"></div>
            @endfor
        @endif
    @endfor
</div>
