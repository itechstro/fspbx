{{-- email-template
version: 1.1.0
language: zh-tw
category: authentication
subcategory: verification-code
format: html
layout: standard
subject: {{ $email_subject }}
description: 雙因素驗證碼
--}}
@extends('emails.email_layout')

@section('content')
<p>您好{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }}，</p>
<p>請使用以下驗證碼完成身分驗證：</p>
<p>您的 2FA 驗證碼：<b>{{ $attributes['code'] ?? '' }}</b></p>

<p>請使用上述驗證碼完成 {{ config('app.name', 'Laravel') }} 帳戶的登入程序。此額外步驟可確認確實是您本人正在存取帳戶。</p>

<p><b>為什麼會收到這封郵件？</b></p>

<p>只要啟用 2FA 或有人嘗試登入，就會觸發此安全措施。若這是您本人發起的操作，請使用驗證碼繼續。若您並未要求此驗證碼，無需採取任何行動——沒有驗證碼，帳戶仍保持安全。</p>

<p><b>並未要求此驗證碼？</b></p>

<p>若您沒有要求此驗證碼，或懷疑有未經授權的活動，請立即變更密碼並聯絡支援團隊以保護帳戶。</p>

<p>如有問題，請<a href="mailto:{{ $attributes["support_email"] ?? ''}}">寄信給我們的客戶成功團隊</a>。</p>

<p>請保持帳戶安全，<br>
{{ config('app.name', 'Laravel') }} 團隊</p>

<p><strong>附註：</strong>需要立即協助入門嗎？{{ config('app.name', 'Laravel') }} 支援團隊隨時可以幫忙！直接回覆此郵件即可。</p>
@endsection
