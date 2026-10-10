<?php

namespace App\Http\Requests;

use App\Enums\NoteLinkType;
use App\Rules\NoteLinkTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class StoreNoteLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('note')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = NoteLinkType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', 'string', Rule::enum(NoteLinkType::class)],
            // An unknown type already fails above; the target rule needs a valid one.
            'id' => array_filter(['required', 'integer', $type ? new NoteLinkTarget($this->route('note')->project, $type) : null]),
        ];
    }

    /** The resolved target. Call it only after validation passes. */
    public function target(): Model
    {
        return NoteLinkTarget::resolve($this->route('note')->project, NoteLinkType::from($this->string('type')->value()), $this->input('id'))
            ?? throw new LogicException('The link target is not valid.');
    }
}
