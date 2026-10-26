<?php

namespace App\Http\Requests\Admin;

class ConfirmCsvImportRequest extends CsvImportRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'preview_hash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/i'],
        ];
    }
}
