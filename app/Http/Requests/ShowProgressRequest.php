<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Progress page's range picker (`?from=&to=`).
 *
 * Authorization mirrors the controller: the chart reads a project's history, so
 * it flows from the project via ProjectPolicy::view (CLAUDE.md § Authorization).
 *
 * Both fields are nullable — an absent range is the normal landing state, and
 * `ProgressController` fills in the current month. The span cap exists because
 * the series materialises one entry per day in PHP; a hand-edited URL asking
 * for a decade of points is a memory question, not a valid range.
 */
class ShowProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->filled('from')) {
                        return;
                    }

                    $span = CarbonImmutable::parse($this->input('from'))
                        ->diffInDays(CarbonImmutable::parse($value));

                    if ($span > 366) {
                        $fail(__('The range cannot span more than 366 days.'));
                    }
                },
            ],
        ];
    }
}
