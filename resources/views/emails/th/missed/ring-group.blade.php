{{-- email-template
version: 1.1.1
language: th
category: missed
subcategory: ring-group
format: html
layout: standard
subject: {{ $email_subject }}
description: การแจ้งเตือนสายที่ไม่ได้รับของกลุ่มเรียกพร้อมกัน
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>สายที่ไม่ได้รับ{{ $attributes['caller_id_number'] ? ' จาก ' . $attributes['caller_id_number'] : '' }}</h1>

<p>สายที่เข้ามาที่ {{ $attributes['ring_group_display'] ?: 'กลุ่มเรียกพร้อมกันของคุณ' }} ไม่มีผู้รับสาย</p>

<ul>
    <li><strong>จาก:</strong> {{ $attributes['caller_display'] ?: 'ผู้โทรไม่ทราบชื่อ' }}</li>
    <li><strong>ถึง:</strong> {{ $attributes['ring_group_display'] ?: 'กลุ่มเรียกพร้อมกัน' }}</li>
    @if (!empty($attributes['destination_number']))
        <li><strong>หมายเลขที่กด:</strong> {{ $attributes['destination_number'] }}</li>
    @endif
</ul>

<p>ขอบคุณ<br>ทีม {{ config('app.name', 'FS PBX') }}</p>
@endsection
