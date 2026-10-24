<?php

namespace App\Http\Requests;

use App\Support\FindReplaceQuery;

/**
 * The find and replace form. A blank `find` shows the form without a preview.
 *
 * The scope fields and their cross-checks come from {@see SearchRequest}. The search
 * text and the match mode do not apply here, so their rules go.
 *
 * The check boxes are on by default. The form sends a hidden `0` before each, so a
 * missing field means a first visit and an unticked box means off.
 */
class FindReplacePreviewRequest extends SearchRequest
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
        $scopeRules = collect(parent::rules())->except(['q', 'mode', 'domains', 'domains.*'])->all();

        return $scopeRules + [
            'find' => ['nullable', 'string', 'max:'.FindReplaceQuery::MAX_FIND_LENGTH],
            'replace' => ['nullable', 'string', 'max:'.FindReplaceQuery::MAX_REPLACE_LENGTH],
            'match_case' => ['nullable', 'boolean'],
            'whole_word' => ['nullable', 'boolean'],
        ];
    }

    public function findReplaceQuery(): FindReplaceQuery
    {
        return new FindReplaceQuery(
            find: (string) $this->validated('find'),
            replace: (string) $this->validated('replace'),
            matchCase: $this->boolean('match_case', true),
            wholeWord: $this->boolean('whole_word', true),
        );
    }
}
