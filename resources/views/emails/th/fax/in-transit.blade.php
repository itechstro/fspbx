{{-- email-template
version: 1.2.1
language: th
category: fax
subcategory: in-transit
format: html
layout: standard
subject: ตอบกลับ: แฟกซ์ถึง {{ $fax_destination }}
description: การแจ้งเตือนว่าแฟกซ์ขาออกถูกรับเข้าระบบแล้ว
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>ไฟล์ของคุณกำลังถูกส่งเป็นแฟกซ์ไปยัง {{ $attributes['fax_destination'] }}</h1>
<p>เราจะแจ้งผลของการส่งให้คุณทราบเพิ่มเติม</p>

<p>หากมีคำถาม โปรด<a href="mailto:{{ $attributes["support_email"] ?? ''}}">ส่งอีเมลถึงทีมลูกค้าสัมพันธ์</a> (เราตอบกลับอย่างรวดเร็ว)</p>
<p>ขอบคุณ
  <br>ทีม {{ config('app.name', 'Laravel') }}</p>
<p><strong>หมายเหตุ:</strong> ต้องการความช่วยเหลือทันทีหรือไม่? ทีมสนับสนุน {{ config('app.name', 'Laravel') }} พร้อมช่วยเหลือ ดู<a href="{{ $attributes["help_url"] ?? ''}}">เอกสารช่วยเหลือ</a> หรือตอบกลับอีเมลนี้</p>
@endsection
