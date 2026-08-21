{{-- email-template
version: 1.2.0
language: zh-tw
category: fax
subcategory: in-transit
format: html
layout: standard
subject: 回覆：傳真至 {{ $fax_destination }}
description: 外送傳真已受理通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>您的檔案正在傳真至 {{ $attributes['fax_destination'] }}。</h1>
<p>傳送結果將另外通知您。</p>

<p>如有問題，請<a href="mailto:{{ $attributes["support_email"] ?? ''}}">寄信給我們的客戶成功團隊</a>。（我們會盡快回覆。）</p>
<p>謝謝，
  <br>{{ config('app.name', 'Laravel') }} 團隊</p>
<p><strong>附註：</strong>需要立即協助入門嗎？{{ config('app.name', 'Laravel') }} 支援團隊隨時可以幫忙！請參閱我們的<a href="{{ $attributes["help_url"] ?? ''}}">說明文件</a>，或直接回覆此郵件。</p>
@endsection
