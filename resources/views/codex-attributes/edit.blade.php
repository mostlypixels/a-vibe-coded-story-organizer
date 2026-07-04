<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Attribute') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form method="POST" action="{{ route('codex-attributes.update', $attribute) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    @include('codex-attributes.partials.fields')

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                        <a href="{{ route('projects.codex-attributes.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
                    </div>
                </form>

                <div class="mt-8 border-t border-gray-200 pt-6">
                    <form method="POST" action="{{ route('codex-attributes.destroy', $attribute) }}"
                          onsubmit="return confirm('{{ __('Delete this attribute? Every timeline value recorded for it will be permanently removed.') }}')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>{{ __('Delete Attribute') }}</x-danger-button>
                    </form>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
