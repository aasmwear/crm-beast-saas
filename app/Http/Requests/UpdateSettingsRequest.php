<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Organization table
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
            'week_start' => ['required', 'string', 'in:Sunday,Monday'],
            'logo' => ['nullable', 'image', 'max:1024'],

            // Settings table: locale, currency
            'locale' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'size:3'],

            // Settings table: work_hours
            'work_hours' => ['nullable', 'array'],
            'work_hours.work_week' => ['nullable', 'string', 'in:Mon-Fri,Sun-Thu'],
            'work_hours.start_time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'work_hours.end_time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],

            // Settings table: notifications.defaults
            'notifications_defaults' => ['nullable', 'array'],
            'notifications_defaults.channels' => ['nullable', 'array'],
            'notifications_defaults.channels.inapp' => ['nullable', 'boolean'],
            'notifications_defaults.channels.email' => ['nullable', 'boolean'],
            'notifications_defaults.types' => ['nullable', 'array'],

            // Settings table: integrations (empty string allowed for clearing)
            'slack_webhook_url' => [
                'nullable',
                'string',
                'max:500',
                function ($attribute, $value, $fail): void {
                    if ($value !== '' && $value !== null && ! filter_var($value, FILTER_VALIDATE_URL)) {
                        $fail('The Slack webhook URL must be a valid URL.');
                    }
                },
            ],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_user' => ['nullable', 'string', 'max:255'],
            'smtp_pass' => ['nullable', 'string', 'max:255'],
            'smtp_from' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'work_hours.work_week' => 'work week',
            'work_hours.start_time' => 'start time',
            'work_hours.end_time' => 'end time',
            'notifications_defaults' => 'notification defaults',
            'slack_webhook_url' => 'Slack webhook URL',
            'smtp_host' => 'SMTP host',
            'smtp_port' => 'SMTP port',
            'smtp_user' => 'SMTP username',
            'smtp_pass' => 'SMTP password',
            'smtp_from' => 'SMTP from address',
        ];
    }
}
