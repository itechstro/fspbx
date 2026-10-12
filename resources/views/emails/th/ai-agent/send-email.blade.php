{{-- email-template
version: 1.0.2
language: th
category: ai-agent
subcategory: send-email
format: html
layout: standard
subject: {{ $email_subject }}
description: อีเมลติดตามผลที่ส่งโดยฟังก์ชันกำหนดเองของ AI Agent
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>{{ $attributes['email_subject'] }}</h1>

<p>AI Agent ได้รวบรวมข้อมูลต่อไปนี้สำหรับการติดตามผล:</p>

<ul>
    @foreach ($attributes['fields'] as $field)
        <li><strong>{{ $field['label'] }}:</strong> {{ $field['value'] }}</li>
    @endforeach
</ul>

@if (!empty($attributes['notes']))
<p><strong>ข้อมูลเพิ่มเติม:</strong><br>{{ $attributes['notes'] }}</p>
@endif

@endsection
