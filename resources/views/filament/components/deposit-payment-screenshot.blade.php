@php
    $path = $getState();
    $url = filled($path) ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
@endphp

@if (filled($url))
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">
            <img src="{{ $url }}" alt="Payment screenshot" class="max-h-80 rounded-lg object-contain" />
        </a>
        <p class="mt-2 text-xs text-gray-500">
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline">
                Open full size
            </a>
        </p>
    </div>
@endif
