<?php

namespace App\Http\Requests;

use App\Rules\WithinEventWindow;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuickEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'event_datetime' => ['required', 'date', new WithinEventWindow($this->route('project'))],
        ];
    }
}
