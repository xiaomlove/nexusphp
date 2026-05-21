<?php

namespace App\Http\Requests\Legacy;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the SYSOP+ "Mass e-mail to users by class" POST that the
 * legacy `public/massmail.php` form submits to itself.
 *
 * Phase 2 batch — replaces `public/massmail.php` (deleted in the
 * same PR).
 *
 * The legacy script (`public/massmail.php`):
 *   - validated `$_POST['or']` against the whitelist
 *     `['<', '>', '=', '<=', '>=']` (`stderr('Error', 'Invalid symbol!')`
 *     otherwise);
 *   - cast `$_POST['class']` via `intval` and ran `int_check` on it;
 *   - rejected an empty trimmed `$_POST['message']`
 *     (`stderr('Error', 'Empty message!')`);
 *   - silently allowed an empty subject (replacing it with
 *     `(no subject)`) and trimmed it to 80 chars.
 *
 * This Request mirrors those rules. The authorisation
 * (`class >= UC_SYSOP`) lives in the controller — same split as
 * `SendStaffMassMessageRequest` / `SendIncrementBulkRequest`.
 */
class SendMassMailRequest extends FormRequest
{
    public const VALID_OPERATORS = ['<', '>', '=', '<=', '>='];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'message' => is_string($this->input('message')) ? trim($this->input('message')) : $this->input('message'),
            'subject' => is_string($this->input('subject')) ? trim($this->input('subject')) : $this->input('subject'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'or' => ['required', 'string', 'in:'.implode(',', self::VALID_OPERATORS)],
            'class' => ['required', 'integer', 'min:0'],
            'subject' => ['nullable', 'string'],
            'message' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'or.required' => 'Invalid symbol!',
            'or.in' => 'Invalid symbol!',
            'class.required' => 'Invalid symbol!',
            'class.integer' => 'Invalid symbol!',
            'message.required' => 'Empty message!',
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
