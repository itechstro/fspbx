{{-- email-template
version: 1.2.1
language: th
category: fax
subcategory: invalid-destination
format: html
layout: standard
subject: แฟกซ์ถึง {{ $invalid_number }} ล้มเหลว - หมายเลขปลายทางไม่ถูกต้อง
description: การแจ้งเตือนหมายเลขปลายทางแฟกซ์ที่ไม่ถูกต้อง
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>แฟกซ์ของคุณถึง {{ $attributes['invalid_number'] }} ส่งไม่สำเร็จ</h1>
<p>หมายเลขปลายทางไม่ใช่หมายเลขโทรศัพท์สหรัฐอเมริกาที่ถูกต้อง</p>
@endsection
