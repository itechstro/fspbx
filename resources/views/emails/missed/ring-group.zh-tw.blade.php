{{-- email-template
version: 1.1.0
language: zh-tw
category: missed
subcategory: ring-group
format: html
layout: standard
subject: {{ $email_subject }}
description: 響鈴群組未接來電通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>未接來電{{ $attributes['caller_id_number'] ? '，來自 ' . $attributes['caller_id_number'] : '' }}。</h1>

<p>撥打至 {{ $attributes['ring_group_display'] ?: '您的響鈴群組' }} 的來電無人接聽。</p>

<ul>
    <li><strong>來電者：</strong> {{ $attributes['caller_display'] ?: '未知來電者' }}</li>
    <li><strong>對象：</strong> {{ $attributes['ring_group_display'] ?: '響鈴群組' }}</li>
    @if (!empty($attributes['destination_number']))
        <li><strong>撥打號碼：</strong> {{ $attributes['destination_number'] }}</li>
    @endif
</ul>

<p>謝謝，<br>{{ config('app.name', 'FS PBX') }} 團隊</p>
@endsection
