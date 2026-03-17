<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CustomField;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class CustomFieldController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        abort_unless($request->user()?->can('custom-fields.manage'), 403);

        $fields = CustomField::query()
            ->forOrg((int) $organization->id)
            ->forEntity(CustomField::ENTITY_CLIENT)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Settings/CustomFields/Index', [
            'organizationSlug' => $organization->slug,
            'customFields' => $fields->map(fn (CustomField $f) => [
                'id' => $f->id,
                'label' => $f->label,
                'slug' => $f->slug,
                'type' => $f->type,
                'options' => $f->options,
                'is_required' => $f->is_required,
                'sort_order' => $f->sort_order,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->can('custom-fields.manage'), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('custom_fields', 'slug')->where(function ($q) use ($organization): void {
                    $q->where('organization_id', $organization->id)
                        ->where('entity', CustomField::ENTITY_CLIENT);
                }),
            ],
            'type' => ['required', 'string', Rule::in(CustomField::TYPES)],
            'options' => [
                'nullable',
                'array',
                Rule::requiredIf(in_array($request->input('type'), [CustomField::TYPE_SELECT, CustomField::TYPE_MULTISELECT], true)),
            ],
            'options.*' => ['string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['organization_id'] = (int) $organization->id;
        $data['entity'] = CustomField::ENTITY_CLIENT;
        $data['slug'] = $data['slug'] ?? CustomField::slugFromLabel($data['label']);
        $data['is_required'] = (bool) ($data['is_required'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $field = DB::transaction(function () use ($data, $organization, $request): CustomField {
            $field = CustomField::query()->create($data);
            AuditLogger::log(
                $organization,
                $request->user(),
                'created',
                'custom_field',
                (int) $field->id,
                ['after' => $field->getAttributes()]
            );
            return $field;
        });

        return redirect()
            ->route('custom-fields.index', ['organization' => $organization->slug])
            ->with('success', 'Custom field created.');
    }

    public function update(Request $request, Organization $organization, CustomField $customField): RedirectResponse
    {
        abort_unless($request->user()?->can('custom-fields.manage'), 403);
        abort_unless((int) $customField->organization_id === (int) $organization->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('custom_fields', 'slug')
                    ->ignore($customField->id)
                    ->where(function ($q) use ($organization): void {
                        $q->where('organization_id', $organization->id)
                            ->where('entity', CustomField::ENTITY_CLIENT);
                    }),
            ],
            'type' => ['required', 'string', Rule::in(CustomField::TYPES)],
            'options' => [
                'nullable',
                'array',
                Rule::requiredIf(in_array($request->input('type'), [CustomField::TYPE_SELECT, CustomField::TYPE_MULTISELECT], true)),
            ],
            'options.*' => ['string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['slug'] = $data['slug'] ?? $customField->slug;
        $data['is_required'] = (bool) ($data['is_required'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? $customField->sort_order);

        $before = $customField->getOriginal();
        $customField->update($data);
        AuditLogger::log(
            $organization,
            $request->user(),
            'updated',
            'custom_field',
            (int) $customField->id,
            ['before' => $before, 'after' => $customField->getAttributes()]
        );

        return redirect()
            ->route('custom-fields.index', ['organization' => $organization->slug])
            ->with('success', 'Custom field updated.');
    }

    public function destroy(Request $request, Organization $organization, CustomField $customField): RedirectResponse
    {
        abort_unless($request->user()?->can('custom-fields.manage'), 403);
        abort_unless((int) $customField->organization_id === (int) $organization->id, 404);

        $id = (int) $customField->id;
        $snapshot = $customField->toArray();
        $customField->delete();

        AuditLogger::log(
            $organization,
            $request->user(),
            'deleted',
            'custom_field',
            $id,
            ['before' => $snapshot]
        );

        return redirect()
            ->route('custom-fields.index', ['organization' => $organization->slug])
            ->with('success', 'Custom field deleted.');
    }
}
