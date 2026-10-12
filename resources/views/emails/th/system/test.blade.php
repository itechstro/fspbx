{{-- email-template
version: 1.1.1
language: th
category: system
subcategory: test
format: html
layout: standard
subject: {{ $email_subject }}
description: การทดสอบการส่งอีเมล
--}}
@extends('emails.th.email_layout')

@section('content')
<p>สวัสดี</p>

<p>นี่คืออีเมลทดสอบจาก {{ config('app.name', 'FS PBX') }}</p>

<p>หากคุณได้รับข้อความนี้ แสดงว่าบริการอีเมลที่ตั้งค่าไว้สามารถส่งอีเมลได้</p>

<p>ส่งเมื่อ {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}</p>
@endsection
