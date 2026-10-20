{{-- email-template
version: 1.0.0
language: en-us
category: talkphone
subcategory: provision
format: html
layout: standard
subject: {{ $attributes['email_subject'] ?? 'TalkPhone setup' }}
description: TalkPhone provisioning QR code
--}}
@extends('emails.email_layout')

@section('content')
<p>Your TalkPhone account is ready. Open TalkPhone, choose <strong>Scan QR code</strong> on Account Setup, and scan the code below. You can also paste the link.</p>

@if (!empty($attributes['qr_png']))
<p>
  <img src="{{ $message->embedData($attributes['qr_png'], 'talkphone-qr.png', 'image/png') }}" width="240" height="240" alt="TalkPhone provisioning QR code">
</p>
@endif

<p><a href="{{ $attributes['provision_url'] }}">{{ $attributes['provision_url'] }}</a></p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        @if (!empty($attributes['display_name']))
        <tr>
          <td class="attributes_item"><strong>Name:</strong> {{ $attributes['display_name'] }}</td>
        </tr>
        @endif
        <tr>
          <td class="attributes_item"><strong>Extension:</strong> {{ $attributes['extension'] }}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Server:</strong> {{ $attributes['server'] }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<p>The link loads your account, codecs, and voicemail settings. It does not include your password in this email. After the form fills in, press Connect.</p>
@endsection
