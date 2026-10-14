<?php

namespace App\Support\Purifier;

use App\Support\EntityLinks;
use HTMLPurifier_Config;
use HTMLPurifier_Context;
use HTMLPurifier_URI;
use HTMLPurifier_URIScheme;

/**
 * Permits `entity:<type>/<id>` hrefs, the in-app links between project entities.
 *
 * Only an `href` takes it. The URI has no host, query or fragment. The sanitizer
 * removes any other form, so stored text holds only links that {@see EntityLinks}
 * can read back.
 */
class EntityUriScheme extends HTMLPurifier_URIScheme
{
    public $may_omit_host = true;

    /**
     * @param  HTMLPurifier_URI  $uri
     * @param  HTMLPurifier_Config  $config
     * @param  HTMLPurifier_Context  $context
     */
    public function doValidate(&$uri, $config, $context): bool
    {
        // An image source is never an entity link.
        return $context->get('CurrentAttr', true) === 'href'
            && $uri->userinfo === null
            && $uri->host === null
            && $uri->port === null
            && $uri->query === null
            && $uri->fragment === null
            && EntityLinks::parseTarget((string) $uri->path) !== null;
    }
}
