<?php

namespace App\Http\Requests;

use App\Enums\StoryOverviewMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a save of a project's `overview_render_mode`. Authorization
 * mirrors StoryController::updateMode per CLAUDE.md: the write is ownership
 * of the project, walked via the route model binding.
 */
class UpdateStoryOverviewModeRequest extends FormRequest
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
            'overview_render_mode' => ['required', Rule::enum(StoryOverviewMode::class)],
        ];
    }
}
