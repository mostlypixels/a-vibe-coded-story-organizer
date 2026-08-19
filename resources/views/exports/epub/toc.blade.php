@extends('exports.epub.layout', ['title' => 'Table of Contents'])

@section('content')
    <section class="toc">
        <h1>Table of Contents</h1>
        <nav epub:type="toc">
            <ol>
                @foreach ($entries as $act)
                    <li>
                        <a href="{{ $act['href'] }}">{{ $act['label'] }}</a>
                        @if (! empty($act['chapters']))
                            <ol>
                                @foreach ($act['chapters'] as $chapter)
                                    <li>
                                        <a href="{{ $chapter['href'] }}">{{ $chapter['label'] }}</a>
                                        @if (! empty($chapter['scenes']))
                                            <ol>
                                                @foreach ($chapter['scenes'] as $scene)
                                                    <li><a href="{{ $scene['href'] }}">{{ $scene['label'] }}</a></li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    </section>
@endsection
