{{-- email-template
version: 1.1.0
language: zh-tw
category: fax
subcategory: service-alert
format: html
layout: standard
subject: {{ $email_subject }}
description: 傳真服務健康警示
--}}
@extends('emails.email_layout')

@section('content')
<h1>傳真服務警示</h1>

@if(isset($attributes["pendingFaxes"]))
    <p>有 {{ $attributes["pendingFaxes"] }} 封外送傳真已等待超過 {{ $attributes["waitTimeThreshold"] }} 分鐘。請檢查傳真服務狀態。</p>
@endif

@if(isset($attributes["failedFaxes"]))
    <p>最近處理的 {{ $attributes["totalChecked"] }} 封傳真中，有 {{ $attributes["failedFaxes"] }} 封失敗（失敗率 {{ $attributes["failureRate"] }}%）。</p>
    <p>這表示傳真服務可能發生問題。</p>
@endif

<p>謝謝，<br>{{ config('app.name', 'Laravel') }} 團隊</p>
@endsection
