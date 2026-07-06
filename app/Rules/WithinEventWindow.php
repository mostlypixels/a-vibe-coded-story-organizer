<?php

namespace App\Rules;

use App\Models\Event;
use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Constrains an event datetime to the project's [Start, End] bookend window.
 *
 * Regular events (create, edit, and the Scene inline "New event") must fall on or
 * between Start and End (inclusive). Editing a bookend flips the rule onto itself:
 * Start may not pass the earliest regular event (nor reach End), and End may not
 * precede the latest regular event (nor reach Start). That is the guarantee that
 * keeps Start/End the earliest/latest is_fixed events, so Project::startEvent()/
 * endEvent() — and the codex attribute-timeline baseline anchored at Start — never
 * resolve to a different row once the bookend dates become editable.
 */
class WithinEventWindow implements ValidationRule
{
    public function __construct(
        private Project $project,
        private ?Event $event = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $moment = Carbon::parse($value);
        } catch (Throwable) {
            return; // Malformed input is the `date` rule's job to report.
        }

        $start = $this->project->startEvent();
        $end = $this->project->endEvent();

        // Editing a bookend: its date is bounded by the opposite bookend and the
        // nearest regular event, not by the [Start, End] window itself.
        if ($this->event?->is_fixed && $this->event->is($start)) {
            $earliest = $this->project->earliestRegularEvent();

            if ($earliest && $moment->gt($earliest->event_datetime)) {
                $fail('The Start event cannot be later than the earliest event (:title).')
                    ->translate([':title' => $earliest->title]);
            }

            if ($moment->gte($end->event_datetime)) {
                $fail('The Start event must be before the End event.')->translate();
            }

            return;
        }

        if ($this->event?->is_fixed && $this->event->is($end)) {
            $latest = $this->project->latestRegularEvent();

            if ($latest && $moment->lt($latest->event_datetime)) {
                $fail('The End event cannot be earlier than the latest event (:title).')
                    ->translate([':title' => $latest->title]);
            }

            if ($moment->lte($start->event_datetime)) {
                $fail('The End event must be after the Start event.')->translate();
            }

            return;
        }

        // Regular event (create or edit) or the Scene inline new event.
        if ($moment->lt($start->event_datetime)) {
            $fail('The event cannot be earlier than the Start event (:datetime).')
                ->translate([':datetime' => $start->event_datetime->format('Y-m-d H:i')]);
        }

        if ($moment->gt($end->event_datetime)) {
            $fail('The event cannot be later than the End event (:datetime).')
                ->translate([':datetime' => $end->event_datetime->format('Y-m-d H:i')]);
        }
    }
}
