{{-- email-template
version: 1.2.0
language: zh-tw
category: fax
subcategory: invalid-destination
format: html
layout: standard
subject: 傳真至 {{ $invalid_number }} 失敗 - 傳真目的地號碼無效
description: 無效傳真目的地通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>傳真至 {{ $attributes['invalid_number'] }} 失敗</h1>
<p>目的地號碼不是有效的美國電話號碼。</p>
@endsection
