{{-- email-template
version: 1.2.1
language: th
category: archive
subcategory: storage-report
format: html
layout: standard
subject: {{ $email_subject }}
description: รายงานการย้ายไฟล์จัดเก็บถาวร
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>รายงานการจัดเก็บถาวรใหม่</h1>
<p>สคริปต์ย้ายไฟล์ทำงานสำเร็จ รายละเอียดดังนี้</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>เซิร์ฟเวอร์:</strong>
            {{ $attributes['hostname'] ?? 'unknown' }}
          </td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>สำเร็จ:</strong>
            @if (isset($attributes['success'])) {{ count($attributes['success'])}} @else 0 @endif
          </td>
        </tr>
        @if (isset($attributes['failed']) && count($attributes['failed']) > 0)
        <tr>
          <td class="attributes_item"><strong>ล้มเหลว:</strong>
              {{ count($attributes['failed'])}}
            </td>
        </tr>
        @endif
      </table>
    </td>
  </tr>
</table>

@if (isset($attributes['failed']) && count($attributes['failed']) > 0)

  @foreach ($attributes['failed'] as $rec)
    <li>{{ $rec['name'] }} -- สาเหตุ :: {{ $rec['msg'] }}</li>
  @endforeach

@endif
@endsection
