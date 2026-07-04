<?php

namespace App\Support;

/**
 * Single source of truth for Codex media upload constraints.
 *
 * Both StoreCodexEntryRequest and UpdateCodexEntryRequest build their file rules
 * from here (guidelines: centralize validation, no magic numbers), and the entry
 * form reads the human-readable hints from the same place so the UI never drifts
 * from what the server actually accepts.
 */
class CodexMediaRules
{
    /**
     * Allowed image extensions (used for both the cover and reference images).
     *
     * @var array<int, string>
     */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Allowed reference-file extensions (documents, not images).
     *
     * @var array<int, string>
     */
    public const FILE_EXTENSIONS = ['pdf', 'txt', 'md', 'doc', 'docx'];

    /**
     * Maximum size for an image upload, in kilobytes (5 MB).
     */
    public const IMAGE_MAX_KILOBYTES = 5120;

    /**
     * Maximum size for a reference-file upload, in kilobytes (10 MB).
     */
    public const FILE_MAX_KILOBYTES = 10240;

    /**
     * Validation rules for the single cover image (optional).
     *
     * @return array<int, mixed>
     */
    public static function coverRules(): array
    {
        return ['nullable', 'image', 'mimes:'.implode(',', self::IMAGE_EXTENSIONS), 'max:'.self::IMAGE_MAX_KILOBYTES];
    }

    /**
     * Validation rules for each reference image in the reference_images[] array.
     *
     * @return array<int, mixed>
     */
    public static function referenceImageRules(): array
    {
        return ['image', 'mimes:'.implode(',', self::IMAGE_EXTENSIONS), 'max:'.self::IMAGE_MAX_KILOBYTES];
    }

    /**
     * Validation rules for each reference file in the reference_files[] array.
     *
     * @return array<int, mixed>
     */
    public static function referenceFileRules(): array
    {
        return ['file', 'mimes:'.implode(',', self::FILE_EXTENSIONS), 'max:'.self::FILE_MAX_KILOBYTES];
    }

    /**
     * "image/*"-style accept attribute for the image inputs.
     */
    public static function imageAccept(): string
    {
        return 'image/*';
    }

    /**
     * Comma-separated ".pdf,.txt,…" accept attribute for the reference-file input.
     */
    public static function fileAccept(): string
    {
        return '.'.implode(',.', self::FILE_EXTENSIONS);
    }

    /**
     * Human hint: "JPG, PNG, GIF, WEBP · up to 5 MB".
     */
    public static function imageHint(): string
    {
        return self::typesLabel(self::IMAGE_EXTENSIONS).' · '.self::sizeLabel(self::IMAGE_MAX_KILOBYTES);
    }

    /**
     * Human hint: "PDF, TXT, MD, DOC, DOCX · up to 10 MB".
     */
    public static function fileHint(): string
    {
        return self::typesLabel(self::FILE_EXTENSIONS).' · '.self::sizeLabel(self::FILE_MAX_KILOBYTES);
    }

    /**
     * @param  array<int, string>  $extensions
     */
    private static function typesLabel(array $extensions): string
    {
        return implode(', ', array_map('strtoupper', $extensions));
    }

    private static function sizeLabel(int $kilobytes): string
    {
        return 'up to '.intdiv($kilobytes, 1024).' MB';
    }
}
