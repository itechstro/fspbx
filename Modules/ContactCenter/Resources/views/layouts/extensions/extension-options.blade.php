@if ($permissions['add_user'])
    <div class="accordion custom-accordion" id="accordionCC{{ $extension->extension_uuid }}">
        <h6 class="dropdown-header" id="headingCC{{ $extension->extension_uuid }}">
            <a class="custom-accordion-title d-block" data-bs-toggle="collapse"
                href="#collapseCC{{ $extension->extension_uuid }}" aria-expanded="false"
                aria-controls="collapseCC{{ $extension->extension_uuid }}">
                Contact Center
                <i class="mdi mdi-chevron-down h4"></i>
            </a>
        </h6>

        <div id="collapseCC{{ $extension->extension_uuid }}" class="collapse"
            aria-labelledby="headingCC{{ $extension->extension_uuid }}"
            data-bs-parent="#accordionCC{{ $extension->extension_uuid }}">
            @if ($permissions['contact_center_agent_create'])
                <livewire:make-contact-center-user :extension="$extension" role="agent" />
            @endif
            @if ($permissions['contact_center_admin_create'])
                <livewire:make-contact-center-user :extension="$extension" role="admin" />
            @endif
            @if ($permissions['contact_center_supervisor_create'])
                <livewire:make-contact-center-user :extension="$extension" role="supervisor" />
            @endif
        </div>
    </div>
@endif
