{{-- Split the XML declaration so Blade does not parse it as a PHP open tag. --}}
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
