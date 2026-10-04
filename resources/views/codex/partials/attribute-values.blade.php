{{-- Read-only attribute timelines: one table per attribute, full history, baseline first, no "current value". --}}
@if ($sheets->isNotEmpty())
    <x-card :title="__('Attributes')" icon="tabler-list-details">
        <div class="space-y-6">
            @foreach ($sheets as $sheet)
                @php
                    $rows = collect([$sheet['baseline']])->filter()->concat($sheet['periods']);
                @endphp

                <div>
                    <h3 class="mb-2 font-semibold text-content">{{ $sheet['attribute']->name }}</h3>

                    {{-- Fixed layout, so the columns line up across every attribute table. --}}
                    <x-table class="table-fixed">
                        <x-slot:head>
                            <x-table-heading class="w-36">{{ __('Date') }}</x-table-heading>
                            <x-table-heading>{{ __('Scene') }}</x-table-heading>
                            <x-table-heading class="w-3/5">{{ __('Value') }}</x-table-heading>
                        </x-slot:head>

                        @foreach ($rows as $row)
                            <x-table-row :striped="$loop->even">
                                <x-table-cell top muted nowrap>
                                    {{-- The baseline starts at the project Start, which has no date of its own. --}}
                                    @if ($row === $sheet['baseline'])
                                        &mdash;
                                    @else
                                        <x-date :value="$row->startEvent->event_datetime" />
                                    @endif
                                </x-table-cell>
                                <x-table-cell top>
                                    @foreach ($row->startEvent->scenes as $scene)
                                        <a href="{{ route('scenes.show', $scene) }}" class="block text-link hover:text-link-hover">{{ $scene->name }}</a>
                                    @endforeach
                                </x-table-cell>
                                <x-table-cell top>{{ $row->value }}</x-table-cell>
                            </x-table-row>
                        @endforeach
                    </x-table>
                </div>
            @endforeach
        </div>
    </x-card>
@endif
