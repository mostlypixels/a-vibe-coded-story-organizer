<nav x-data="{ open: false }" class="bg-nav">
    <div>
        <div class="flex justify-between h-12">
            <div class="flex">
                <div class="shrink-0 flex items-center bg-nav-raised px-2">
                    <a href="{{ $navigation->homeUrl() }}">
                        <x-application-logo class="block h-6 w-auto fill-current text-nav-content" />
                    </a>
                </div>

                <div class="hidden sm:flex">
                    <x-dropdown align="left" width="w-56" offset-classes="mt-0">
                        <x-slot name="trigger">
                            @if ($navigation->hasBook())
                                <button type="button" class="inline-flex h-12 items-center gap-2 bg-nav-raised px-4 text-sm font-semibold leading-5 text-nav-content hover:bg-nav-raised/80 focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-focus transition ease-in-out duration-150">
                                    <span class="flex flex-col items-start leading-tight">
                                        <span>{{ $navigation->book->displayName() }}</span>
                                        @if ($navigation->book->hasOwnName())
                                            <span class="text-xs font-normal text-nav-content-muted">{{ $navigation->project->name }}</span>
                                        @endif
                                    </span>
                                    <x-tabler-chevron-down class="h-4 w-4 shrink-0" aria-hidden="true" />
                                </button>
                            @else
                                <button type="button" class="inline-flex h-12 items-center gap-2 bg-nav-raised px-4 text-sm font-semibold leading-5 text-nav-content hover:bg-nav-raised/80 focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-focus transition ease-in-out duration-150">
                                    {{ __('Choose a project') }}
                                    <x-tabler-chevron-down class="h-4 w-4 shrink-0" aria-hidden="true" />
                                </button>
                            @endif
                        </x-slot>

                        <x-slot name="content">
                            @if ($navigation->hasBook())
                                @foreach ($navigation->projectBooks() as $projectBook)
                                    <x-dropdown-link :href="route('books.select', $projectBook)" :active="$projectBook->is($navigation->routeBook)">
                                        {{ $projectBook->displayName() }}
                                    </x-dropdown-link>
                                @endforeach

                                <x-dropdown-link :href="route('projects.books.index', $navigation->project)" :active="$navigation->booksActive">
                                    {{ __('Manage books') }} &rarr;
                                </x-dropdown-link>

                                <div class="border-t border-border"></div>
                            @endif

                            @foreach ($navigation->otherProjects() as $otherProject)
                                <x-navigation.section-heading>{{ $otherProject->name }}</x-navigation.section-heading>

                                @foreach ($otherProject->books as $otherBook)
                                    <div class="pl-4">
                                        <x-dropdown-link :href="route('books.select', $otherBook)">
                                            {{ $otherBook->displayName() }}
                                        </x-dropdown-link>
                                    </div>
                                @endforeach
                            @endforeach

                            <div class="border-t border-border"></div>

                            <x-dropdown-link :href="route('projects.index')">{{ __('All projects') }} &rarr;</x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-6 sm:flex">
                    @if ($navigation->hasProject())
                        <x-navigation.project-menu :navigation="$navigation" />
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 sm:pe-2">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-nav-content bg-transparent hover:text-nav-content focus:outline-hidden transition ease-in-out duration-150">
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

            <div class="pe-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-nav-content hover:bg-nav-raised focus:outline-hidden focus:bg-nav-raised transition duration-150 ease-in-out">
                    <x-tabler-menu-2 class="h-6 w-6" x-bind:class="{ 'hidden': open }" />
                    <x-tabler-x class="h-6 w-6" x-bind:class="{ 'hidden': ! open }" />
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="px-4 py-3 border-b border-nav-raised">
            <div class="text-xs uppercase tracking-wide text-nav-content-muted mb-2">{{ __('Project') }}</div>

            @if ($navigation->hasBook())
                @foreach ($navigation->projectBooks() as $projectBook)
                    <x-responsive-nav-link :href="route('books.select', $projectBook)" :active="$projectBook->is($navigation->routeBook)">
                        {{ $projectBook->displayName() }}
                    </x-responsive-nav-link>
                @endforeach

                <x-responsive-nav-link :href="route('projects.books.index', $navigation->project)" :active="$navigation->booksActive">
                    {{ __('Manage books') }} &rarr;
                </x-responsive-nav-link>
            @endif

            @foreach ($navigation->otherProjects() as $otherProject)
                <x-navigation.section-heading>{{ $otherProject->name }}</x-navigation.section-heading>

                @foreach ($otherProject->books as $otherBook)
                    <div class="pl-4">
                        <x-responsive-nav-link :href="route('books.select', $otherBook)">
                            {{ $otherBook->displayName() }}
                        </x-responsive-nav-link>
                    </div>
                @endforeach
            @endforeach

            <x-responsive-nav-link :href="route('projects.index')">{{ __('All projects') }} &rarr;</x-responsive-nav-link>
        </div>

        <div class="pt-2 pb-3 space-y-1">
            @if ($navigation->hasProject())
                <x-navigation.responsive-project-menu :navigation="$navigation" />
            @endif
        </div>

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
