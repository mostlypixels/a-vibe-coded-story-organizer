{{-- The reduced bar the error pages carry: logo, project picker, Configuration.
     `layouts/navigation` cannot serve here — it highlights the active section
     off the route, and on an error page the route is the one that just failed.
     $navigation comes from the view composer in AppServiceProvider, built with
     ProjectNavigation::offRoute() so no route parameter reaches the picker.

     Guests see the bare bar: the links below all sit behind `auth`. --}}
<nav class="bg-nav">
    <div class="flex justify-between h-12">
        <div class="flex">
            <div class="shrink-0 flex items-center bg-nav-raised px-2">
                <a href="{{ auth()->check() ? route('dashboard') : url('/') }}">
                    <x-application-logo class="block h-6 w-auto fill-current text-nav-content" />
                </a>
            </div>

            @auth
                {{-- Same picker as the main bar, and the same cap: a shortcut to
                     five projects, with "All projects" as the complete answer. --}}
                <x-dropdown align="left" width="w-56" offset-classes="mt-0">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex h-12 items-center gap-2 bg-nav-raised px-4 text-sm font-semibold leading-5 text-nav-content hover:bg-nav-raised/80 focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-focus transition ease-in-out duration-150">
                            {{ $navigation->hasProject() ? $navigation->project->name : __('Choose a project') }}
                            <x-tabler-chevron-down class="h-4 w-4 shrink-0" aria-hidden="true" />
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @foreach ($navigation->otherProjects() as $otherProject)
                            <x-dropdown-link :href="route('projects.show', $otherProject)">
                                {{ $otherProject->name }}
                            </x-dropdown-link>
                        @endforeach

                        <div class="border-t border-border"></div>

                        <x-dropdown-link :href="route('dashboard')">{{ __('All projects') }} &rarr;</x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            @endauth
        </div>

        @auth
            <div class="flex items-center pe-2">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-nav-content bg-transparent focus:outline-hidden transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <x-tabler-chevron-down class="h-4 w-4" />
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
        @endauth
    </div>
</nav>
