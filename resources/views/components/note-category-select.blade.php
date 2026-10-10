{{--
    A select of the project's note categories, indented by depth. Extra attributes
    (id, name, x-model) go to the select.

    `blocked` is the name of an Alpine array of ids to disable, for the parent
    pick in the category dialog.
--}}
@props(['tree', 'selected' => null, 'emptyLabel', 'blocked' => null])

<x-select {{ $attributes }}>
    <option value="">{{ $emptyLabel }}</option>

    @foreach ($tree->flat() as $row)
        <option
            value="{{ $row['category']->id }}"
            @selected($selected !== null && (int) $selected === $row['category']->id)
            @if ($blocked) x-bind:disabled="{{ $blocked }}.includes({{ $row['category']->id }})" @endif
        >{{ str_repeat("\u{00A0}\u{00A0}", $row['depth'] - 1).$row['category']->name }}</option>
    @endforeach
</x-select>
