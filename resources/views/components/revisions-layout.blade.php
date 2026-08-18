<x-app-layout>
    @isset($header)
        <x-slot name="header">
            {{ $header }}
        </x-slot>
    @endisset

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-3">
            @include('revisions.partials.sidebar', [
                'tree' => $tree,
                'project' => $project,
                'activeEntity' => $entity,
                'activeId' => $id,
                'activeField' => $field,
            ])
        </div>

        <div class="lg:col-span-9 space-y-6">
            @if ($errorMessage())
                <x-alert variant="danger" dismissible>{{ $errorMessage() }}</x-alert>
            @endif

            @if ($errors->any())
                <x-alert variant="danger" dismissible :title="__('That value cannot be restored as it stands.')">
                    <p>{{ __('It no longer passes the rules this field enforces today — the rules have tightened since it was saved. The text is still in the history; nothing was changed.') }}</p>

                    <ul class="mt-1 list-disc list-inside">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            @if (session('status') === 'reverted')
                <x-alert variant="success" dismissible>
                    {{ __('Reverted. That value is current again, and the revert was added to the history — nothing was removed.') }}
                </x-alert>
            @endif

            {{ $slot }}
        </div>
    </div>
</x-app-layout>
