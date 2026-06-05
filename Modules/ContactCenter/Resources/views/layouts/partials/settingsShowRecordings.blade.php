<div>
    @include('layouts.partials.greetingSelector', [
        'id' => 'queue_greeting',
        'allRecordings' => $recordings,
        'value' => $queue->queue_greeting ?? null,
        'hint' => 'Select the greeting callers hear when they enter the Contact Center queue. This greeting plays once before the hold music begins.',
        'inlineScripts' => false
    ])
</div>
