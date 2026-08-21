{{-- email-template
format: text
layout: none
--}}
新的封存儲存報告

卸載指令已完成。

伺服器：{{ $attributes['hostname'] ?? 'unknown' }}
成功：{{ isset($attributes['success']) ? count($attributes['success']) : 0 }}
失敗：{{ isset($attributes['failed']) ? count($attributes['failed']) : 0 }}

@if(isset($attributes['failed']) && count($attributes['failed']) > 0)
失敗紀錄：
@foreach($attributes['failed'] as $record)
- {{ $record['name'] }} — {{ $record['msg'] }}
@endforeach
@endif
