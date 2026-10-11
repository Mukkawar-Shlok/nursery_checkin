<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInChildRequest extends FormRequest
{
    public function rules(): array
    {
        return ['child_id' => ['required', 'uuid']];
    }
}
