<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Welcome') }}
        </x-heading>
    </x-slot>

    <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-2 lg:items-start">
        <div class="space-y-6">
        <x-card>
            <p class="text-content">
                {{ __("Hi! Let's make your first project. You can change anything here later.") }}
            </p>
        </x-card>

        <x-collapsible-card :title="__('What\'s a project?')">
            <p class="text-content-muted">
                {{ __('A project is your universe. It holds all the books in your series and one codex you share across them. The codex is where you keep your worldbuilding: the people, the places, and the groups in your story.') }}
            </p>
        </x-collapsible-card>

        <x-collapsible-card :title="__('What are attributes?')">
            <div class="space-y-3 text-content-muted">
                <p>
                    {{ __("Attributes are the facts you track about a character, place, or group. A character has an age. A city has a ruler. A guild has a founding year. You pick which facts matter, and every character gets the same set, so you don't forget one.") }}
                </p>

                <p>
                    {{ __("We'll fill in a starter set for you, based on your kind of story. A fantasy world and a spy thriller need different facts, so an ancient Greek hero won't get a job title. Edit them, delete the ones you don't want, or add your own.") }}
                </p>
            </div>
        </x-collapsible-card>
        </div>

        <div class="space-y-6">
        <x-card>
            <form id="onboarding-form" method="POST" action="{{ route('onboarding.store') }}" class="space-y-6">
                @csrf

                <x-field name="name" :label="__('Project name')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                </x-field>

                <fieldset>
                    <legend class="font-medium text-content">{{ __('Pick your genre') }}</legend>
                    <p class="mt-1 text-sm text-content-muted">
                        {{ __("What kind of story is this? Your answer sets up the attributes, tags, and a few example entries. Writing something that doesn't fit? Skip and start blank below.") }}
                    </p>

                    {{-- Blank is offered by the "Skip and start blank" link below, not as a tile. --}}
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach (\App\Enums\Genre::cases() as $genre)
                            @continue($genre === \App\Enums\Genre::Blank)
                            <x-genre-option :genre="$genre" :checked="old('genre') === $genre->value" />
                        @endforeach
                    </div>

                    <x-input-error :messages="$errors->get('genre')" class="mt-2" />
                </fieldset>

                <div class="flex items-center justify-between border-t border-border pt-6">
                    <x-button form="onboarding-skip-form" variant="link" type="submit">
                        {{ __('Skip and start blank') }}
                    </x-button>

                    <x-button variant="primary" type="submit">
                        {{ __('Create project') }}
                    </x-button>
                </div>
            </form>

            <form id="onboarding-skip-form" method="POST" action="{{ route('onboarding.store') }}" class="hidden">
                @csrf
                <input type="hidden" name="name" value="{{ __('Untitled project') }}">
                <input type="hidden" name="genre" value="{{ \App\Enums\Genre::Blank->value }}">
            </form>
        </x-card>

        <x-card>
            <x-heading level="3">{{ __('Install demo projects') }}</x-heading>

            <p class="mt-2 text-content-muted">
                {{ __('Want to look around first? Install our sample project, The Roman of Melusine, and poke at a finished codex, timeline, and chapters. You can delete it whenever you like.') }}
            </p>

            <form method="POST" action="{{ route('onboarding.demo') }}" class="mt-4">
                @csrf

                <x-button variant="secondary" type="submit">
                    {{ __('Install demo projects') }}
                </x-button>
            </form>
        </x-card>
        </div>
    </div>
</x-app-layout>
