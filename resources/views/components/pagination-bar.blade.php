@props(['paginator'])

<div class="mt-4 flex flex-wrap items-center justify-between gap-4">
    <x-row-range :paginator="$paginator" />

    {{-- onEachSide(1) puts the ellipsis in from 11 pages instead of 15, so a long
         list stays a narrow bar. --}}
    <div>{{ $paginator->onEachSide(1)->links() }}</div>

    <form method="POST" action="{{ route('preferences.page-size.update') }}">
        @csrf
        @method('PATCH')

        <label for="page-size" class="sr-only">{{ __('Rows per page') }}</label>
        <x-select id="page-size" name="page_size" onchange="this.form.requestSubmit()" class="text-sm">
            @foreach (\App\Support\PageSize::sizes() as $size)
                <option value="{{ $size }}" @selected($size === $paginator->perPage())>{{ $size }}</option>
            @endforeach
        </x-select>
    </form>
</div>
