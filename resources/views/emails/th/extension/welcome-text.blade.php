{{-- email-template
format: text
layout: none
--}}
ยินดีต้อนรับ คุณ{{ $attributes['recipient_name'] }}!

เราได้ตั้งค่าหมายเลขภายใน {{ $attributes['extension'] }} ให้คุณแล้ว โปรดเก็บอีเมลนี้ไว้สำหรับข้อมูลโทรศัพท์และวอยซ์เมล

หมายเลขภายใน: {{ $attributes['extension'] }}
@if (!empty($attributes['direct_numbers']))
หมายเลขตรง: {{ implode(', ', $attributes['direct_numbers']) }}
@endif
กล่องวอยซ์เมล: {{ $attributes['voicemail_id'] }}
PIN วอยซ์เมล: {{ $attributes['voicemail_pin'] }}

ตั้งค่าข้อความทักทายวอยซ์เมล

1. กด *97 จากโทรศัพท์ของคุณ
2. ใส่ PIN วอยซ์เมล แล้วกด #
3. กด 5 สำหรับตัวเลือกกล่องเสียง
4. กด 1 เพื่อบันทึกข้อความทักทายเมื่อไม่สามารถรับสายได้

@if (!empty($attributes['help_url']))
ความช่วยเหลือ: {{ $attributes['help_url'] }}
@endif
@if (!empty($attributes['support_email']))
มีคำถามหรือไม่? ส่งอีเมลถึง {{ $attributes['support_email'] }}
@endif

ยินดีต้อนรับสู่ระบบ
{{ $attributes['app_name'] }}
