{{-- Shared shell for the book/ reading layer. Self-contained: minimal INLINE CSS
     only (no external assets), so a chapter page opens straight from the unzipped
     archive. The book/ layer is the human reading version — the one place Markdown
     is rendered to HTML (see documentation/export-format.md). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('Book') }}</title>
    <style>
        body {
            font-family: Georgia, "Times New Roman", serif;
            line-height: 1.65;
            color: #1a1a1a;
            background: #ffffff;
            max-width: 40rem;
            margin: 0 auto;
            padding: 2.5rem 1.25rem 4rem;
        }
        a { color: #023047; }
        h1 { font-size: 1.9rem; line-height: 1.2; margin: 0 0 1.5rem; }
        h2 { font-size: 1.35rem; margin: 2rem 0 0.5rem; }
        hr {
            border: none;
            border-top: 1px solid #cccccc;
            width: 40%;
            margin: 2.5rem auto;
        }
        nav.reading-nav {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin: 1.5rem 0;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 0.9rem;
        }
        .toc ul { list-style: none; padding-left: 1rem; margin: 0.5rem 0 0; }
        .toc li { margin: 0.35rem 0; }
        .toc a { text-decoration: none; }
        .toc a:hover { text-decoration: underline; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
