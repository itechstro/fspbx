{{-- email-template
version: 1.1.1
language: th
category: export
subcategory: completed
format: html
layout: standard
subject: {{ $email_subject }}
description: การแจ้งเตือนเมื่อส่งออกรายงานเสร็จสิ้น
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>รายงานของคุณพร้อมแล้ว</h1>

<p>ไฟล์ CSV ที่คุณขอพร้อมให้ดาวน์โหลดแล้ว</p>

<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td align="center">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td align="center">
    <table border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td>
        <a href="{{ $attributes['fileUrl'] }}" target="_blank" rel="noopener" style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; background-color: #4a90e2; border-radius: 5px; text-decoration: none;">
            ดาวน์โหลดรายงาน
        </a>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>

<p>ขอบคุณ!</p>
@endsection
