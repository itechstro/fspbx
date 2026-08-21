{{-- email-template
format: text
layout: none
--}}
已收到傳真{{ $attributes['caller_id_number'] ? '，來自 ' . $attributes['caller_id_number'] : '' }}。

{{ $attributes['fax_destination'] }} 收到一封新傳真，已附加於本郵件。

寄件者：{{ $attributes['caller_display'] }}
收件者：{{ $attributes['fax_destination'] }}
頁數：{{ $attributes['fax_pages'] ?? '' }}
@if (!empty($attributes['fax_date']))
收到時間：{{ $attributes['fax_date'] }}
@endif

傳真以 {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }} 檔案附加。

如有問題，請寄信給我們的客戶成功團隊：
{{ $attributes['support_email'] ?? '' }}

謝謝，
{{ config('app.name', 'Laravel') }} 團隊
