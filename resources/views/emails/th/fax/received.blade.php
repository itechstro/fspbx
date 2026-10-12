{{-- email-template
version: 1.1.1
language: th
category: fax
subcategory: received
format: html
layout: standard
subject: {{ $email_subject }}
description: การแจ้งเตือนแฟกซ์ขาเข้า
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>ได้รับแฟกซ์แล้ว{{ $attributes['caller_id_number'] ? ' จาก ' . $attributes['caller_id_number'] : '' }}</h1>

<p>มีแฟกซ์ใหม่สำหรับ {{ $attributes['fax_destination'] }} และแนบมากับอีเมลนี้</p>

<ul>
    <li><strong>จาก:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>ถึง:</strong> {{ $attributes['fax_destination'] }}</li>
    <li><strong>จำนวนหน้า:</strong> {{ $attributes['fax_pages'] ?? '' }}</li>
    @if (!empty($attributes['fax_date']))
        <li><strong>ได้รับเมื่อ:</strong> {{ $attributes['fax_date'] }}</li>
    @endif
</ul>

@if (!empty($attributes['is_test']))
    <p><strong>นี่คืออีเมลทดสอบ</strong> ไม่ได้มีการส่งแฟกซ์จริง</p>
@endif

<p>แฟกซ์ถูกแนบเป็นไฟล์ {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}</p>

<p>หากมีคำถาม โปรด<a href="mailto:{{ $attributes['support_email'] ?? '' }}">ส่งอีเมลถึงทีมลูกค้าสัมพันธ์</a></p>
<p>ขอบคุณ<br>ทีม {{ config('app.name', 'Laravel') }}</p>
@endsection
