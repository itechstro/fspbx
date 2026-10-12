{{-- email-template
format: text
layout: none
--}}
ยินดีต้อนรับสู่แอป {{ config('app.name', 'Laravel') }} ของคุณ โปรดเก็บอีเมลนี้ไว้สำหรับใช้อ้างอิง ขั้นตอนง่ายๆ ในการเริ่มใช้แอปมีดังนี้:

ดาวน์โหลดแอปสำหรับอุปกรณ์ของคุณ:

Google Play: {{ $attributes['google_play_link'] ?? '' }}
Apple Store: {{ $attributes['apple_store_link'] ?? '' }}
รับเวอร์ชัน Windows ({{ $attributes['windows_link'] ?? '' }})
ดาวน์โหลดสำหรับ Mac ({{ $attributes['mac_link'] ?? '' }})

ชื่อที่แสดง: {{ $attributes['name'] ?? ''}}
หมายเลขภายใน PBX: {{ $attributes['extension'] ?? ''}}

ใช้ข้อมูลเหล่านี้เพื่อเข้าสู่ระบบ:

โดเมน: {{ $attributes['domain'] ?? ''}}
ชื่อผู้ใช้: {{ $attributes['login_username'] ?? $attributes['username'] ?? ''}}
@if(!empty($attributes['password_url']))
รหัสผ่าน: {{ $attributes['password_url'] }}
@elseif(!empty($attributes['password']))
รหัสผ่าน: {{ $attributes['password'] }}
@endif

@if(!empty($attributes['qrCodeUrl']))
สแกน QR โค้ดในเวอร์ชัน HTML ของอีเมลนี้เพื่อเข้าสู่ระบบด้วยแอป CloudPLAY
@endif

เมื่อเข้าสู่ระบบแล้ว คุณสามารถเริ่มติดต่อกับผู้ใช้ในองค์กรได้ คุณสามารถโทรออกและรับสายผ่านหมายเลขภายใน รอสาย โอนสาย และอื่นๆ

หากมีคำถาม โปรดส่งอีเมลถึงทีมลูกค้าสัมพันธ์ที่ {{ $attributes['support_email'] ?? '' }}

ขอบคุณ
ทีม {{ config('app.name', 'Laravel') }}
