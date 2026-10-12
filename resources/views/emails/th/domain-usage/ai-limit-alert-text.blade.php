{{-- email-template
format: text
layout: none
--}}
@if(!empty($data['is_test']))
นี่คือการแจ้งเตือนทดสอบ ตัวเลขการใช้งานด้านล่างเป็นข้อมูลตัวอย่างเท่านั้น

@endif
การแจ้งเตือนการใช้งาน AI สำหรับ {{ $data['domain_name'] ?? 'บัญชี' }}
ช่วงเวลา: {{ $data['period_label'] ?? $data['period'] ?? '' }}

{{ $data['badge_label'] }}

บริการ: {{ $data['limit_label'] ?? '' }}
การใช้งาน: {{ $data['usage_formatted'] }} / {{ $data['limit_formatted'] }} {{ $data['unit'] ?? '' }}
คงเหลือ: {{ $data['remaining_formatted'] }} {{ $data['unit'] ?? '' }}
ใช้ไปแล้ว: {{ $data['percent_used_formatted'] }}%

@if(!empty($data['is_reached']))
คำขอ AI ใหม่สำหรับบริการนี้อาจถูกบล็อกจนกว่าการใช้งานจะรีเซ็ตหรือมีการเพิ่มขีดจำกัด
@else
การใช้งานใกล้ถึงขีดจำกัดรายเดือนแล้ว โปรดพิจารณาเพิ่มขีดจำกัดของบัญชีหรือตรวจสอบกิจกรรม AI
@endif

ตรวจสอบการใช้งานได้ที่ใบอนุญาตบัญชีของบัญชีนี้
