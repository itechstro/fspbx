{{-- version: 2.4.0 --}}

@switch($flavor)

@case('mac.cfg')
@include('provisioning.intrade.mac-cfg', [
    'modelLabel' => 'Entry',
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
