{{-- email-template
version: 1.1.1
language: th
category: fax
subcategory: service-alert
format: html
layout: standard
subject: {{ $email_subject }}
description: การแจ้งเตือนสถานะบริการแฟกซ์
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>การแจ้งเตือนบริการแฟกซ์</h1>

@if(isset($attributes["pendingFaxes"]))
    <p>มีแฟกซ์ขาออก {{ $attributes["pendingFaxes"] }} รายการที่รอดำเนินการนานกว่า {{ $attributes["waitTimeThreshold"] }} นาที โปรดตรวจสอบสถานะบริการแฟกซ์</p>
@endif

@if(isset($attributes["failedFaxes"]))
    <p>แฟกซ์ที่ประมวลผลล่าสุด {{ $attributes["totalChecked"] }} รายการ มี {{ $attributes["failedFaxes"] }} รายการที่ล้มเหลว (อัตราล้มเหลว {{ $attributes["failureRate"] }}%)</p>
    <p>สิ่งนี้อาจบ่งชี้ปัญหาของบริการแฟกซ์</p>
@endif

<p>ขอบคุณ<br>ทีม {{ config('app.name', 'Laravel') }}</p>
@endsection
