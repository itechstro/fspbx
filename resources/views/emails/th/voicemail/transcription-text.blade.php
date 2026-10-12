{{-- email-template
format: text
layout: none
--}}
คุณมีข้อความเสียงใหม่:

จาก: {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}
ถึงกล่องเสียง: {{ $attributes['dialed_user'] }}
ได้รับเมื่อ: {{ $attributes['message_date'] }}
ความยาว: {{ $attributes['message_duration'] }}

ตัวอย่างวอยซ์เมล:
{{ $attributes['message_text'] }}

@if($attributes['voicemail_file_mode'] === 'attach')
ฟังวอยซ์เมลนี้จากโทรศัพท์ หรือเปิดไฟล์เสียงที่แนบมา คุณยังสามารถลงชื่อเข้าใช้บัญชีเพื่อจัดการและฟังวอยซ์เมลได้
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
ฟังวอยซ์เมลนี้จากโทรศัพท์ หรือดาวน์โหลดไฟล์บันทึกที่ {{ $attributes['voicemail_download_url'] }} คุณยังสามารถลงชื่อเข้าใช้บัญชีเพื่อจัดการและฟังวอยซ์เมลได้
@else
ฟังวอยซ์เมลนี้จากโทรศัพท์ คุณยังสามารถลงชื่อเข้าใช้บัญชีเพื่อจัดการและฟังวอยซ์เมลได้
@endif

หากมีคำถาม โปรดส่งอีเมลถึงทีมลูกค้าสัมพันธ์ (เราตอบกลับอย่างรวดเร็ว)
