{{-- email-template
version: 1.2.1
language: th
category: fax
subcategory: not-authorized
format: html
layout: standard
subject: อีเมลไม่ได้รับอนุญาต
description: การแจ้งเตือนผู้ส่งอีเมลเป็นแฟกซ์ที่ไม่ได้รับอนุญาต
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>แฟกซ์ของคุณถึง {{ $attributes['fax_destination'] }} ส่งไม่สำเร็จ</h1>
<p>อีเมลด้านล่างไม่ได้รับอนุญาตให้ส่งแฟกซ์ โปรดติดต่อผู้ดูแลระบบ</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>{{ $attributes['from'] }}</strong>
        </tr>
      </table>
    </td>
  </tr>
</table>
@endsection
