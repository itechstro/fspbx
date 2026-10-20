{{-- email-template
format: text
layout: none
--}}
Your TalkPhone account is ready. Open TalkPhone, choose Scan QR code on Account Setup, or paste this link:

{{ $attributes['provision_url'] }}

@if (!empty($attributes['display_name']))
Name: {{ $attributes['display_name'] }}
@endif
Extension: {{ $attributes['extension'] }}
Server: {{ $attributes['server'] }}

The link loads your account, codecs, and voicemail settings. Your password is not included in this email. After the form fills in, press Connect.
