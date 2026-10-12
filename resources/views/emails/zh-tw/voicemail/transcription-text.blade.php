{{-- email-template
format: text
layout: none
--}}
您有一則新的語音留言：

來電者：{{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}
收件信箱：{{ $attributes['dialed_user'] }}
收到時間：{{ $attributes['message_date'] }}
長度：{{ $attributes['message_duration'] }}

語音留言預覽：
{{ $attributes['message_text'] }}

@if($attributes['voicemail_file_mode'] === 'attach')
您可以從話機收聽此語音留言，或開啟附加的音訊檔。也可以登入帳戶管理並收聽語音信箱。
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
您可以從話機收聽此語音留言，或自此連結下載錄音：{{ $attributes['voicemail_download_url'] }}。也可以登入帳戶管理並收聽語音信箱。
@else
您可以從話機收聽此語音留言。也可以登入帳戶管理並收聽語音信箱。
@endif

如有問題，請寄信給我們的客戶成功團隊。（我們會盡快回覆。）
