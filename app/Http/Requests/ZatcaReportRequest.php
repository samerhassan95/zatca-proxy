<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ZatcaReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Invoice data
            'invoice' => 'required|array',
            'invoice.invoice_number' => 'required|string|max:255',
            'invoice.created_at' => 'required|date',
            'invoice.sub_total' => 'required|numeric|min:0',
            'invoice.total_tax_amount' => 'required|numeric|min:0',
            'invoice.total' => 'required|numeric|min:0',
            'invoice.payment_method' => 'nullable|string|in:cash,card,credit_card,bank_transfer',
            'invoice.zatca_uuid' => 'nullable|string|uuid',
            'invoice.zatca_invoice_counter' => 'nullable|integer|min:1',
            'invoice.zatca_previous_hash' => 'nullable|string',
            
            // Invoice items
            'invoice.items' => 'required|array|min:1',
            'invoice.items.*.name' => 'required|string|max:255',
            'invoice.items.*.quantity' => 'required|numeric|min:0.01',
            'invoice.items.*.price' => 'required|numeric|min:0',
            'invoice.items.*.amount' => 'required|numeric|min:0',
            'invoice.items.*.tax_amount' => 'nullable|numeric|min:0',
            'invoice.items.*.tax_percentage' => 'nullable|numeric|min:0|max:100',

            // Company data
            'company' => 'required|array',
            'company.company_name' => 'required|string|max:255',
            'company.vat_number' => 'required|string|max:15',
            'company.commercial_registration' => 'nullable|string|max:10',
            'company.address' => 'nullable|string|max:500',
            'company.city' => 'nullable|string|max:100',
            'company.zip_code' => 'nullable|string|max:10',
            'company.zatca_certificate' => 'required|string',
            'company.zatca_private_key' => 'required|string',
            'company.zatca_secret' => 'nullable|string',

            // Environment
            'environment' => 'nullable|string|in:developer,simulation,production',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice.required' => 'Invoice data is required',
            'invoice.items.required' => 'Invoice must have at least one item',
            'invoice.items.min' => 'Invoice must have at least one item',
            'company.required' => 'Company data is required',
            'company.zatca_certificate.required' => 'ZATCA certificate is required',
            'company.zatca_private_key.required' => 'ZATCA private key is required',
            'environment.in' => 'Environment must be one of: developer, simulation, production',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
                'timestamp' => now()->toISOString(),
            ], 422)
        );
    }
}