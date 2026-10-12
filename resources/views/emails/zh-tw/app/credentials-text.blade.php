{{-- email-template
format: text
layout: none
--}}
歡迎使用 {{ config('app.name', 'Laravel') }} 應用程式。請保留此郵件以供日後參考。以下是開始使用應用程式的簡單步驟：

為您的裝置下載應用程式：

Google Play：{{ $attributes['google_play_link'] ?? '' }}
Apple Store：{{ $attributes['apple_store_link'] ?? '' }}
取得 Windows 版（{{ $attributes['windows_link'] ?? '' }}）
下載 Mac 版（{{ $attributes['mac_link'] ?? '' }}）

顯示名稱：{{ $attributes['name'] ?? ''}}
PBX 分機：{{ $attributes['extension'] ?? ''}}

請使用這些憑證登入：

網域：{{ $attributes['domain'] ?? ''}}
使用者名稱：{{ $attributes['login_username'] ?? $attributes['username'] ?? ''}}
@if(!empty($attributes['password_url']))
密碼：{{ $attributes['password_url'] }}
@elseif(!empty($attributes['password']))
密碼：{{ $attributes['password'] }}
@endif

@if(!empty($attributes['qrCodeUrl']))
請在本郵件的 HTML 版本中掃描 QR 碼，以 CloudPLAY 應用程式登入。
@endif

登入後即可開始與組織內的使用者通訊。您可以透過分機撥打與接聽電話、保留通話、轉接來電等。

如有問題，請寄信給我們的客戶成功團隊：{{ $attributes['support_email'] ?? '' }}。

謝謝，
{{ config('app.name', 'Laravel') }} 團隊
