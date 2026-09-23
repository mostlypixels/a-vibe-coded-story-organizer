<?php

namespace App\Support;

/**
 * The largest upload that PHP accepts, from `upload_max_filesize` and `post_max_size`.
 *
 * PHP drops a larger request before Laravel validates it. The import size setting
 * must not promise more than this limit.
 */
class ServerUploadLimit
{
    private string $uploadMaxFilesize;

    private string $postMaxSize;

    /** Tests give fixed values. The app reads php.ini. */
    public function __construct(?string $uploadMaxFilesize = null, ?string $postMaxSize = null)
    {
        $this->uploadMaxFilesize = $uploadMaxFilesize ?? (string) ini_get('upload_max_filesize');
        $this->postMaxSize = $postMaxSize ?? (string) ini_get('post_max_size');
    }

    /** Whole megabytes, or null when PHP sets no limit. */
    public function megabytes(): ?int
    {
        // A value of 0 or less means "no limit" in php.ini.
        $limits = array_filter(
            [ini_parse_quantity($this->uploadMaxFilesize), ini_parse_quantity($this->postMaxSize)],
            fn (int $bytes) => $bytes > 0,
        );

        return $limits === [] ? null : intdiv(min($limits), 1024 * 1024);
    }
}
