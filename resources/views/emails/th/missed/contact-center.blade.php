{{-- email-template
version: 1.0.1
language: th
category: missed
subcategory: contact-center
format: html
layout: standard
subject: {{ $email_subject }}
description: การแจ้งเตือนสายที่วางก่อนได้รับบริการของศูนย์ติดต่อ
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>สายที่วางก่อนได้รับบริการ{{ $attributes['caller_id_number'] ? ' จาก '.$attributes['caller_id_number'] : '' }}</h1>

<p>ผู้โทรออกจาก {{ $attributes['queue_display'] }} ก่อนที่เจ้าหน้าที่จะรับสาย</p>

<ul>
    <li><strong>จาก:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>ศูนย์ติดต่อ:</strong> {{ $attributes['queue_display'] }}</li>
    <li><strong>สาเหตุ:</strong> {{ $attributes['departure_reason'] }}</li>
    @if (!empty($attributes['wait_duration']))
        <li><strong>เวลารอ:</strong> {{ $attributes['wait_duration'] }}</li>
    @endif
    <li><strong>รหัสสาย:</strong> {{ $attributes['call_uuid'] }}</li>
</ul>

<p>ขอบคุณ<br>ทีม {{ config('app.name', 'FS PBX') }}</p>
@endsection
