@props(['html' => null, 'limit' => 120])

{{ Str::of($html ?? '')->stripTags()->squish()->limit($limit) }}
