{{-- email-template
version: 1.1.1
language: th
category: app
subcategory: credentials
format: html
layout: standard
subject: {{ $email_subject }}
description: ข้อมูลเข้าสู่ระบบแอปมือถือและเดสก์ท็อป
--}}
@extends('emails.th.email_layout')

@section('content')
<!-- Start Content-->

<p>ยินดีต้อนรับสู่แอป {{ config('app.name', 'Laravel') }} ของคุณ โปรดเก็บอีเมลนี้ไว้สำหรับใช้อ้างอิง ขั้นตอนง่ายๆ ในการเริ่มใช้แอปมีดังนี้:</p>
<p>1. ดาวน์โหลดแอปสำหรับอุปกรณ์ของคุณ:</p>
<!-- Action -->
<table class="body-action" align="center" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td align="center">
      <!-- Border based button https://litmus.com/blog/a-guide-to-bulletproof-buttons-in-email-design -->
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>

                  <a href="{{ $attributes['google_play_link'] ?? '' }}">
                    <img class="max-width" border="0" style="display:block; color:#000000; text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto
                      !important;" width="189" alt="ดาวน์โหลดสำหรับ Android" data-proportionally-constrained="true" data-responsive="true"
                      src="https://cdn.mcauto-images-production.sendgrid.net/b9e58e76174a4c84/88af7fc9-c74b-43ec-a1e2-a712cd1d3052/646x250.png">
                  </a>


                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
    <td>
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
                  <a href="{{ $attributes['apple_store_link'] ?? '' }}"><img class="max-width" border="0" style="display:block; color:#000000;
                    text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto !important;" width="174" alt="ดาวน์โหลดสำหรับ iOS" data-proportionally-constrained="true" data-responsive="true"
                    src="https://cdn.mcauto-images-production.sendgrid.net/b9e58e76174a4c84/bb2daef8-a40d-4eed-8fb4-b4407453fc94/320x95.png">
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

    </td>
  </tr>
  <tr>
    <td align="center">
      <!-- Border based button https://litmus.com/blog/a-guide-to-bulletproof-buttons-in-email-design -->
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
                  <a
                    href="{{ $attributes['windows_link'] ?? '' }}"
                    target="_blank"
                    style="background-color:#3869D4; border-top:10px solid #3869D4; border-right:18px solid #3869D4; border-bottom:10px solid #3869D4; border-left:18px solid #3869D4; border-radius:3px; color:#ffffff !important; display:inline-block; font-family:Helvetica, Arial, sans-serif; font-size:16px; font-weight:700; line-height:20px; text-align:center; text-decoration:none; -webkit-text-size-adjust:none;"
                  ><span style="color:#ffffff;">รับเวอร์ชัน Windows</span></a>


                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
    <td>
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
                  <a
                    href="{{ $attributes['mac_link'] ?? '' }}"
                    target="_blank"
                    style="background-color:#3869D4; border-top:10px solid #3869D4; border-right:18px solid #3869D4; border-bottom:10px solid #3869D4; border-left:18px solid #3869D4; border-radius:3px; color:#ffffff !important; display:inline-block; font-family:Helvetica, Arial, sans-serif; font-size:16px; font-weight:700; line-height:20px; text-align:center; text-decoration:none; -webkit-text-size-adjust:none;"
                  ><span style="color:#ffffff;">ดาวน์โหลดสำหรับ Mac</span></a>

                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>


<p>2. เมื่อติดตั้งและเปิดแอปแล้ว ให้ใส่ข้อมูลด้านล่างหรือสแกน QR โค้ด:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>ชื่อที่แสดง:</strong> {{ $attributes['name'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>หมายเลขภายใน PBX:</strong> {{ $attributes['extension'] ?? ''}}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<p>ใช้ข้อมูลเหล่านี้เพื่อเข้าสู่ระบบ:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>โดเมน:</strong> {{ $attributes['domain'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>ชื่อผู้ใช้:</strong> {{ $attributes['login_username'] ?? $attributes['username'] ?? ''}}</td>
        </tr>
        @if(!empty($attributes['password_url']))
          <tr>
            <td class="attributes_item"><strong>รหัสผ่าน:</strong> <a href="{{ $attributes['password_url'] }}">รับรหัสผ่าน</a></td>
          </tr>
        @elseif(!empty($attributes['password']))
          <tr>
            <td class="attributes_item"><strong>รหัสผ่าน:</strong> {{ $attributes['password'] }}</td>
          </tr>
        @endif
      </table>
    </td>
  </tr>
  @if(!empty($attributes['qrCodeUrl']))
    <tr>
      <td class="attributes_content" align="center" style="padding-top: 0;">
        <img
          src="{{ $attributes['qrCodeUrl'] }}"
          alt="QR โค้ดข้อมูลเข้าสู่ระบบแอปมือถือ"
          width="180"
          style="display:block; width:180px; height:auto; margin:0 auto;"
        >
      </td>
    </tr>
  @endif
</table>

<p>3. เมื่อเข้าสู่ระบบแล้ว คุณสามารถเริ่มติดต่อกับผู้ใช้ในองค์กรได้ คุณสามารถโทรออกและรับสายผ่านหมายเลขภายใน รอสาย โอนสาย และอื่นๆ</p>

<p>หากมีคำถาม โปรด<a href="mailto:{{ $attributes["support_email"] ?? ''}}">ส่งอีเมลถึงทีมลูกค้าสัมพันธ์</a></p>
<p>ขอบคุณ
  <br>ทีม {{ config('app.name', 'Laravel') }}</p>

@endsection
