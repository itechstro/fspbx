{{-- email-template
version: 1.0.1
language: zh-tw
category: ai-agent
subcategory: send-email
format: html
layout: standard
subject: {{ $email_subject }}
description: AI 客服自訂功能寄出的後續追蹤郵件
--}}
@extends('emails.email_layout')

@section('content')
<h1>{{ $attributes['email_subject'] }}</h1>

<p>AI 客服已收集下列資訊，供後續追蹤：</p>

<ul>
    @foreach ($attributes['fields'] as $field)
        <li><strong>{{ $field['label'] }}:</strong> {{ $field['value'] }}</li>
    @endforeach
</ul>

@if (!empty($attributes['notes']))
<p><strong>補充資訊：</strong><br>{{ $attributes['notes'] }}</p>
@endif

@endsection
