{{-- email-template
version: 1.1.1
language: th
category: emergency
subcategory: call
format: html
layout: standard
subject: {{ $email_subject }}
description: การแจ้งเตือนสายฉุกเฉิน
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>การแจ้งเตือนสายฉุกเฉิน</h1>

<p>มีการโทรฉุกเฉินจากหมายเลขภายใน <strong>{{ $attributes['caller'] }}</strong></p>

<p>โปรดดำเนินการที่เหมาะสมทันที</p>

<p>ขอบคุณ และโปรดรักษาความปลอดภัย</p>
@endsection
