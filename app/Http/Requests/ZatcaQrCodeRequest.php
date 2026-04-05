<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ZatcaQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Invoice data (minimal for QR code)
            'invoice' => 'required|array',
            'invoice.created_at' => 'required|date',
            'invoice.total' => 'required|numeric|min:0',
            'invoice.total_tax_amount' => 'required|numeric|min:0',

            // Company data (minimal for QR code)
            'company' => 'required|array',
            'company.company_name' => 'required|string|max:255',
            'company.vat_number' => 'required|string|max:15',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice.required' => 'Invoice data is required',
            'company.required' => 'Company data is required',
            'company.company_name.required' => 'Company name is required',
            'company.vat_number.required' => 'VAT number is required',
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