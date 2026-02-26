<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

final class SettingsController extends Controller
{
    private const MASKED_PLACEHOLDER = '••••••••';

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

        $slackWebhookUrl = Setting::isSecretSet($orgId, 'slack_webhook_url')
            ? self::MASKED_PLACEHOLDER
            : null;
        $smtpPassSet = Setting::isSecretSet($orgId, 'smtp_pass');

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
                'slack_webhook_url' => $slackWebhookUrl,
                'slack_webhook_connected' => $slackWebhookUrl !== null,
                'smtp_host' => Setting::get($orgId, 'smtp_host'),
                'smtp_port' => Setting::get($orgId, 'smtp_port'),
                'smtp_user' => Setting::get($orgId, 'smtp_user'),
                'smtp_from' => Setting::get($orgId, 'smtp_from'),
                'smtp_pass_set' => $smtpPassSet,
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

        $changedKeys = [];

        // Organization table
        if (($validated['name'] ?? null) !== $organization->name) {
            $changedKeys[] = 'name';
        }
        if (($validated['timezone'] ?? null) !== $organization->timezone) {
            $changedKeys[] = 'timezone';
        }
        if (($validated['week_start'] ?? null) !== $organization->week_start) {
            $changedKeys[] = 'week_start';
        }

        $organization->name = $validated['name'];
        $organization->timezone = $validated['timezone'];
        $organization->week_start = $validated['week_start'];

        if ($request->hasFile('logo')) {
            $changedKeys[] = 'logo';
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
        $settingKeys = [
            'locale' => fn () => $validated['locale'] ?? config('app.locale'),
            'currency' => fn () => $validated['currency'] ?? 'USD',
        ];
        foreach ($settingKeys as $key => $resolver) {
            if (array_key_exists($key, $validated)) {
                $newVal = $resolver();
                $oldVal = Setting::get($orgId, $key);
                if ($newVal !== $oldVal) {
                    $changedKeys[] = $key;
                }
                Setting::put($orgId, $key, $newVal);
            }
        }
        if (! empty($validated['work_hours'])) {
            $changedKeys[] = 'work_hours';
            Setting::put($orgId, 'work_hours', $validated['work_hours']);
        }
        if (array_key_exists('notifications_defaults', $validated) && is_array($validated['notifications_defaults'])) {
            $changedKeys[] = 'notifications_defaults';
            Setting::put($orgId, 'notifications.defaults', $validated['notifications_defaults']);
        }

        $integrationKeys = ['slack_webhook_url', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_from'];
        foreach ($integrationKeys as $key) {
            if (array_key_exists($key, $validated)) {
                if ($key === 'slack_webhook_url' && $validated[$key] !== '') {
                    Setting::putEncrypted($orgId, $key, $validated[$key]);
                    $changedKeys[] = $key;
                } elseif ($key === 'slack_webhook_url' && ($validated[$key] ?? '') === '') {
                    Setting::put($orgId, $key, null);
                    $changedKeys[] = $key;
                } else {
                    Setting::put($orgId, $key, $validated[$key]);
                    $changedKeys[] = $key;
                }
            }
        }
        if (! empty($validated['smtp_pass'] ?? null)) {
            Setting::putEncrypted($orgId, 'smtp_pass', $validated['smtp_pass']);
            $changedKeys[] = 'smtp_pass';
        }

        if ($changedKeys !== []) {
            AuditLogger::log(
                $organization,
                $request->user(),
                'updated',
                'settings',
                $orgId,
                ['keys' => $changedKeys]
            );
        }

        return redirect()
            ->route('settings.index', ['organization' => $organization->slug])
            ->with('success', 'Settings updated');
    }

    /**
     * Test Slack webhook. Gated by settings.update.
     */
    public function testSlack(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.update'), 403);

        /** @var Organization $organization */
        $organization = $request->route('organization');
        $orgId = (int) $organization->id;

        $url = Setting::getDecrypted($orgId, 'slack_webhook_url');
        if (! $url) {
            return response()->json(['success' => false, 'message' => 'Slack webhook URL is not configured.'], 400);
        }

        try {
            $response = Http::timeout(10)->post($url, [
                'text' => 'CRM Beast: Slack webhook test from organization **' . $organization->name . '**.',
            ]);

            if ($response->successful()) {
                return response()->json(['success' => true, 'message' => 'Slack webhook test sent.']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Slack returned status ' . $response->status() . '.',
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Request failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test SMTP connection. Gated by settings.update.
     */
    public function testSmtp(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.update'), 403);

        /** @var Organization $organization */
        $organization = $request->route('organization');
        $orgId = (int) $organization->id;

        $host = Setting::get($orgId, 'smtp_host');
        $port = (int) (Setting::get($orgId, 'smtp_port') ?? 587);
        $user = Setting::get($orgId, 'smtp_user');
        $pass = Setting::getDecrypted($orgId, 'smtp_pass');
        $from = Setting::get($orgId, 'smtp_from') ?? $user ?? 'noreply@example.com';

        if (! $host) {
            return response()->json(['success' => false, 'message' => 'SMTP host is not configured.'], 400);
        }

        try {
            $tls = in_array($port, [465, 587], true);
            $transport = new EsmtpTransport($host, $port, $tls);
            $transport->setUsername($user ?? '');
            $transport->setPassword($pass ?? '');
            $transport->start();

            return response()->json(['success' => true, 'message' => 'SMTP connection successful.']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'SMTP test failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
