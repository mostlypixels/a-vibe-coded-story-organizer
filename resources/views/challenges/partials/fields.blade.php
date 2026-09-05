@php
    $challenge ??= null;
@endphp

<div
    x-data="{
        recurrence: '{{ old('recurrence', $challenge?->recurrence?->value ?? 'none') }}',
        startsOn: '{{ old('starts_on', $challenge?->starts_on?->toDateString()) }}',
        endsOn: '{{ old('ends_on', $challenge?->ends_on?->toDateString()) }}',
        targetWords: '{{ old('target_words', $challenge?->target_words) }}',
        windowDays() {
            if (! this.startsOn) return null;
            const start = new Date(this.startsOn + 'T00:00:00');
            if (this.recurrence === 'monthly') {
                return new Date(start.getFullYear(), start.getMonth() + 1, 0).getDate();
            }
            if (! this.endsOn) return null;
            const end = new Date(this.endsOn + 'T00:00:00');
            const days = Math.round((end - start) / 86400000) + 1;
            return days > 0 ? days : null;
        },
        get parHint() {
            const days = this.windowDays();
            const target = parseInt(this.targetWords, 10);
            if (! days || ! target) return null;
            return Math.round(target / days).toLocaleString('en-US');
        },
    }"
    class="space-y-6"
>
    <x-field name="name" :label="__('Name')">
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $challenge?->name)" required autofocus />
    </x-field>

    <x-field name="recurrence" :label="__('Recurrence')">
        <x-select id="recurrence" name="recurrence" x-model="recurrence" class="mt-1 block w-full" required>
            @foreach (\App\Enums\ChallengeRecurrence::cases() as $recurrence)
                <option value="{{ $recurrence->value }}" @selected(old('recurrence', $challenge?->recurrence?->value ?? 'none') === $recurrence->value)>
                    {{ $recurrence->label() }}
                </option>
            @endforeach
        </x-select>
    </x-field>

    <x-field name="starts_on" :label="__('Starts on')">
        <x-text-input id="starts_on" name="starts_on" type="date" x-model="startsOn" class="mt-1 block w-full" required />
    </x-field>

    <div>
        <x-input-label for="ends_on" :value="__('Ends on')" />
        <div x-show="recurrence !== 'monthly'">
            <x-text-input id="ends_on" name="ends_on" type="date" x-model="endsOn" class="mt-1 block w-full" />
        </div>
        <p x-show="recurrence === 'monthly'" class="mt-1 text-sm text-content-muted">
            {{ __('Runs every calendar month until you delete it.') }}
        </p>
        <x-input-error :messages="$errors->get('ends_on')" class="mt-2" />
    </div>

    <x-field name="target_words" :label="__('Target words')">
        <x-text-input id="target_words" name="target_words" type="number" min="1" x-model="targetWords" class="mt-1 block w-full" required />
        <p class="mt-1 text-sm text-content-muted" x-show="parHint" x-text="parHint ? 'about ' + parHint + ' words a day' : ''"></p>
    </x-field>
</div>
