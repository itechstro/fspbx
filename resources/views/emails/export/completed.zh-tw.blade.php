{{-- email-template
version: 1.1.0
language: zh-tw
category: export
subcategory: completed
format: html
layout: standard
subject: {{ $email_subject }}
description: 報表匯出完成通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>您的報表已準備就緒。</h1>

<p>您要求的 CSV 檔案已可下載。</p>

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
            下載報表
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

<p>謝謝！</p>
@endsection
