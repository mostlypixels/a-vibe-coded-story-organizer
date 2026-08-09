<x-app-layout>
    <x-page-heading>{{ __('Tools') }}</x-page-heading>

    <div class="grid gap-6 md:grid-cols-2">
        <x-card :title="__('Revisions')" stretch flush-footer>
            <p>{{ __('Every autosave and manual save of your scenes, kept for restoring or comparing later.') }}</p>

            <x-slot:footer>
                <a href="{{ route('projects.revisions.index', $project) }}" class="text-sm font-medium text-link hover:underline">
                    {{ __('View revisions') }}
                </a>
            </x-slot:footer>
        </x-card>

        <x-card :title="__('Progress')" stretch flush-footer>
            <p>{{ __('Your daily and total word count goals, charted against what you have written.') }}</p>

            <x-slot:footer>
                <a href="{{ route('projects.progress', $project) }}" class="text-sm font-medium text-link hover:underline">
                    {{ __('View progress') }}
                </a>
            </x-slot:footer>
        </x-card>
    </div>
</x-app-layout>
