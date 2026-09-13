{{-- email-template
format: text
layout: none
--}}
您好{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }}，

我們收到您 {{ config('app.name', 'Laravel') }} 帳戶的密碼重設請求，因此寄出這封郵件。

重設密碼：{{ $attributes['url'] ?? '' }}

此密碼重設連結將於 {{ $attributes['expire_minutes'] ?? '' }} 分鐘後失效。

若您沒有申請重設密碼，無需採取任何動作。

如有任何問題，請來信聯絡客戶成功團隊：{{ $attributes['support_email'] ?? '' }}。

請妥善保管帳戶安全，
{{ config('app.name', 'Laravel') }} 團隊 敬上

請勿回覆此郵件。這是系統自動發送的訊息，回覆不會被處理。
