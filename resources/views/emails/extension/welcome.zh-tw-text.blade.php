{{-- email-template
format: text
layout: none
--}}
歡迎，{{ $attributes['recipient_name'] }}！

我們已為您設定分機 {{ $attributes['extension'] }}。請保留此郵件，以便查閱話機與語音信箱資料。

分機：{{ $attributes['extension'] }}
@if (!empty($attributes['direct_numbers']))
直撥號碼：{{ implode(', ', $attributes['direct_numbers']) }}
@endif
語音信箱：{{ $attributes['voicemail_id'] }}
語音信箱 PIN：{{ $attributes['voicemail_pin'] }}

設定語音信箱問候語

1. 從您的話機撥打 *97。
2. 輸入語音信箱 PIN，然後按 #。
3. 按 5 進入信箱選項。
4. 按 1 錄製無法接聽時的問候語。

@if (!empty($attributes['help_url']))
說明：{{ $attributes['help_url'] }}
@endif
@if (!empty($attributes['support_email']))
如有問題，請寄信至 {{ $attributes['support_email'] }}。
@endif

歡迎加入，
{{ $attributes['app_name'] }}
