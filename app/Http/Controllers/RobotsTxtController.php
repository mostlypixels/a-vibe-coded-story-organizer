<?php

namespace App\Http\Controllers;

use App\Models\CrawlerSetting;
use App\Services\RobotsTxtGenerator;
use Illuminate\Http\Response;

/**
 * Serves the dynamic /robots.txt, rendered live from the crawler settings
 * singleton. Public and unauthenticated — crawlers are anonymous. No CSRF
 * concern (GET). This route replaces the removed static public/robots.txt.
 */
class RobotsTxtController extends Controller
{
    public function __invoke(RobotsTxtGenerator $generator): Response
    {
        return response($generator->generate(CrawlerSetting::current()))
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
