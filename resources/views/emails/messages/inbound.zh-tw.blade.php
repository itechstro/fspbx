{{-- email-template
version: 1.2.0
language: zh-tw
category: messages
subcategory: inbound
format: html
layout: standard
subject: {{ $email_subject }}
description: 轉寄至電子郵件的來電訊
--}}
@extends('emails.email_layout')

@section('content')

<p><strong>寄件者：</strong> {{ $attributes['source'] ?? '—' }}</p>
<p><strong>收件者：</strong> {{ $attributes['destination'] ?? '—' }}</p>

@if(!empty($attributes['message']))
    <p>{{ $attributes['message'] }}</p>
@else
    <p><em>沒有文字內容。</em></p>
@endif

@if(!empty($attributes['inline_images']) && is_array($attributes['inline_images']))
    <p><strong>圖片：</strong></p>

    @foreach($attributes['inline_images'] as $image)
        <div style="margin: 0 0 16px 0;">
            <img
                src="cid:{{ $image['cid'] }}"
                alt="{{ $image['name'] }}"
                style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 6px;"
            >
            <div style="font-size: 12px; color: #666; margin-top: 6px;">
                {{ $image['name'] }}
            </div>
        </div>
    @endforeach
@endif

@if(!empty($attributes['media']) && is_array($attributes['media']))
    <p><strong>附件：</strong> {{ count($attributes['media']) }}</p>

    <ul>
        @foreach($attributes['media'] as $index => $item)
            <li>
                {{ $item['original_name'] ?? $item['stored_name'] ?? ('附件 ' . ($index + 1)) }}
                @if(!empty($item['mime_type']))
                    ({{ $item['mime_type'] }})
                @endif
            </li>
        @endforeach
    </ul>
@endif

@endsection
