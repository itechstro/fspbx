{{-- email-template
format: text
layout: none
--}}
ไฟล์ของคุณกำลังถูกส่งเป็นแฟกซ์ไปยัง {{ $attributes['fax_destination'] }}

เราจะแจ้งผลของการส่งให้คุณทราบเพิ่มเติม

หากมีคำถาม โปรดส่งอีเมลถึงทีมลูกค้าสัมพันธ์ที่ {{ $attributes['support_email'] ?? '' }}

ขอบคุณ
ทีม {{ config('app.name', 'Laravel') }}

หมายเหตุ: ต้องการความช่วยเหลือทันทีหรือไม่? ไปที่ {{ $attributes['help_url'] ?? '' }} หรือตอบกลับอีเมลนี้
