{{-- email-template
format: text
layout: none
--}}
สวัสดี

นี่คืออีเมลทดสอบจาก {{ config('app.name', 'FS PBX') }}

หากคุณได้รับข้อความนี้ แสดงว่าบริการอีเมลที่ตั้งค่าไว้สามารถส่งอีเมลได้

ส่งเมื่อ {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}
