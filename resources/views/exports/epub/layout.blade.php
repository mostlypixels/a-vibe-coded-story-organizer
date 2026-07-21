{{-- Shared XHTML shell for every epub content document (act divider + chapter page).

     EPUB 3 content documents are XHTML5, so this shell is XML-well-formed on purpose
     (task 05 parses every rendered fragment with DOMDocument::loadXML()): the XML
     declaration comes first, void elements self-close, and the root <html> carries the
     Project's language on both `lang` and `xml:lang` (the epub accessibility requirement).

     The XML declaration is built by concatenating '<' and '?xml ... ?>' separately rather
     than as one literal string, because Blade tokenizes the raw template with PHP's own
     lexer before {!! !!} is compiled into a real echo statement: if `<?xml` appears adjacent
     in the source, PHP's lexer reads it as a short-open tag during that pre-compilation
     pass and the build fails with `unexpected identifier "version"`, regardless of the
     {!! !!} wrapping.

     Only styles.css is linked — one stylesheet holding nothing but the act/chapter
     page-break rules (the grilled "semantic HTML + minimal page-break CSS only" decision).
     The href is the flat filename because task 04 places styles.css beside the content
     documents inside the epub package. --}}
{!! '<' . '?xml version="1.0" encoding="UTF-8"?>' !!}
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="{{ $language }}" xml:lang="{{ $language }}">
<head>
    <meta charset="utf-8"/>
    <title>{{ $title }}</title>
    <link rel="stylesheet" type="text/css" href="styles.css"/>
</head>
<body>
@yield('content')
</body>
</html>
