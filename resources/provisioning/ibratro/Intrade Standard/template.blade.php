{{-- version: 2.1.1 --}}

@switch($flavor)

@case('mac.cfg')
@include('provisioning.ibratro.mac-cfg', [
    'modelLabel' => 'InTrade Standard',
    'modelProfile' => 'standard',
    'maxLines' => 12,
    'videoEnabled' => false,
    'includeLineVideoCodec' => false,
    'keyLayout' => 'standard',
    'funcKeyPages' => 5,
    'keysPerPage' => 8,
    'sideKeyPages' => 3,
])
@break

@endswitch
