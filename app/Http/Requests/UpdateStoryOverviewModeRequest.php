<?php

namespace App\Http\Requests;

use App\Enums\StoryOverviewMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates a book's story overview mode. */
class UpdateStoryOverviewModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('book')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overview_render_mode' => ['required', Rule::enum(StoryOverviewMode::class)],
        ];
    }
}
