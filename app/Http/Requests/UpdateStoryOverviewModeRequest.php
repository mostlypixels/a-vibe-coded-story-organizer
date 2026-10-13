<?php

namespace App\Http\Requests;

use App\Enums\StoryOverviewMode;
use App\Enums\StoryOverviewSceneDisplay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates a book's story overview mode and scene display. */
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
            'overview_render_mode' => ['required_without:overview_scene_display', Rule::enum(StoryOverviewMode::class)],
            'overview_scene_display' => ['required_without:overview_render_mode', Rule::enum(StoryOverviewSceneDisplay::class)],
        ];
    }
}
