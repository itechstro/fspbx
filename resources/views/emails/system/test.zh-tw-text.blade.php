{{-- email-template
format: text
layout: none
--}}
您好，

這是來自 {{ config('app.name', 'FS PBX') }} 的測試郵件。

若您收到此訊息，表示目前設定的郵件服務可以正常寄信。

寄送時間：{{ $attributes['sent_at'] ?? now()->toDateTimeString() }}。
