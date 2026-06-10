Welcome to your {{ config('app.name', 'Laravel') }} app. Make sure to keep a copy of this email for future reference. Below are the simple steps to get started using the app:

Download the app for your device(s):

Google Play: {{ $attributes['google_play_link'] ?? '' }}
Apple Store: {{ $attributes['apple_store_link'] ?? '' }}
Get it for Windows ({{ $attributes['windows_link'] ?? '' }})
Download for Mac ({{ $attributes['mac_link'] ?? '' }})

Display name: {{ $attributes['name'] ?? ''}}
PBX Extension: {{ $attributes['extension'] ?? ''}}

Use these credentials to log in:

Domain: {{ $attributes['domain'] ?? ''}}
Username: {{ $attributes['username'] ?? ''}}
@if(!empty($attributes['password_url']))
Password: {{ $attributes['password_url'] }}
@elseif(!empty($attributes['password']))
Password: {{ $attributes['password'] }}
@endif

@if(!empty($attributes['qrCodeUrl']))
Scan the QR code in the HTML version of this email to sign in with the CloudPLAY app.
@endif

Once you have logged in, start communicating with the users within your organization. You can make and receive phone calls through your extension, put calls on hold, transfer calls, and much more.

If you have any questions, email our customer success team at {{ $attributes['support_email'] ?? '' }}.

Thanks,
{{ config('app.name', 'Laravel') }} Team
