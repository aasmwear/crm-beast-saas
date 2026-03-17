<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\Platform\OrganizationFeature;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Support\FeatureCatalog;
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

        $of = OrganizationFeature::query()->where('organization_id', $orgId)->first();
        $features = $of !== null ? $of->features : OrganizationFeature::DEFAULT_FEATURES;
        $featureCatalog = FeatureCatalog::all();

        $canViewApiKeys = $request->user()?->can('api_keys.view') ?? false;
        $apiKeys = [];
        if ($canViewApiKeys) {
            $apiKeys = OrganizationApiKey::query()
                ->where('organization_id', $orgId)
                ->with('createdBy:id,name')
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'prefix', 'created_at', 'created_by_user_id', 'last_used_at', 'revoked_at'])
                ->map(fn ($k) => [
                    'id' => $k->id,
                    'name' => $k->name,
                    'prefix' => $k->prefix,
                    'created_at' => $k->created_at->toIso8601String(),
                    'created_by' => $k->createdBy?->name,
                    'last_used_at' => $k->last_used_at?->toIso8601String(),
                    'revoked_at' => $k->revoked_at?->toIso8601String(),
                ])
                ->values()
                ->toArray();
        }

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
            'features' => $features,
            'featureCatalog' => $featureCatalog,
            'apiKeys' => $apiKeys,
            'canViewApiKeys' => $canViewApiKeys,
            'canViewCustomFields' => $request->user()?->can('custom-fields.manage') ?? false,
        ]);
    }

    /**
     * Update organization feature flags. Gated by settings.update.
     */
    public function updateFeatures(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.update'), 403);

        /** @var Organization $organization */
        $organization = $request->route('organization');
        $orgId = (int) $organization->id;

        $payload = $request->validate([
            'features' => ['required', 'array'],
            'features.*' => ['nullable'],
        ]);

        $of = OrganizationFeature::query()->where('organization_id', $orgId)->first();
        if ($of === null) {
            $of = OrganizationFeature::query()->create([
                'organization_id' => $orgId,
                'features' => OrganizationFeature::DEFAULT_FEATURES,
                'subscription_status' => 'active',
            ]);
        }

        $current = $of->features;
        $changedKeys = [];
        $allowed = array_keys(FeatureCatalog::keys());

        foreach ($payload['features'] as $key => $value) {
            if (! FeatureCatalog::isValidKey($key)) {
                continue;
            }

            $type = FeatureCatalog::getType($key);
            if ($type === FeatureCatalog::TYPE_BOOLEAN) {
                $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif ($type === FeatureCatalog::TYPE_NUMBER) {
                $normalized = is_numeric($value) ? (int) $value : ($current[$key] ?? FeatureCatalog::defaults()[$key] ?? 0);
            } else {
                continue;
            }

            if (($current[$key] ?? null) !== $normalized) {
                $changedKeys[] = "features.{$key}";
            }
            $current[$key] = $normalized;
        }

        $of->features = $current;
        $of->save();

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

        return response()->json(['success' => true, 'message' => 'Feature flags updated.']);
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
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            $val = $validated[$key];
            if ($key === 'slack_webhook_url') {
                $isEmpty = $val === null || $val === '';
                if (! $isEmpty) {
                    Setting::putEncrypted($orgId, $key, (string) $val);
                    $changedKeys[] = $key;
                } elseif (! empty($validated['slack_webhook_clear'])) {
                    Setting::put($orgId, $key, null);
                    $changedKeys[] = $key;
                }
            } else {
                Setting::put($orgId, $key, $val);
                $changedKeys[] = $key;
            }
        }
        $smtpPass = $validated['smtp_pass'] ?? null;
        if ($smtpPass !== null && $smtpPass !== '') {
            Setting::putEncrypted($orgId, 'smtp_pass', (string) $smtpPass);
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
