<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSceneShareRequest extends FormRequest
{
    /**
     * Authorization walks up to the owning project, mirroring the controller's
     * ProjectPolicy check ($scene->chapter->act->project).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('scene')->chapter->act->project);
    }

    /**
     * The duration is validated against the config whitelist so no lifetime is
     * ever hard-coded in a controller or view. Values are the CarbonInterval-
     * parseable strings from config/sharing.php.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'duration' => ['required', Rule::in(array_values(config('sharing.scene_link_durations')))],
        ];
    }
}
