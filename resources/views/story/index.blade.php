<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }} &mdash; {{ __('Story Overview') }}
            </h2>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to Project') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-10">
            <h1 class="text-3xl font-bold text-gray-900">{{ __('Story Overview') }}</h1>

            <div x-data="{ open: true }" class="bg-white shadow-sm rounded-lg">
                <button type="button" @click="open = ! open" class="w-full flex items-center justify-between px-6 py-4 text-left">
                    <span class="font-semibold text-gray-800">{{ __('Table of Contents') }}</span>
                    <svg class="h-4 w-4 fill-current text-gray-500 transition-transform" :class="{ 'rotate-180': open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div x-show="open" x-transition class="px-6 pb-4 space-y-3">
                    @foreach ($acts as $act)
                        <div>
                            <a href="#act-{{ $act->id }}" class="font-semibold text-gray-800 hover:text-gray-600">
                                {{ __('Act :number', ['number' => $act->position]) }} &mdash; {{ $act->name }}
                            </a>

                            @if ($act->chapters->isNotEmpty())
                                <ul class="mt-1 ml-4 space-y-1">
                                    @foreach ($act->chapters as $chapter)
                                        <li>
                                            <a href="#chapter-{{ $chapter->id }}" class="text-sm text-gray-500 hover:text-gray-700">
                                                {{ __('Chapter :number', ['number' => $chapter->position]) }} &mdash; {{ $chapter->name }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            @forelse ($acts as $act)
                <div class="space-y-6">
                    <h2 id="act-{{ $act->id }}" class="text-2xl font-bold text-gray-900 border-b border-gray-300 pb-2 scroll-mt-16">
                        {{ __('Act :number', ['number' => $act->position]) }} &mdash; {{ $act->name }}
                    </h2>

                    @forelse ($act->chapters as $chapter)
                        <article class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                            <h3 id="chapter-{{ $chapter->id }}" class="text-xl font-semibold text-gray-800 scroll-mt-16">
                                {{ __('Chapter :number', ['number' => $chapter->position]) }} &mdash; {{ $chapter->name }}
                            </h3>

                            @forelse ($chapter->scenes as $scene)
                                <section x-data="{ open: true }" class="space-y-2 pb-4 border-b border-gray-200 last:border-b-0 last:pb-0">
                                    <div class="flex items-center justify-between">
                                        <button type="button" @click="open = ! open" class="flex items-center gap-2 text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                            <svg class="h-4 w-4 fill-current text-gray-500 transition-transform" :class="{ 'rotate-180': open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                            {{ $scene->name }}
                                        </button>

                                        <x-icon-edit-link :href="route('scenes.edit', $scene)" />
                                    </div>

                                    <div x-show="open" x-transition class="prose prose-sm max-w-none text-gray-700">
                                        {!! Str::markdown($scene->contents ?? '') !!}
                                    </div>
                                </section>
                            @empty
                                <p class="text-sm text-gray-500">{{ __('No scenes in this chapter yet.') }}</p>
                            @endforelse
                        </article>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('No chapters in this act yet.') }}</p>
                    @endforelse
                </div>
            @empty
                <p class="text-center text-gray-500">{{ __('No acts yet.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
