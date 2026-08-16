@extends('errors.layout')

@section('code', '500')
@section('title', __('Something went wrong on our side.'))
{{-- Never echo `$exception->getMessage()` here: a 500 message can hold a stack
     trace, a file path, or a database error. The user gets a fixed sentence. --}}
@section('message', __('The page did not load. Please try again in a moment.'))
