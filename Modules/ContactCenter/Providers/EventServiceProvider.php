<?php

namespace Modules\ContactCenter\Providers;

use App\Events\ExtensionDeleted;
use App\Events\ExtensionUpdated;
use Modules\ContactCenter\Listeners\DeleteAgentWhenExtensionIsDeleted;
use Modules\ContactCenter\Listeners\UpdateAgentWhenExtensionIsUpdated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ExtensionDeleted::class => [
            DeleteAgentWhenExtensionIsDeleted::class,
        ],
        ExtensionUpdated::class => [
            UpdateAgentWhenExtensionIsUpdated::class,
        ],
    ];
}