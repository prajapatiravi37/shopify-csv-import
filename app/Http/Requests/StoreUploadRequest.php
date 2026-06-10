<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Please select a CSV file to upload.',
            'csv_file.mimes' => 'Only CSV files are allowed.',
            'csv_file.max' => 'CSV file must not exceed 10MB.',
        ];
    }
}
