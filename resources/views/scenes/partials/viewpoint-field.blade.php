<x-field name="viewpoint_character_id" :label="__('Viewpoint')" :hint="__('The character whose eyes the reader sees through (optional).')">
    <x-select id="viewpoint_character_id" name="viewpoint_character_id" class="mt-1 block w-full">
        <option value="">{{ __('— No viewpoint —') }}</option>
        @foreach ($characters as $character)
            <option value="{{ $character->id }}" @selected($selected == $character->id)>{{ $character->name }}</option>
        @endforeach
    </x-select>
</x-field>
