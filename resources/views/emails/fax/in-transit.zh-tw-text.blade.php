{{-- email-template
format: text
layout: none
--}}
您的檔案正在傳真至 {{ $attributes['fax_destination'] }}。

傳送結果將另外通知您。

如有問題，請寄信給我們的客戶成功團隊：{{ $attributes['support_email'] ?? '' }}。

謝謝，
{{ config('app.name', 'Laravel') }} 團隊

附註：需要立即協助嗎？請造訪 {{ $attributes['help_url'] ?? '' }} 或回覆此郵件。
