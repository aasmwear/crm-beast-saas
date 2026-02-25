<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    /**
     * Display the organization settings page.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('settings.view'), 403);

        /** @var Organization $organization */
        $organization = $request->route('organization');
        $orgId = (int) $organization->id;

        $workHours = Setting::get($orgId, 'work_hours', [
            'work_week' => 'Mon-Fri',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $notificationsDefaults = Setting::get($orgId, 'notifications.defaults', [
            'channels' => ['inapp' => true, 'email' => false],
            'types' => [],
        ]);
        $locale = Setting::get($orgId, 'locale', config('app.locale', 'en'));
        $currency = Setting::get($orgId, 'currency', 'USD');

        return Inertia::render('Settings/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'logo_path' => $organization->logo_path,
                'timezone' => $organization->timezone ?? 'UTC',
                'week_start' => $organization->week_start ?? 'Monday',
            ],
            'settings' => [
                'locale' => $locale,
                'currency' => $currency,
                'work_hours' => $workHours,
                'notifications_defaults' => $notificationsDefaults,
                'slack_webhook_url' => Setting::get($orgId, 'slack_webhook_url'),
                'smtp_host' => Setting::get($orgId, 'smtp_host'),
                'smtp_port' => Setting::get($orgId, 'smtp_port'),
                'smtp_user' => Setting::get($orgId, 'smtp_user'),
                'smtp_from' => Setting::get($orgId, 'smtp_from'),
            ],
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::ALL),
            'locales' => ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German'],
            'currencies' => ['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP'],
        ]);
    }

    /**
     * Update organization settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        /** @var Organization $organization */
        $organization = $request->route('organization');
        $orgId = (int) $organization->id;
        $validated = $request->validated();

        // Organization table
        $organization->name = $validated['name'];
        $organization->timezone = $validated['timezone'];
        $organization->week_start = $validated['week_start'];

        if ($request->hasFile('logo')) {
            $dir = 'logos';
            if (! Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }

            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }

            $path = $request->file('logo')->store($dir, 'public');
            $organization->logo_path = $path;
        }

        $organization->save();

        // Settings table
        if (array_key_exists('locale', $validated)) {
            Setting::put($orgId, 'locale', $validated['locale'] ?? config('app.locale'));
        }
        if (array_key_exists('currency', $validated)) {
            Setting::put($orgId, 'currency', $validated['currency'] ?? 'USD');
        }
        if (! empty($validated['work_hours'])) {
            Setting::put($orgId, 'work_hours', $validated['work_hours']);
        }
        if (array_key_exists('notifications_defaults', $validated) && is_array($validated['notifications_defaults'])) {
            Setting::put($orgId, 'notifications.defaults', $validated['notifications_defaults']);
        }
        $integrationKeys = ['slack_webhook_url', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_from'];
        foreach ($integrationKeys as $key) {
            if (array_key_exists($key, $validated)) {
                Setting::put($orgId, $key, $validated[$key]);
            }
        }
        if (! empty($validated['smtp_pass'] ?? null)) {
            Setting::put($orgId, 'smtp_pass', $validated['smtp_pass']);
        }

        return redirect()
            ->route('settings.index', ['organization' => $organization->slug])
            ->with('success', 'Settings updated');
    }
}
