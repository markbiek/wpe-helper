@component('mail::message')
# Plugin update report for {{ $install->name }}

## Updated plugins
@if (count($updated) > 0)
    @foreach ($updated as $plugin)
        * {{ $plugin->name }}
    @endforeach
@else
    No plugins were updated.
@endif

## Skipped plugins
@if (count($skipped) > 0)
    @foreach ($skipped as $plugin)
        * {{ $plugin->name }}
    @endforeach
@else
    No plugins were skipped.
@endif

@endcomponent
