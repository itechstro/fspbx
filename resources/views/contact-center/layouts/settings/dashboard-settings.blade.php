@if ($permissions['contact_center_dashboard_view'] ?? false)
<div class="col-md-4 col-lg-3 col-sm-6">
    <div class="card">
        <a href="/contact-center">
            <div class="card-body pt-2">
                <div class="d-flex justify-content-between align-items-center"
                    style="border-bottom: 1px solid #f1f3fa;">
                    <h6 class="m-0" style="color: #6c757d;font-size:20px">Contact Center Dashboard</h6>
                    <i class="mdi mdi-face-agent text-info" style="font-size: 2rem;"></i>
                </div>
                <h2 class="my-2 text-info" style="opacity: 0;">S</h2>
                <p class="mb-0 text-muted">
                    <span class="text-nowrap">Click here to view your dashboard</span>
                </p>
            </div> <!-- end card-body-->
        </a>
    </div>
</div>
@endif

@if ($permissions['contact_center_settings_edit'] ?? false)
<div class="col-md-4 col-lg-3 col-sm-6">
    <div class="card">
        <a href="/contact-center/settings">
            <div class="card-body pt-2">
                <div class="d-flex justify-content-between align-items-center"
                    style="border-bottom: 1px solid #f1f3fa;">
                    <h6 class="m-0" style="color: #6c757d;font-size:20px">Contact Center Settings</h6>
                    <i class="mdi mdi-face-agent text-info" style="font-size: 2rem;"></i>
                </div>
                <h2 class="my-2 text-info" style="opacity: 0;">S</h2>
                <p class="mb-0 text-muted">
                    <span class="text-nowrap">Click here to modify your settings</span>
                </p>
            </div> <!-- end card-body-->
        </a>
    </div>
</div>
@endif