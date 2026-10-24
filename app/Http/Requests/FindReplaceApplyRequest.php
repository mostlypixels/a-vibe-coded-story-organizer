<?php

namespace App\Http\Requests;

use App\Services\SceneFindReplace;
use Illuminate\Contracts\Validation\Validator;

/**
 * The apply step of find and replace.
 *
 * `selection` is one JSON field, because one input for each match would pass
 * `max_input_vars`. It maps a scene ID to the hash of the contents the preview saw
 * and the indexes of the matches to replace: `{"14": {"hash": "…", "indexes": [0, 2]}}`.
 *
 * The scope fields come from {@see FindReplacePreviewRequest}. They only rebuild the
 * preview after the redirect.
 */
class FindReplaceApplyRequest extends FindReplacePreviewRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'selection' => ['required', 'string'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator) {
            // Not the `required` rule: it rejects a find text of spaces, which is valid.
            if ((string) $this->input('find') === '') {
                $validator->errors()->add('find', __('Enter the text to find.'));
            }

            if ($validator->errors()->has('selection') || ! is_string($this->input('selection'))) {
                return;
            }

            if ($this->selection() === null) {
                $validator->errors()->add('selection', __('The selection is not valid.'));
            }
        });
    }

    /**
     * The matches to replace, or null when the JSON has the wrong shape.
     *
     * @return array<int, array{hash: string, indexes: list<int>}>|null keyed by scene ID
     */
    public function selection(): ?array
    {
        $decoded = json_decode((string) $this->input('selection'), true);

        if (! is_array($decoded)) {
            return null;
        }

        $selection = [];
        $total = 0;

        foreach ($decoded as $sceneId => $entry) {
            if (preg_match('/^[1-9][0-9]*$/', (string) $sceneId) !== 1
                || ! is_array($entry)
                || ! is_string($entry['hash'] ?? null)
                || ! is_array($entry['indexes'] ?? null)
                || ! array_is_list($entry['indexes'])) {
                return null;
            }

            foreach ($entry['indexes'] as $index) {
                if (! is_int($index) || $index < 0) {
                    return null;
                }
            }

            $indexes = array_values(array_unique($entry['indexes']));
            sort($indexes);
            $total += count($indexes);

            if ($total > SceneFindReplace::MAX_MATCHES) {
                return null;
            }

            $selection[(int) $sceneId] = ['hash' => $entry['hash'], 'indexes' => $indexes];
        }

        return $selection;
    }
}
