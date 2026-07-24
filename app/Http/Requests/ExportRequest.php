<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes a project export.
 *
 * The /admin area sits behind the `access-admin` gate (any authenticated user),
 * which is NOT ownership. Because the export reads one user-owned project, this
 * request also walks ProjectPolicy: a foreign or missing project_id is a 403,
 * never a silent export of another user's project. The controller mirrors the
 * same authorize('view', $project) check.
 */
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
            // Unchecked checkboxes are absent from the request; read the value with
            // $request->boolean('include_images') so absent means false.
            'include_images' => ['sometimes', 'boolean'],
            // Same absent-means-false pattern for the revision-history toggle
            // (task 14, autosave-with-revisions).
            'include_revisions' => ['sometimes', 'boolean'],
        ];
    }
}
