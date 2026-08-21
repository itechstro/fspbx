{{-- email-template
format: text
layout: none
--}}
傳真已成功傳送至 {{ $attributes['fax_destination'] }}。

傳真已於 {{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }} 成功傳送。

@if (!empty($attributes['fax_pages']))
已傳送頁數：{{ $attributes['fax_pages'] }}@if (isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages']) / {{ $attributes['fax_total_pages'] }}@endif。
@endif
@if (!empty($attributes['fax_duration_formatted']))
傳送時間：{{ $attributes['fax_duration_formatted'] }}。
@endif

已傳送的傳真已附加於本郵件，供您存檔。

謝謝，
{{ config('app.name', 'Laravel') }} 團隊
