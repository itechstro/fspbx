{{-- email-template
version: 1.3.0
language: zh-tw
category: extension
subcategory: welcome
format: html
layout: standard
subject: 您的分機 {{ $attributes['extension'] }} 已準備就緒
description: 分機與語音信箱歡迎資訊
--}}
@extends('emails.email_layout')

@section('content')
<h1>歡迎，{{ $attributes['recipient_name'] }}！</h1>

<p>我們已為您設定分機 <strong>{{ $attributes['extension'] }}</strong>。請保留此郵件，以便查閱話機與語音信箱資料。</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>分機：</strong> {{ $attributes['extension'] }}</td>
        </tr>
        @if (!empty($attributes['direct_numbers']))
          <tr>
            <td class="attributes_item"><strong>直撥號碼：</strong> {{ implode(', ', $attributes['direct_numbers']) }}</td>
          </tr>
        @endif
        <tr>
          <td class="attributes_item"><strong>語音信箱：</strong> {{ $attributes['voicemail_id'] }}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>語音信箱 PIN：</strong> {{ $attributes['voicemail_pin'] }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<h2>設定語音信箱問候語</h2>
<ol>
  <li>從您的話機撥打 <strong>*97</strong>。</li>
  <li>輸入語音信箱 PIN，然後按 <strong>#</strong>。</li>
  <li>按 <strong>5</strong> 進入信箱選項。</li>
  <li>按 <strong>1</strong> 錄製無法接聽時的問候語。</li>
</ol>

@if (!empty($attributes['help_url']))
  <p>更多說明請見<a href="{{ $attributes['help_url'] }}">說明中心</a>。</p>
@endif

@if (!empty($attributes['support_email']))
  <p>如有問題，請寄信至 <a href="mailto:{{ $attributes['support_email'] }}">{{ $attributes['support_email'] }}</a>。</p>
@endif

<p>歡迎加入，<br>{{ $attributes['app_name'] }}</p>
@endsection
