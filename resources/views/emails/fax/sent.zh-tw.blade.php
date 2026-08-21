{{-- email-template
version: 1.1.0
language: zh-tw
category: fax
subcategory: sent
format: html
layout: standard
subject: {{ $email_subject }}
description: 外送傳真成功通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>傳真已成功傳送至 {{ $attributes['fax_destination'] }}。</h1>

<p>傳真已於
{{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }} 成功傳送。</p>

@if (!empty($attributes['fax_pages']))
    <p>已傳送頁數：<strong>{{ $attributes['fax_pages'] }}</strong>{!! isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages'] ? ' / ' . $attributes['fax_total_pages'] : '' !!}。</p>
@endif

@if (!empty($attributes['fax_duration_formatted']))
    <p>傳送時間：{{ $attributes['fax_duration_formatted'] }}。</p>
@endif

<p>已傳送的傳真已附加於本郵件，供您存檔。</p>

<p>謝謝，<br>{{ config('app.name', 'Laravel') }} 團隊</p>
@endsection
