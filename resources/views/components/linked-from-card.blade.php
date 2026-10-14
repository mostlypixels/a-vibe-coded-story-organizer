{{-- Texts that link to one entity. Hidden when there are none. --}}
@if ($sources->isNotEmpty())
    <x-card :title="__('Linked from')" icon="tabler-link" data-linked-from>
        <ul class="divide-y divide-border">
            @foreach ($sources as $source)
                <li class="py-2 first:pt-0 last:pb-0">
                    <a href="{{ $source['url'] }}" class="text-link hover:text-link-hover">{{ $source['title'] }}</a>
                    <p class="text-sm text-content-muted">
                        {{ __($source['kind']) }} &middot; {{ implode(', ', array_map(fn ($field) => __($field), $source['fields'])) }}
                    </p>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
