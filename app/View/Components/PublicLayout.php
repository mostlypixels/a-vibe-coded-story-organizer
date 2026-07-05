<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The layout for public, unauthenticated pages (currently the shared-scene
 * view). Mirrors Breeze's AppLayout/GuestLayout class-component convention so
 * `<x-public-layout>` renders `layouts/public.blade.php` — a slim, no-nav
 * reading page whose <head> carries the `noindex` robots meta.
 */
class PublicLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.public');
    }
}
