<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        $settings = Setting::where('organization_id', (int) $org->id)->pluck('value', 'key');

        return Inertia::render('Settings/Index', ['settings' => $settings]);
    }

    public function save(Request $request): RedirectResponse
    {
        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        $payload = $request->validate([
            'slack_webhook_url' => 'nullable|string',
            'smtp_host' => 'nullable|string',
            'smtp_user' => 'nullable|string',
            'smtp_pass' => 'nullable|string',
            'google_drive_key' => 'nullable|string',
        ]);

        foreach ($payload as $k => $v) {
            Setting::put((int) $org->id, $k, $v);
        }

        return back()->with('success', 'Settings saved');
    }
}
