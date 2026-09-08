<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuickEventRequest;
use App\Models\Project;
use App\Services\Concerns\CreatesInlineEvents;
use App\Support\DateFormat;
use App\Support\LocaleChoice;
use Illuminate\Http\JsonResponse;

/**
 * The AJAX counterpart to the "new event" fields on the scene and codex
 * forms. Those forms fall back to `CreatesInlineEvents::createInlineEvent()`
 * on submit; this endpoint calls the same trait so a picker can create the
 * event without leaving the page.
 */
class QuickEventController extends Controller
{
    use CreatesInlineEvents;

    public function store(StoreQuickEventRequest $request, Project $project): JsonResponse
    {
        $event = $this->createInlineEvent(
            $project,
            $request->validated('title'),
            $request->validated('event_datetime'),
        );

        $locale = LocaleChoice::resolve($request->user()->locale);

        return response()->json([
            'id' => $event->id,
            'title' => $event->title,
            'datetime' => $event->event_datetime->format('Y-m-d\TH:i'),
            'label' => $event->title.' — '.DateFormat::date($event->event_datetime, $locale),
        ], 201);
    }
}
