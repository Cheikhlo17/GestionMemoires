<?php

namespace App\Http\Requests\Archive;

use Illuminate\Foundation\Http\FormRequest;

class UpdateArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('archive'));
    }

    public function rules(): array
    {
        return [
            'keywords' => ['sometimes', 'array', 'max:15'],
            'keywords.*' => ['string', 'max:50'],
            'visibility' => ['sometimes', 'in:public,private'],
            'show_final_grade' => ['sometimes', 'boolean'],
        ];
    }
}