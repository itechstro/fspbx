<?php

namespace Modules\ContactCenter\Http\ViewComposers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class DashboardViewComposer
{
    public function compose(View $view) //: mixed
    {
        // // Override just the 'content' section
        // $view->getFactory()->startSection('content', view('custom-fields::items.create'));
        // $view->with('content', view('custom-fields::items.create'));

        // Prepare data
        $view_data = $view->getData();

        $view_data['permissions']['contact_center_settings_edit'] = userCheckPermission("contact_center_settings_edit");
        $view_data['permissions']['contact_center_dashboard_view'] = userCheckPermission("contact_center_dashboard_view");
        // dd($view_data);

        // $data['recurring_sub_total'] = 0;

        // Override the whole file
        // $view->setPath(view('custom-fields::components.documents.template.default')->getPath());
        // $view->with($data);
        // dd($view);
        // Push to a stack
        $view->getFactory()->startPush('dashboard_tiles_end', view('contactcenter::layouts.settings.dashboard-settings', $view_data));

        // Push scripts
        // $view->getFactory()->startPush('scripts_end', view('authorize-net::companies.show.scripts'));

        // return $view;
    }

   
}
