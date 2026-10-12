{{-- email-template
version: 1.0.1
language: th
category: authentication
subcategory: reset-password
format: html
layout: standard
subject: {{ $email_subject }}
description: ลิงก์รีเซ็ตรหัสผ่าน
--}}
@extends('emails.th.email_layout')

@section('content')
<p>สวัสดี{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }}</p>

<p>คุณได้รับอีเมลนี้เนื่องจากเราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชี {{ config('app.name', 'Laravel') }} ของคุณ</p>

<p><a href="{{ $attributes['url'] ?? '' }}">รีเซ็ตรหัสผ่าน</a></p>

<p>ลิงก์รีเซ็ตรหัสผ่านนี้จะหมดอายุใน {{ $attributes['expire_minutes'] ?? '' }} นาที</p>

<p>หากคุณไม่ได้ขอรีเซ็ตรหัสผ่าน ไม่ต้องดำเนินการใดเพิ่มเติม</p>

<p>หากมีคำถาม โปรด<a href="mailto:{{ $attributes["support_email"] ?? ''}}">ส่งอีเมลถึงทีมลูกค้าสัมพันธ์</a></p>

<p>โปรดรักษาความปลอดภัยของบัญชี<br>
ทีม {{ config('app.name', 'Laravel') }}</p>
@endsection
