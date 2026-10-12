{{-- email-template
version: 1.1.1
language: th
category: fax
subcategory: sent
format: html
layout: standard
subject: {{ $email_subject }}
description: การแจ้งเตือนแฟกซ์ขาออกที่ส่งสำเร็จ
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>แฟกซ์ของคุณถึง {{ $attributes['fax_destination'] }} ถูกส่งสำเร็จแล้ว</h1>

<p>แฟกซ์ถูกส่งสำเร็จเมื่อ
{{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}</p>

@if (!empty($attributes['fax_pages']))
    <p>จำนวนหน้าที่ส่ง: <strong>{{ $attributes['fax_pages'] }}</strong>{!! isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages'] ? ' จาก ' . $attributes['fax_total_pages'] : '' !!}</p>
@endif

@if (!empty($attributes['fax_duration_formatted']))
    <p>ระยะเวลา: {{ $attributes['fax_duration_formatted'] }}</p>
@endif

<p>แฟกซ์ที่ส่งถูกแนบมากับอีเมลนี้เพื่อเก็บเป็นหลักฐาน</p>

<p>ขอบคุณ<br>ทีม {{ config('app.name', 'Laravel') }}</p>
@endsection
