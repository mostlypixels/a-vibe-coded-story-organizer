{{-- Shared XHTML shell for every epub content document (act divider + chapter page).

     EPUB 3 content documents are XHTML5, so this shell is XML-well-formed on purpose
     (task 05 parses every rendered fragment with DOMDocument::loadXML()): the XML
     declaration comes first, void elements self-close, and the root <html> carries the
     Project's language on both `lang` and `xml:lang` (the epub accessibility requirement).

     The XML declaration is emitted through {!! !!} rather than typed literally so PHP can
     never mistake `<?xml` for a short-open tag when the compiled Blade runs.

     Only styles.css is linked — one stylesheet holding nothing but the act/chapter
     page-break rules (the grilled "semantic HTML + minimal page-break CSS only" decision).
     The href is the flat filename because task 04 places styles.css beside the content
     documents inside the epub package. --}}
{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
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
