<?php

namespace App\Http\Requests\Legacy;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the SYSOP+ "Batch add bonus / attendance card / invites /
 * uploaded / temporary-invites" POST that the legacy
 * `public/increment-bulk.php` form submits to
 * `/take-increment-bulk.php`.
 *
 * Phase 2 batch — replaces `public/take-increment-bulk.php` (deleted
 * in the same PR). See `docs/migration-recipe.md` § "Common test
 * pitfalls (Phase 2 lessons)" for the wider context.
 *
 * The legacy script (`public/take-increment-bulk.php`):
 *   - rejected any non-POST verb with `stderr('Error', 'Permission denied!')`
 *     (HTTP 200 + body); the `Route::post(...)` registration in
 *     `routes/web.php` makes Laravel produce a 405 by itself.
 *   - required a non-empty trimmed `msg`, `amount`, `type`
 *     (`stderr("Error", "Don't leave any fields blank.")`);
 *   - required `is_numeric($amount)`;
 *   - required `$type` to be a key of `$lang_incrementbulk['types']`
 *     (`seedbonus`, `attendance_card`, `invites`, `uploaded`,
 *     `tmp_invites`);
 *   - bailed with `stderr("Error","No valid filter")` if neither
 *     `classes` nor a plugin filter produced any WHERE clause; that
 *     check is **not** in this FormRequest because it depends on
 *     `apply_filter('role_query_conditions')`, which we run in the
 *     controller (the FormRequest layer can't model plugin filters).
 *   - required `intval($_POST['duration']) > 0` only when
 *     `$type === 'tmp_invites'`.
 *
 * This Request mirrors those rules. The authorisation
 * (`class >= UC_SYSOP`) lives in the controller — same split as
 * `SendStaffMassMessageRequest` / `AddUserController`.
 */
class SendIncrementBulkRequest extends FormRequest
{
    public const TYPE_SEEDBONUS = 'seedbonus';

    public const TYPE_ATTENDANCE_CARD = 'attendance_card';

    public const TYPE_INVITES = 'invites';

    public const TYPE_UPLOADED = 'uploaded';

    public const TYPE_TMP_INVITES = 'tmp_invites';

    public const VALID_TYPES = [
        self::TYPE_SEEDBONUS,
        self::TYPE_ATTENDANCE_CARD,
        self::TYPE_INVITES,
        self::TYPE_UPLOADED,
        self::TYPE_TMP_INVITES,
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'msg' => is_string($this->input('msg')) ? trim($this->input('msg')) : $this->input('msg'),
            'subject' => is_string($this->input('subject')) ? trim($this->input('subject')) : $this->input('subject'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'msg' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:128'],
            'type' => ['required', 'string', 'in:'.implode(',', self::VALID_TYPES)],
            'amount' => ['required', 'numeric'],
            // Required only when `type === 'tmp_invites'`. The legacy
            // script ignored the value otherwise. The conditional
            // rule keeps that contract: GET-form callers don't need
            // to send `duration` for non-temp-invite types.
            'duration' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:type,'.self::TYPE_TMP_INVITES,
            ],
            'classes' => ['sometimes', 'array'],
            'classes.*' => ['integer', 'min:0'],
            'sender' => ['nullable', 'string', 'in:self,system'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'msg.required' => "Don't leave any fields blank.",
            'amount.required' => "Don't leave any fields blank.",
            'amount.numeric' => 'amount must be numeric',
            'type.required' => "Don't leave any fields blank.",
            'type.in' => 'Invalid type',
            'duration.required_if' => 'Invalid duration',
            'duration.integer' => 'Invalid duration',
            'duration.min' => 'Invalid duration',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
