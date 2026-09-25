<?php

/*
 * Field names for validation messages. Laravel merges this file over its own
 * validation.php, so only the names live here. Use the label the writer sees.
 * A request whose field has a different label on its form overrides it in attributes().
 */
return [
    'attributes' => [
        'act_id' => 'act',
        'aliases.*' => 'alias',
        'applies_to' => 'applies to',
        'attribute_baselines.*' => 'attribute value',
        'book_id' => 'book',
        'chapter_id' => 'chapter',
        'codex_attribute_id' => 'attribute',
        'divider_type' => 'divider style',
        'ends_on' => 'end date',
        'event_datetime' => 'date and time',
        'event_id' => 'event',
        'inception_event_id' => 'start event',
        'isbn' => 'ISBN',
        'max_archive_megabytes' => 'maximum archive size',
        'move_children_to' => 'destination',
        'new_event_datetime' => 'new event date and time',
        'new_event_title' => 'new event title',
        'new_inception_event_datetime' => 'new event date and time',
        'new_inception_event_title' => 'new event title',
        'new_termination_event_datetime' => 'new event date and time',
        'new_termination_event_title' => 'new event title',
        'plotlines.*' => 'plotline',
        'reference_files.*' => 'reference file',
        'reference_images.*' => 'reference image',
        'retention_days' => 'retention window',
        'start_event_id' => 'event',
        'starts_on' => 'start date',
        'tags.*' => 'tag',
        'termination_event_id' => 'end event',
        'user_agent_whitelist' => 'allowed crawlers',
        'user_agent_whitelist.*' => 'allowed crawler',
    ],

    'values' => [
        'recurrence' => [
            'none' => 'one-off',
        ],
    ],
];
