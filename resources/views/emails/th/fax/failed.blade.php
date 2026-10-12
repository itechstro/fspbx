{{-- email-template
version: 1.2.1
language: th
category: fax
subcategory: failed
format: html
layout: standard
subject: ตอบกลับ: แฟกซ์ถึง {{ $fax_destination }} ล้มเหลว
description: การแจ้งเตือนแฟกซ์ขาออกที่ส่งไม่สำเร็จ
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>แฟกซ์ของคุณถึง {{ $attributes['fax_destination'] }} ส่งไม่สำเร็จ</h1>
<p>{{ $attributes['email_message'] }}</p>
@endsection
