<nav x-data="{ open: false }" class="bg-navy-950 border-b border-black">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-12">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-6 w-auto fill-current text-white" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @if ($project = request()->route('project')
                            ?? request()->route('plotline')?->project
                            ?? request()->route('event')?->project
                            ?? request()->route('act')?->project
                            ?? request()->route('chapter')?->act?->project
                            ?? request()->route('scene')?->chapter?->act?->project
                            ?? request()->route('codexEntry')?->project
                            ?? request()->route('codexAttribute')?->project)
                        <x-nav-link :href="route('projects.show', $project)" :active="request()->routeIs('projects.show')">
                            {{ __('Home') }}
                        </x-nav-link>

                        <div class="flex items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-aqua-100 hover:text-white focus:outline-none transition duration-150 ease-in-out">
                                        {{ __('Timeline') }}

                                        <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link :href="route('projects.plotlines.index', $project)">
                                        {{ __('Plotlines') }}
                                    </x-dropdown-link>

                                    <x-dropdown-link :href="route('projects.events.index', $project)">
                                        {{ __('Events') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>

                        <div class="flex items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-aqua-100 hover:text-white focus:outline-none transition duration-150 ease-in-out">
                                        {{ __('Codex') }}

                                        <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    @foreach (\App\Enums\CodexEntryType::cases() as $codexType)
                                        <x-dropdown-link :href="route('projects.codex.index', [$project, $codexType->routeKey()])">
                                            {{ __($codexType->pluralLabel()) }}
                                        </x-dropdown-link>
                                    @endforeach

                                    <x-dropdown-link :href="route('projects.codex-attributes.index', $project)">
                                        {{ __('Attributes') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>

                        <div class="flex items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-aqua-100 hover:text-white focus:outline-none transition duration-150 ease-in-out">
                                        {{ __('Story') }}

                                        <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link :href="route('projects.story.index', $project)">
                                        {{ __('Story Overview') }}
                                    </x-dropdown-link>

                                    <x-dropdown-link :href="route('projects.acts.index', $project)">
                                        {{ __('Acts') }}
                                    </x-dropdown-link>

                                    <x-dropdown-link :href="route('projects.chapters.index', $project)">
                                        {{ __('Chapters') }}
                                    </x-dropdown-link>

                                    <x-dropdown-link :href="route('projects.scenes.index', $project)">
                                        {{ __('Scenes') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-aqua-100 bg-transparent hover:text-white focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <x-dropdown-link :href="route('crawler-settings.edit')">
                            {{ __('Site settings') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-aqua-100 hover:text-white hover:bg-navy-800 focus:outline-none focus:bg-navy-800 focus:text-white transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @if ($project = request()->route('project')
                    ?? request()->route('plotline')?->project
                    ?? request()->route('event')?->project
                    ?? request()->route('act')?->project
                    ?? request()->route('chapter')?->act?->project
                    ?? request()->route('scene')?->chapter?->act?->project
                    ?? request()->route('codexEntry')?->project
                    ?? request()->route('codexAttribute')?->project)
                <x-responsive-nav-link :href="route('projects.show', $project)" :active="request()->routeIs('projects.show')">
                    {{ __('Home') }}
                </x-responsive-nav-link>

                <div class="px-4 pt-2 pb-1 text-xs font-semibold text-aqua-200 uppercase tracking-wider">
                    {{ __('Timeline') }}
                </div>

                <x-responsive-nav-link :href="route('projects.plotlines.index', $project)" :active="request()->routeIs('projects.plotlines.*') || request()->routeIs('plotlines.*')">
                    {{ __('Plotlines') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('projects.events.index', $project)" :active="request()->routeIs('projects.events.*') || request()->routeIs('events.*')">
                    {{ __('Events') }}
                </x-responsive-nav-link>

                <div class="px-4 pt-2 pb-1 text-xs font-semibold text-aqua-200 uppercase tracking-wider">
                    {{ __('Codex') }}
                </div>

                @foreach (\App\Enums\CodexEntryType::cases() as $codexType)
                    <x-responsive-nav-link
                        :href="route('projects.codex.index', [$project, $codexType->routeKey()])"
                        :active="request()->route('type') === $codexType->routeKey() || request()->route('codexEntry')?->type === $codexType">
                        {{ __($codexType->pluralLabel()) }}
                    </x-responsive-nav-link>
                @endforeach

                <x-responsive-nav-link :href="route('projects.codex-attributes.index', $project)" :active="request()->routeIs('projects.codex-attributes.*') || request()->routeIs('codex-attributes.*')">
                    {{ __('Attributes') }}
                </x-responsive-nav-link>

                <div class="px-4 pt-2 pb-1 text-xs font-semibold text-aqua-200 uppercase tracking-wider">
                    {{ __('Story') }}
                </div>

                <x-responsive-nav-link :href="route('projects.story.index', $project)" :active="request()->routeIs('projects.story.*')">
                    {{ __('Story Overview') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('projects.acts.index', $project)" :active="request()->routeIs('projects.acts.*') || request()->routeIs('acts.*')">
                    {{ __('Acts') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('projects.chapters.index', $project)" :active="request()->routeIs('projects.chapters.*') || request()->routeIs('chapters.*')">
                    {{ __('Chapters') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('projects.scenes.index', $project)" :active="request()->routeIs('projects.scenes.*') || request()->routeIs('scenes.*')">
                    {{ __('Scenes') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-navy-800">
            <div class="px-4">
                <div class="font-medium text-base text-white">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-aqua-200">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('crawler-settings.edit')">
                    {{ __('Site settings') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
