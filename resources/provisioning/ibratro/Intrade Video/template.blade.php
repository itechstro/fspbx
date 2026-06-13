{{-- version: 2.3.0 --}}

@switch($flavor)

@case('mac.cfg')
@include('provisioning.ibratro.mac-cfg', [
    'modelLabel' => 'InTrade Video',
    'modelProfile' => 'video',
    'maxLines' => 20,
    'videoEnabled' => true,
    'includeLineVideoCodec' => false,
    'keyLayout' => 'video',
    'funcKeyPages' => 4,
    'keysPerPage' => 29,
])
@break

@endswitch
