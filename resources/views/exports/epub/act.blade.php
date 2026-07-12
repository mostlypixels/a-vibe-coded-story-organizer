{{-- An Act divider page: "Act {position}" plus the Act's name on its own line.

     The name line is omitted entirely when the Act has no name (no empty second line),
     and the Act `description` is never rendered — only the number + name, per the grilled
     content decision. The <section class="act"> root is what styles.css page-breaks. --}}
@extends('exports.epub.layout', ['title' => 'Act '.$position])

@section('content')
    <section class="act">
        <h1>Act {{ $position }}</h1>
        @if (filled($name))
            <p class="act-name">{{ $name }}</p>
        @endif
    </section>
@endsection
