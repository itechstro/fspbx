{{-- version: 2.1.0 --}}

@switch($flavor)

@case('mac.cfg')
@include('provisioning.ibratro.mac-cfg', [
    'modelLabel' => 'InTrade Video',
    'modelProfile' => 'video',
    'maxLines' => 20,
    'videoEnabled' => true,
    'includeLineVideoCodec' => false,
    'keyLayout' => 'advanced',
    'funcKeyPages' => 4,
    'keysPerPage' => 29,
])
@break

@endswitch
