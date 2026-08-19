<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Blank dates select the current month.
 *
 * The span limit bounds the daily series that PHP creates in memory.
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
