{{-- email-template
version: 1.2.0
language: zh-tw
category: fax
subcategory: not-authorized
format: html
layout: standard
subject: 電子郵件未獲授權
description: 未授權的電子郵件轉傳真寄件者通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>傳真至 {{ $attributes['fax_destination'] }} 失敗</h1>
<p>下列電子郵件未獲授權傳送傳真。請聯絡系統管理員。</p>

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
