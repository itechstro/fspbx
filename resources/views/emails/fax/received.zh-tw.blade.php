{{-- email-template
version: 1.1.0
language: zh-tw
category: fax
subcategory: received
format: html
layout: standard
subject: {{ $email_subject }}
description: 收到傳真通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>已收到傳真{{ $attributes['caller_id_number'] ? '，來自 ' . $attributes['caller_id_number'] : '' }}。</h1>

<p>{{ $attributes['fax_destination'] }} 收到一封新傳真，已附加於本郵件。</p>

<ul>
    <li><strong>寄件者：</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>收件者：</strong> {{ $attributes['fax_destination'] }}</li>
    <li><strong>頁數：</strong> {{ $attributes['fax_pages'] ?? '' }}</li>
    @if (!empty($attributes['fax_date']))
        <li><strong>收到時間：</strong> {{ $attributes['fax_date'] }}</li>
    @endif
</ul>

@if (!empty($attributes['is_test']))
    <p><strong>這是測試郵件。</strong>並未觸發實際傳真流程。</p>
@endif

<p>傳真以 {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }} 檔案附加。</p>

<p>如有問題，請<a href="mailto:{{ $attributes['support_email'] ?? '' }}">寄信給我們的客戶成功團隊</a>。</p>
<p>謝謝，<br>{{ config('app.name', 'Laravel') }} 團隊</p>
@endsection
