{{-- email-template
version: 1.1.0
language: zh-tw
category: system
subcategory: test
format: html
layout: standard
subject: {{ $email_subject }}
description: 電子郵件傳送測試
--}}
@extends('emails.email_layout')

@section('content')
<p>您好，</p>

<p>這是來自 {{ config('app.name', 'FS PBX') }} 的測試郵件。</p>

<p>若您收到此訊息，表示目前設定的郵件服務可以正常寄信。</p>

<p>寄送時間：{{ $attributes['sent_at'] ?? now()->toDateTimeString() }}。</p>
@endsection
