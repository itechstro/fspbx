{{-- email-template
version: 1.0.0
language: zh-tw
category: missed
subcategory: contact-center
format: html
layout: standard
subject: {{ $email_subject }}
description: 聯絡中心放棄來電通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>放棄來電{{ $attributes['caller_id_number'] ? '，來自 '.$attributes['caller_id_number'] : '' }}。</h1>

<p>來電者在坐席接聽前離開了 {{ $attributes['queue_display'] }}。</p>

<ul>
    <li><strong>來電者：</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>聯絡中心：</strong> {{ $attributes['queue_display'] }}</li>
    <li><strong>原因：</strong> {{ $attributes['departure_reason'] }}</li>
    @if (!empty($attributes['wait_duration']))
        <li><strong>等待時間：</strong> {{ $attributes['wait_duration'] }}</li>
    @endif
    <li><strong>通話 ID：</strong> {{ $attributes['call_uuid'] }}</li>
</ul>

<p>謝謝，<br>{{ config('app.name', 'FS PBX') }} 團隊</p>
@endsection
