{{-- email-template
version: 1.0.0
language: zh-tw
category: authentication
subcategory: reset-password
format: html
layout: standard
subject: {{ $email_subject }}
description: 密碼重設連結
--}}
@extends('emails.email_layout')

@section('content')
<p>您好{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }}，</p>

<p>我們收到您 {{ config('app.name', 'Laravel') }} 帳戶的密碼重設請求，因此寄出這封郵件。</p>

<p><a href="{{ $attributes['url'] ?? '' }}">重設密碼</a></p>

<p>此密碼重設連結將於 {{ $attributes['expire_minutes'] ?? '' }} 分鐘後失效。</p>

<p>若您沒有申請重設密碼，無需採取任何動作。</p>

<p>如有任何問題，請<a href="mailto:{{ $attributes["support_email"] ?? ''}}">來信聯絡客戶成功團隊</a>。</p>

<p>請妥善保管帳戶安全，<br>
{{ config('app.name', 'Laravel') }} 團隊 敬上</p>

@endsection
