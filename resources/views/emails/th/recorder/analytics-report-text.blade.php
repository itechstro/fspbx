{{-- email-template
format: text
layout: none
--}}
รายงานวิเคราะห์การบันทึกเสียง
{{ $data['domain_name'] ?? 'บัญชี' }}
{{ $data['period_label'] ?? '' }}
สร้างเมื่อ {{ $data['generated_at'] ?? '' }}

จำนวนสายทั้งหมด: {{ $data['summary']['total_calls'] ?? 0 }}
ระยะเวลารวม: {{ $data['summary']['total_duration'] ?? '00:00:00' }}
ระยะเวลาเฉลี่ย: {{ $data['summary']['average_duration'] ?? '00:00:00' }}
ถอดเสียงแล้ว: {{ $data['summary']['transcribed_count'] ?? 0 }}
สรุปแล้ว: {{ $data['summary']['summarized_count'] ?? 0 }}

ความรู้สึก
เชิงบวก: {{ $data['summary']['sentiment']['positive'] ?? 0 }}
เป็นกลาง: {{ $data['summary']['sentiment']['neutral'] ?? 0 }}
เชิงลบ: {{ $data['summary']['sentiment']['negative'] ?? 0 }}
ไม่ทราบ: {{ $data['summary']['sentiment']['unknown'] ?? 0 }}

สายต่อวัน
@if(empty($data['calls_by_day']))
ไม่มีข้อมูลสายรายวันในช่วงเวลานี้
@else
@foreach($data['calls_by_day'] as $row)
{{ $row['date'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

สถานะการถอดเสียง
@if(empty($data['transcription_status_breakdown']))
ไม่มีข้อมูลการถอดเสียงในช่วงเวลานี้
@else
@foreach($data['transcription_status_breakdown'] as $row)
{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

สถานะสรุป
@if(empty($data['summary_status_breakdown']))
ไม่มีข้อมูลสรุปในช่วงเวลานี้
@else
@foreach($data['summary_status_breakdown'] as $row)
{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

หัวข้อยอดนิยม
@if(empty($data['top_topics']))
ไม่มีหัวข้อสรุปในช่วงเวลานี้
@else
@foreach($data['top_topics'] as $topic)
- {{ $topic['label'] ?? '' }} ({{ $topic['count'] ?? 0 }})
@endforeach
@endif

AI สรุปผู้บริหาร
@if(!empty($data['executive_summary']))
@if(!empty($data['executive_summary']['overview']))
{{ $data['executive_summary']['overview'] }}

@endif
@if(!empty($data['executive_summary']['highlights']))
ไฮไลต์:
@foreach($data['executive_summary']['highlights'] as $item)
- {{ $item }}
@endforeach

@endif
@if(!empty($data['executive_summary']['concerns']))
ประเด็นที่ต้องระวัง:
@foreach($data['executive_summary']['concerns'] as $item)
- {{ $item }}
@endforeach

@endif
@if(!empty($data['executive_summary']['recommendations']))
ข้อแนะนำ:
@foreach($data['executive_summary']['recommendations'] as $item)
- {{ $item }}
@endforeach

@endif
@elseif(!empty($data['executive_summary_error']))
ไม่ได้รวมสรุปผู้บริหาร: {{ $data['executive_summary_error'] }}

@endif

สายที่บันทึกไว้
@if(empty($data['template_calls']))
ไม่พบสายที่บันทึกในช่วงเวลานี้
@else
แสดงสูงสุด 25 สายด้านล่าง รายการทั้งหมดอยู่ในไฟล์ CSV ที่แนบมา

@foreach($data['template_calls'] as $call)
---
{{ $call['date'] ?? '' }} {{ $call['time'] ?? '' }}
ผู้โทร: {{ $call['caller'] ?? '—' }}
หมายเลขที่กด: {{ $call['dialed'] ?? '—' }}
ระยะเวลา: {{ $call['duration'] ?? '—' }}
ความรู้สึก: {{ $call['sentiment'] ?? '—' }}
สรุป: {{ $call['summary'] ?? '—' }}
@endforeach
@endif

เปิดการบันทึกเสียง: {{ $data['recorder_url'] ?? '' }}
