<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorizes an archive export through the selected project. */
class ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::find($this->input('project_id'));

        return $project !== null && $this->user()->can('view', $project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'include_images' => ['sometimes', 'boolean'],
        ];
    }
}
