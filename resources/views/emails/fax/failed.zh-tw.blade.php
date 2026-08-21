{{-- email-template
version: 1.2.0
language: zh-tw
category: fax
subcategory: failed
format: html
layout: standard
subject: 回覆：傳真至 {{ $fax_destination }} 失敗
description: 外送傳真失敗通知
--}}
@extends('emails.email_layout')

@section('content')
<h1>傳真至 {{ $attributes['fax_destination'] }} 失敗</h1>
<p>{{ $attributes['email_message'] }}</p>
@endsection
