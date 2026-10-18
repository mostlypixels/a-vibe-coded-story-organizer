<?php

namespace App\Http\Requests;

use App\Enums\EditorCardState;
use App\Enums\LinkTargetType;
use App\Models\CodexEntry;
use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates one card state change on the Write page. */
class UpdateEditorCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('scene')->project());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([LinkTargetType::Codex->value, LinkTargetType::Note->value])],
            'id' => ['required', 'integer', 'min:1'],
            'state' => ['required', Rule::enum(EditorCardState::class)],
        ];
    }

    /** The codex entry or note in the scene's project, or a 404. Call it only after validation passes. */
    public function target(): CodexEntry|Note
    {
        /** @var CodexEntry|Note */
        return LinkTargetType::from($this->string('type')->value())
            ->queryFor($this->route('scene')->project())
            ->findOrFail($this->integer('id'));
    }
}
