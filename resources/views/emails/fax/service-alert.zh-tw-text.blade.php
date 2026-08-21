{{-- email-template
format: text
layout: none
--}}
傳真服務警示

@if(isset($attributes["pendingFaxes"]))
有 {{ $attributes["pendingFaxes"] }} 封外送傳真已等待超過 {{ $attributes["waitTimeThreshold"] }} 分鐘。請檢查傳真服務狀態。
@endif

@if(isset($attributes["failedFaxes"]))
最近處理的 {{ $attributes["totalChecked"] }} 封傳真中，有 {{ $attributes["failedFaxes"] }} 封失敗（失敗率 {{ $attributes["failureRate"] }}%）。
這表示傳真服務可能發生問題。
@endif

謝謝，

{{ config('app.name', 'Laravel') }} 團隊
