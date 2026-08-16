@extends('errors.layout')

@section('code', '403')
@section('title', __('This is not yours to open.'))
{{-- Never `$exception->getMessage()`: every abort(403) in the app leaves it at
     Laravel's "This action is unauthorized.", which says less than this line.
     The text also stays vague on purpose — it must not confirm that the
     resource exists. --}}
@section('message', __('You do not have access to this page. If you expected access, check that you are signed in to the right account.'))
