{{-- email-template
version: 1.3.1
language: th
category: extension
subcategory: welcome
format: html
layout: standard
subject: หมายเลขภายใน {{ $attributes['extension'] }} ของคุณพร้อมใช้งานแล้ว
description: ข้อมูลต้อนรับหมายเลขภายในและวอยซ์เมล
--}}
@extends('emails.th.email_layout')

@section('content')
<h1>ยินดีต้อนรับ คุณ{{ $attributes['recipient_name'] }}!</h1>

<p>เราได้ตั้งค่าหมายเลขภายใน <strong>{{ $attributes['extension'] }}</strong> ให้คุณแล้ว โปรดเก็บอีเมลนี้ไว้สำหรับข้อมูลโทรศัพท์และวอยซ์เมล</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>หมายเลขภายใน:</strong> {{ $attributes['extension'] }}</td>
        </tr>
        @if (!empty($attributes['direct_numbers']))
          <tr>
            <td class="attributes_item"><strong>หมายเลขตรง:</strong> {{ implode(', ', $attributes['direct_numbers']) }}</td>
          </tr>
        @endif
        <tr>
          <td class="attributes_item"><strong>กล่องวอยซ์เมล:</strong> {{ $attributes['voicemail_id'] }}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>PIN วอยซ์เมล:</strong> {{ $attributes['voicemail_pin'] }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<h2>ตั้งค่าข้อความทักทายวอยซ์เมล</h2>
<ol>
  <li>กด <strong>*97</strong> จากโทรศัพท์ของคุณ</li>
  <li>ใส่ PIN วอยซ์เมล แล้วกด <strong>#</strong></li>
  <li>กด <strong>5</strong> สำหรับตัวเลือกกล่องเสียง</li>
  <li>กด <strong>1</strong> เพื่อบันทึกข้อความทักทายเมื่อไม่สามารถรับสายได้</li>
</ol>

@if (!empty($attributes['help_url']))
  <p>ดูความช่วยเหลือเพิ่มเติมได้ที่ <a href="{{ $attributes['help_url'] }}">ศูนย์ช่วยเหลือ</a></p>
@endif

@if (!empty($attributes['support_email']))
  <p>มีคำถามหรือไม่? ส่งอีเมลถึง <a href="mailto:{{ $attributes['support_email'] }}">{{ $attributes['support_email'] }}</a></p>
@endif

<p>ยินดีต้อนรับสู่ระบบ<br>{{ $attributes['app_name'] }}</p>
@endsection
