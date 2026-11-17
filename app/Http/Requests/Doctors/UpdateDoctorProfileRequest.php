<?php

namespace App\Http\Requests\Doctors;

use App\Enums\ConsultLanguage;
use App\Enums\Specialty;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `zUpdateDoctorProfileRequest`: any field, absent ones left alone.
 */
class UpdateDoctorProfileRequest extends ApiRequest
{
    public const SESSION_MINUTES = [15, 20, 30, 45, 60];

    public const CHAT_WINDOW_HOURS = [12, 24, 48, 72];

    private const TIME = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';

    public function rules(): array
    {
        $kobo = ['sometimes', 'required', 'integer', 'min:0', 'max:100000000'];

        return [
            'mdcn_number' => ['sometimes', 'required', 'string', 'min:4', 'max:32'],
            'specialty' => ['sometimes', 'required', Rule::enum(Specialty::class)],
            'sub_specialty' => ['sometimes', 'nullable', 'string', 'max:120'],
            'years_of_experience' => ['sometimes', 'required', 'integer', 'min:0', 'max:80'],
            'current_institution' => ['sometimes', 'nullable', 'string', 'max:160'],
            'languages' => ['sometimes', 'required', 'array', 'min:1', 'max:4'],
            'languages.*' => ['required', 'distinct', Rule::enum(ConsultLanguage::class)],
            'bio' => ['sometimes', 'required', 'string', 'max:500'],
            'photo_url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
            'fee_chat_kobo' => $kobo,
            'fee_audio_kobo' => $kobo,
            'fee_video_kobo' => $kobo,
            'session_minutes' => ['sometimes', 'required', 'integer', Rule::in(self::SESSION_MINUTES)],
            'chat_window_hours' => ['sometimes', 'required', 'integer', Rule::in(self::CHAT_WINDOW_HOURS)],
            'follow_up_fee_kobo' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'bank_name' => ['sometimes', 'required', 'string', 'max:120'],
            'bank_account_number' => ['sometimes', 'required', 'string', 'regex:/^\d{10}$/'],
            'availability' => ['sometimes', 'present', 'array', 'max:21'],
            'availability.*.day' => ['required', 'integer', 'between:0,6'],
            'availability.*.start' => ['required', 'string', self::TIME],
            'availability.*.end' => ['required', 'string', self::TIME],
            'is_available_now' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * A window ends after it starts; `HH:MM` strings compare as times.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('availability', []) as $index => $window) {
                    if (is_array($window) && is_string($window['start'] ?? null) && is_string($window['end'] ?? null) && $window['end'] <= $window['start']) {
                        $validator->errors()->add("availability.{$index}.end", __('validation.after', ['attribute' => 'end', 'date' => 'start']));
                    }
                }
            },
        ];
    }
}
