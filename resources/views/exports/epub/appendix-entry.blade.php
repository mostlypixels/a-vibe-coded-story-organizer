{{-- EpubExporter prepares the image path and converts the description to trusted XHTML. --}}
@extends('exports.epub.layout', ['title' => $name])

@section('content')
    <section class="codex-entry">
        <h1>{{ $name }}</h1>
        @if ($imagePath !== null)
            <div class="codex-image"><img src="{{ $imagePath }}" alt="{{ $name }}"/></div>
        @endif
        @if ($description !== '')
            <div class="codex-description">{!! $description !!}</div>
        @endif
    </section>
@endsection
