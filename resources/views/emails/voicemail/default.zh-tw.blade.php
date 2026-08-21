{{-- email-template
version: 1.1.0
language: zh-tw
category: voicemail
subcategory: default
format: html
layout: standard
subject: 來自 {{ $caller_id_name }} <{{ $caller_id_number }}> 的語音留言 {{ $message_duration }}
description: 新語音留言通知
--}}
@extends('emails.email_layout')

@section('content')
<p>您有一則新的語音留言：</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="attributes_content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td class="attributes_item"><strong>來電者：</strong> {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}</td></tr>
                <tr><td class="attributes_item"><strong>收件信箱：</strong> {{ $attributes['dialed_user'] }}</td></tr>
                <tr><td class="attributes_item"><strong>收到時間：</strong> {{ $attributes['message_date'] }}</td></tr>
                <tr><td class="attributes_item"><strong>長度：</strong> {{ $attributes['message_duration'] }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if($attributes['voicemail_file_mode'] === 'attach')
    <p>您可以從話機收聽此語音留言，或開啟附加的音訊檔。也可以登入帳戶管理並收聽語音信箱。</p>
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
    <p>您可以從話機收聽此語音留言，或使用<a href="{{ $attributes['voicemail_download_url'] }}">安全下載連結</a>。也可以登入帳戶管理並收聽語音信箱。</p>
@else
    <p>您可以從話機收聽此語音留言。也可以登入帳戶管理並收聽語音信箱。</p>
@endif
@endsection
