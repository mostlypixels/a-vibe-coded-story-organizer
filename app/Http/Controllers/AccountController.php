<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * The account hub. It holds only links; Profile and Configuration each
     * check their own access, so this page needs no policy of its own.
     */
    public function home(): View
    {
        return view('account.home');
    }
}
