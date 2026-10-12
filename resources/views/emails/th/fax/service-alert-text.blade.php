{{-- email-template
format: text
layout: none
--}}
การแจ้งเตือนบริการแฟกซ์

@if(isset($attributes["pendingFaxes"]))
มีแฟกซ์ขาออก {{ $attributes["pendingFaxes"] }} รายการที่รอดำเนินการนานกว่า {{ $attributes["waitTimeThreshold"] }} นาที โปรดตรวจสอบสถานะบริการแฟกซ์
@endif

@if(isset($attributes["failedFaxes"]))
แฟกซ์ที่ประมวลผลล่าสุด {{ $attributes["totalChecked"] }} รายการ มี {{ $attributes["failedFaxes"] }} รายการที่ล้มเหลว (อัตราล้มเหลว {{ $attributes["failureRate"] }}%)
สิ่งนี้อาจบ่งชี้ปัญหาของบริการแฟกซ์
@endif

ขอบคุณ

ทีม {{ config('app.name', 'Laravel') }}
