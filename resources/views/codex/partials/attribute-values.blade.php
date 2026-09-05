{{-- Read-only attribute timelines: full history, baseline first, no "current value". --}}
@if ($sheets->isNotEmpty())
    <x-card :title="__('Attributes')">
        <div class="space-y-4">
            @foreach ($sheets as $sheet)
                <div>
                    <h3 class="font-semibold text-content">{{ $sheet['attribute']->name }}</h3>

                    <dl class="mt-1 space-y-0.5">
                        @if ($sheet['baseline'])
                            <div class="flex gap-2 text-sm">
                                <dt class="text-content-muted">{{ $sheet['baseline']->startEvent->title }}:</dt>
                                <dd class="text-content">{{ $sheet['baseline']->value }}</dd>
                            </div>
                        @endif

                        @foreach ($sheet['periods'] as $period)
                            <div class="flex gap-2 text-sm">
                                <dt class="text-content-muted">{{ $period->startEvent->title }}:</dt>
                                <dd class="text-content">{{ $period->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </x-card>
@endif
