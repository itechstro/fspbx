{{-- email-template
version: 1.1.1
language: th
category: voicemail
subcategory: default
format: html
layout: standard
subject: วอยซ์เมลจาก {{ $caller_id_name }} <{{ $caller_id_number }}> {{ $message_duration }}
description: การแจ้งเตือนวอยซ์เมลใหม่
--}}
@extends('emails.th.email_layout')

@section('content')
<p>คุณมีข้อความเสียงใหม่:</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="attributes_content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td class="attributes_item"><strong>จาก:</strong> {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}</td></tr>
                <tr><td class="attributes_item"><strong>ถึงกล่องเสียง:</strong> {{ $attributes['dialed_user'] }}</td></tr>
                <tr><td class="attributes_item"><strong>ได้รับเมื่อ:</strong> {{ $attributes['message_date'] }}</td></tr>
                <tr><td class="attributes_item"><strong>ความยาว:</strong> {{ $attributes['message_duration'] }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if($attributes['voicemail_file_mode'] === 'attach')
    <p>ฟังวอยซ์เมลนี้จากโทรศัพท์ หรือเปิดไฟล์เสียงที่แนบมา คุณยังสามารถลงชื่อเข้าใช้บัญชีเพื่อจัดการและฟังวอยซ์เมลได้</p>
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
    <p>ฟังวอยซ์เมลนี้จากโทรศัพท์ หรือใช้<a href="{{ $attributes['voicemail_download_url'] }}">ลิงก์ดาวน์โหลดที่ปลอดภัย</a> คุณยังสามารถลงชื่อเข้าใช้บัญชีเพื่อจัดการและฟังวอยซ์เมลได้</p>
@else
    <p>ฟังวอยซ์เมลนี้จากโทรศัพท์ คุณยังสามารถลงชื่อเข้าใช้บัญชีเพื่อจัดการและฟังวอยซ์เมลได้</p>
@endif
@endsection
