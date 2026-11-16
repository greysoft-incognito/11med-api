<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every 11med Form Request. Rules mirror the route's zod schema; a failure
 * renders as 400 `VALIDATION_FAILED` naming the first failing field.
 */
abstract class ApiRequest extends FormRequest
{
    /**
     * Roles are enforced on the route group and ownership in the service.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;
}
