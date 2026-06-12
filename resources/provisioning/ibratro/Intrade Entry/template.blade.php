{{-- version: 2.1.0 --}}

@switch($flavor)

@case('mac.cfg')
@include('provisioning.ibratro.mac-cfg', [
    'modelLabel' => 'InTrade Entry',
    'modelProfile' => 'entry',
    'maxLines' => 6,
    'videoEnabled' => false,
    'includeLineVideoCodec' => false,
    'keyLayout' => 'entry',
    'funcKeyPages' => 1,
    'keysPerPage' => 8,
])
@break

@endswitch
