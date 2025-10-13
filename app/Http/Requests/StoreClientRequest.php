<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'company_name' => ['required','string','max:255'],
            'industry' => ['nullable','string','max:255'],
            'niche' => ['nullable','string','max:255'],
            'primary_contact_name'  => ['nullable','string','max:255'],
            'primary_contact_email' => ['nullable','email','max:255'],
            'primary_contact_phone' => ['nullable','string','max:40'],
            'website' => ['nullable','url'],
            'address' => ['nullable','string','max:500'],
            'tags' => ['nullable','array'],
            'fronter' => ['nullable','array'],
            'closer'  => ['nullable','array'],
            'assigned_account_manager_id' => ['nullable','exists:users,id'],
            'google_business_profile_status'        => ['nullable','in:Not Created,Created,Pending,Verified,Suspended'],
            'google_business_profile_access_status' => ['nullable','in:No Access,Access Granted,Access Pending'],
            'client_activation_status'              => ['nullable','in:Inactive,Active,Paused,Cancelled'],
            'notes_by_cst'   => ['nullable','string'],
            'notes_by_sales' => ['nullable','string'],
            'notes_by_tech'  => ['nullable','string'],
            'status' => ['nullable','string','max:50'],
        ];
    }
}
