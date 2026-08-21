{{-- email-template
version: 1.2.0
language: zh-tw
category: archive
subcategory: storage-report
format: html
layout: standard
subject: {{ $email_subject }}
description: 封存儲存卸載報告
--}}
@extends('emails.email_layout')

@section('content')
<h1>新的封存儲存報告</h1>
<p>卸載指令已成功執行。報告如下。</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>伺服器：</strong>
            {{ $attributes['hostname'] ?? 'unknown' }}
          </td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>成功：</strong>
            @if (isset($attributes['success'])) {{ count($attributes['success'])}} @else 0 @endif
          </td>
        </tr>
        @if (isset($attributes['failed']) && count($attributes['failed']) > 0)
        <tr>
          <td class="attributes_item"><strong>失敗：</strong>
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
    <li>{{ $rec['name'] }} -- 原因 :: {{ $rec['msg'] }}</li>
  @endforeach

@endif
@endsection
