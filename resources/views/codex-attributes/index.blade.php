<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }} &mdash; {{ __('Attributes') }}
            </h2>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to Project') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
            <div class="flex items-center justify-end">
                <x-button variant="primary" :href="route('projects.codex-attributes.create', $project)">{{ __('New Attribute') }}</x-button>
            </div>

            <x-table>
                <x-slot:head>
                    <x-table-heading>{{ __('Name') }}</x-table-heading>
                    <x-table-heading>{{ __('Applies to') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($attributes as $attribute)
                    <x-table-row :striped="$loop->even">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-800">{{ $attribute->name }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($attribute->applies_to as $type)
                                    <x-badge>{{ $type->label() }}</x-badge>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-edit-link :href="route('codex-attributes.edit', $attribute)" />
                                <x-icon-delete-button
                                    :action="route('codex-attributes.destroy', $attribute)"
                                    :confirm="__('Delete this attribute? Every timeline value recorded for it will be permanently removed.')" />
                            </div>
                        </td>
                    </x-table-row>
                @empty
                    <x-table-empty :colspan="3">{{ __('No attributes yet.') }}</x-table-empty>
                @endforelse
            </x-table>
    </div>
</x-app-layout>
