{{-- email-template
version: 1.1.0
language: zh-tw
category: emergency
subcategory: call
format: html
layout: standard
subject: {{ $email_subject }}
description: 緊急通話通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>緊急通話通知</h1>

<p>分機 <strong>{{ $attributes['caller'] }}</strong> 已撥打緊急電話。</p>

<p>請立即採取適當行動。</p>

<p>謝謝，請注意安全。</p>
@endsection
