<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="2">
                {{ __('Compare') }} &mdash; {{ Illuminate\Support\Str::headline($field) }}
            </x-heading>
            <a href="{{ route('revisions.index', ['entity' => $entity, 'id' => $id, 'field' => $field]) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to history') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($from === null || $to === null)
            {{-- Not enough history yet to compare (fewer than two revisions), or an
                 explicit from/to pair that failed to resolve. --}}
            <div class="bg-white shadow-sm rounded-lg px-6 py-10 text-center text-gray-500">
                <p class="font-medium text-gray-600">{{ __('Nothing to compare yet.') }}</p>
                <p class="mt-1 text-sm">{{ __('This field needs at least two revisions before they can be compared.') }}</p>
            </div>
        @else
            <div class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-gray-600">
                <div class="flex items-center gap-3">
                    <div>
                        <span class="font-semibold text-gray-800">{{ __('From') }}</span>
                        {{ $from->created_at->format('d F Y H:i') }}
                        ({{ $from->user?->name ?? __('Unknown') }})
                    </div>
                    <x-revert-revision-button :revision="$from" :base-hash="$baseHash" />
                </div>
                <div class="flex items-center gap-3">
                    <div>
                        <span class="font-semibold text-gray-800">{{ __('To') }}</span>
                        {{ $to->created_at->format('d F Y H:i') }}
                        ({{ $to->user?->name ?? __('Unknown') }})
                    </div>
                    <x-revert-revision-button :revision="$to" :base-hash="$baseHash" />
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($result->formattingChangedOnly)
                    {{-- handoff.md §5.3: same prose, different HTML markup only —
                         rendering an empty diff here would misleadingly read as
                         "nothing changed". --}}
                    <p class="text-gray-600 italic">{{ __('Formatting changed only.') }}</p>
                @else
                    {{-- jfcherng/php-diff's HTML renderer already escapes the
                         underlying text — safe to render directly. --}}
                    <div class="prose max-w-none text-sm [&_del]:bg-red-100 [&_del]:text-red-700 [&_del]:no-underline [&_ins]:bg-green-100 [&_ins]:text-green-700 [&_ins]:no-underline">
                        {!! $result->html !!}
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
