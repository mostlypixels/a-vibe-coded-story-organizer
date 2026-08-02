{{-- $navigation is a ProjectNavigation, supplied by the view composer in
     AppServiceProvider. It owns the "which project / which active section"
     logic that used to be inline @php in this file. --}}
<nav x-data="{ open: false }" class="bg-nav">
    {{-- Primary Navigation Menu. Deliberately NOT inside the max-w-7xl container
         that <header> and <main> use: the logo anchors the left corner and the
         account menu the right, so the bar spans the viewport. The consequence is
         that on screens wider than 1280px the nav no longer lines up with the page
         content below it.

         The bar itself carries NO horizontal padding: the gutter lives inside the
         edge elements instead (logo on the left, account menu on the right), so the
         logo's yellow reaches the viewport edge while its mark still sits a
         comfortable distance in. --}}
    <div>
        <div class="flex justify-between h-12">
            <div class="flex">
                {{-- Logo. Shares the picker's nav-raised fill so the two read as one
                     block in the corner. --}}
                <div class="shrink-0 flex items-center bg-nav-raised px-2">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-6 w-auto fill-current text-nav-content" />
                    </a>
                </div>

                {{-- The project picker. Its list is capped — see
                     ProjectNavigation::otherProjects() for why, and why "All
                     projects" is not optional decoration. --}}
                <div class="hidden sm:flex">
                    {{-- width takes a raw class, not a number: the component maps
                         only the legacy '48'. `56` would render as a junk class and
                         leave the panel unsized. --}}
                    <x-dropdown align="left" width="w-56" offset-classes="mt-0">
                        <x-slot name="trigger">
                            {{-- Square corners, full bar height: the block reads as part of the
                                 bar's structure rather than a control floating on it. --}}
                            @if ($navigation->hasProject())
                                <button type="button" class="inline-flex h-12 items-center gap-2 bg-nav-raised px-4 text-sm font-semibold leading-5 text-nav-content hover:bg-nav-raised/80 focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-focus transition ease-in-out duration-150">
                                    {{ $navigation->project->name }}
                                    <x-tabler-chevron-down class="h-4 w-4 shrink-0" aria-hidden="true" />
                                </button>
                            @else
                                {{-- Nothing chosen yet: same block and fill — the flat
                                     vocabulary has no second nav-raised shade to dim the
                                     label with, so both states share nav-content. --}}
                                <button type="button" class="inline-flex h-12 items-center gap-2 bg-nav-raised px-4 text-sm font-semibold leading-5 text-nav-content hover:bg-nav-raised/80 focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-focus transition ease-in-out duration-150">
                                    {{ __('Choose a project') }}
                                    <x-tabler-chevron-down class="h-4 w-4 shrink-0" aria-hidden="true" />
                                </button>
                            @endif
                        </x-slot>

                        <x-slot name="content">
                            {{-- The open project is deliberately absent: the trigger
                                 already names it, so listing it again is a dead entry. --}}
                            @foreach ($navigation->otherProjects() as $otherProject)
                                <x-dropdown-link :href="route('projects.show', $otherProject)">
                                    {{ $otherProject->name }}
                                </x-dropdown-link>
                            @endforeach

                            <div class="border-t border-border"></div>

                            <x-dropdown-link :href="route('dashboard')">{{ __('All projects') }} &rarr;</x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-6 sm:flex">
                    @if ($navigation->hasProject())
                        <x-navigation.project-menu :navigation="$navigation" />
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 sm:pe-2">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-nav-content bg-transparent hover:text-nav-content focus:outline-hidden transition ease-in-out duration-150">
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

                        <x-dropdown-link :href="route('admin.index')">
                            {{ __('Configuration') }}
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
            {{-- pe-2, not the old -me-2: that negative margin existed to cancel the
                 bar's px-2, which is gone — it would now push the button off-screen. --}}
            <div class="pe-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-nav-content hover:bg-nav-raised focus:outline-hidden focus:bg-nav-raised transition duration-150 ease-in-out">
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
        {{-- Mobile half of the picker: the same capped list, laid out as rows
             rather than a panel. The open project stays visible here (marked
             active) because there is no trigger naming it. --}}
        <div class="px-4 py-3 border-b border-nav-raised">
            <div class="text-xs uppercase tracking-wide text-nav-content-muted mb-2">{{ __('Project') }}</div>

            @if ($navigation->hasProject())
                <x-responsive-nav-link :href="route('projects.show', $navigation->project)" :active="true">
                    {{ $navigation->project->name }}
                </x-responsive-nav-link>
            @endif

            @foreach ($navigation->otherProjects() as $otherProject)
                <x-responsive-nav-link :href="route('projects.show', $otherProject)">
                    {{ $otherProject->name }}
                </x-responsive-nav-link>
            @endforeach

            <x-responsive-nav-link :href="route('dashboard')">{{ __('All projects') }} &rarr;</x-responsive-nav-link>
        </div>

        <div class="pt-2 pb-3 space-y-1">
            @if ($navigation->hasProject())
                <x-navigation.responsive-project-menu :navigation="$navigation" />
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-nav-raised">
            <div class="px-4">
                <div class="font-medium text-base text-nav-content">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-nav-content-muted">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.index')">
                    {{ __('Configuration') }}
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
