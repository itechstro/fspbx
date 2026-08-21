{{-- email-template
version: 1.1.0
language: zh-tw
category: app
subcategory: credentials
format: html
layout: standard
subject: {{ $email_subject }}
description: 行動與桌面應用程式憑證
--}}
@extends('emails.email_layout')

@section('content')
<p>歡迎使用 {{ config('app.name', 'Laravel') }} 應用程式。請保留此郵件以供日後參考。以下是開始使用應用程式的簡單步驟：</p>
<p>1. 為您的裝置下載應用程式：</p>
<table class="body-action" align="center" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td align="center">
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
                  <a href="{{ $attributes['google_play_link'] ?? '' }}">
                    <img class="max-width" border="0" style="display:block; color:#000000; text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto
                      !important;" width="189" alt="下載 Android 版" data-proportionally-constrained="true" data-responsive="true"
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
                    text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto !important;" width="174" alt="下載 iOS 版" data-proportionally-constrained="true" data-responsive="true"
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
                  ><span style="color:#ffffff;">取得 Windows 版</span></a>
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
                  ><span style="color:#ffffff;">下載 Mac 版</span></a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<p>2. 安裝並啟動應用程式後，請輸入下列憑證或掃描 QR 碼：</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>顯示名稱：</strong> {{ $attributes['name'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>PBX 分機：</strong> {{ $attributes['extension'] ?? ''}}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<p>請使用這些憑證登入：</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>網域：</strong> {{ $attributes['domain'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>使用者名稱：</strong> {{ $attributes['login_username'] ?? $attributes['username'] ?? ''}}</td>
        </tr>
        @if(!empty($attributes['password_url']))
          <tr>
            <td class="attributes_item"><strong>密碼：</strong> <a href="{{ $attributes['password_url'] }}">取得密碼</a></td>
          </tr>
        @elseif(!empty($attributes['password']))
          <tr>
            <td class="attributes_item"><strong>密碼：</strong> {{ $attributes['password'] }}</td>
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
          alt="行動應用程式憑證 QR 碼"
          width="180"
          style="display:block; width:180px; height:auto; margin:0 auto;"
        >
      </td>
    </tr>
  @endif
</table>

<p>3. 登入後即可開始與組織內的使用者通訊。您可以透過分機撥打與接聽電話、保留通話、轉接來電等。</p>

<p>如有問題，請<a href="mailto:{{ $attributes["support_email"] ?? ''}}">寄信給我們的客戶成功團隊</a>。</p>
<p>謝謝，
  <br>{{ config('app.name', 'Laravel') }} 團隊</p>
@endsection
