{{-- The category list beside the notes table. `$category` is the ?category= value. --}}
@php
    $indents = [1 => 'pl-0', 2 => 'pl-4', 3 => 'pl-8'];
    $rows = $categoryTree->flat();
    $names = collect($rows)->mapWithKeys(fn ($row) => [$row['category']->id => $row['category']->name]);
    $blockedForNew = $categoryTree->blockedParentIds();
    $link = fn (?string $value) => route('projects.notes.index', array_filter(['project' => $project, 'category' => $value]));
    $itemClass = fn (bool $active) => 'block min-w-0 flex-1 truncate rounded-md px-2 py-1 text-sm hover:bg-neutral '
        .($active ? 'bg-neutral font-semibold text-content' : 'text-content-muted');
@endphp

<nav aria-label="{{ __('Note categories') }}" class="space-y-3">
    <ul class="space-y-1">
        <li class="flex">
            <a href="{{ $link(null) }}" @class([$itemClass($category === null)]) @if ($category === null) aria-current="page" @endif>{{ __('All notes') }}</a>
        </li>
        <li class="flex">
            <a href="{{ $link('none') }}" @class([$itemClass($category === 'none')]) @if ($category === 'none') aria-current="page" @endif>{{ __('Uncategorized') }}</a>
        </li>

        @foreach ($rows as $row)
            @php
                $item = $row['category'];
                $active = $category === (string) $item->id;
                $parentName = $names->get($item->parent_id);
            @endphp
            <li class="flex items-center gap-1 {{ $indents[$row['depth']] }}">
                <a href="{{ $link((string) $item->id) }}" @class([$itemClass($active)]) @if ($active) aria-current="page" @endif>{{ $item->name }}</a>

                <x-icon-button
                    type="button"
                    icon="pencil"
                    variant="ghost"
                    :label="__('Edit category')"
                    x-data=""
                    x-on:click="$dispatch('open-note-category', {{ Js::from([
                        'action' => route('note-categories.update', $item),
                        'method' => 'PATCH',
                        'title' => __('Edit category'),
                        'name' => $item->name,
                        'parentId' => $item->parent_id,
                        'blocked' => $categoryTree->blockedParentIds($item->id),
                    ]) }})"
                />

                <x-icon-delete-button
                    :action="route('note-categories.destroy', $item)"
                    :confirm="$parentName
                        ? __('Delete :name? Its notes and sub-categories move to :parent.', ['name' => $item->name, 'parent' => $parentName])
                        : __('Delete :name? Its notes and sub-categories move to the top level.', ['name' => $item->name])"
                    :label="__('Delete category')"
                />
            </li>
        @endforeach
    </ul>

    <x-input-error :messages="$errors->get('name')" />
    <x-input-error :messages="$errors->get('parent_id')" />

    <x-button
        variant="secondary"
        type="button"
        x-data=""
        x-on:click="$dispatch('open-note-category', {{ Js::from([
            'action' => route('projects.note-categories.store', $project),
            'method' => 'POST',
            'title' => __('New category'),
            'name' => '',
            'parentId' => null,
            'blocked' => $blockedForNew,
        ]) }})"
    >{{ __('New category') }}</x-button>
</nav>

<x-note-category-dialog :tree="$categoryTree" />
