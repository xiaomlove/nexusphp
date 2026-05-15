<?php

namespace App\Http\Requests\Legacy;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the "Mass PM to all Staff members and users" POST that the
 * legacy `public/staffmess.php` form submits to `/takestaffmess.php`.
 *
 * Phase 2 batch — replaces `public/takestaffmess.php` (deleted in the
 * same PR). See `docs/migration-recipe.md` § "Common test pitfalls
 * (Phase 2 lessons)" for the wider context.
 *
 * The legacy script (`public/takestaffmess.php`):
 *   - required a non-empty `msg` (otherwise `stderr()`)
 *   - happily accepted an empty `subject` (no check)
 *   - validated each entry of `$_POST['clases']` (the typo'd field) but
 *     never used it — the SQL filter only reads `$_POST['classes']`
 *   - accepted `sender` ∈ {self, system} via radio (default `self`)
 *   - bailed with `stderr('Error', 'No valid filter')` if neither
 *     `classes` nor a plugin filter produced any WHERE clause; that
 *     check is **not** in this FormRequest because it depends on
 *     `apply_filter('role_query_conditions')`, which we run in the
 *     controller (the FormRequest layer can't model plugin filters).
 *
 * This Request mirrors those rules without the typo'd field. The
 * authorization (`class >= UC_ADMINISTRATOR`) lives in the controller
 * — see `AddUserController` / `DonatedController` for the same split.
 */
class SendStaffMassMessageRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller (admin-class check),
     * after the `auth.nexus:nexus-web` middleware on the route has
     * confirmed the caller is logged in. Keeping `authorize()` at
     * `true` lets validation rules run first so input shape errors
     * surface as 422, not 403.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirror legacy `trim($_POST['msg'])` / `trim($_POST['subject'])`
     * so the "non-empty" check below matches the original semantics
     * exactly.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'msg' => is_string($this->input('msg')) ? trim($this->input('msg')) : $this->input('msg'),
            'subject' => is_string($this->input('subject')) ? trim($this->input('subject')) : $this->input('subject'),
        ]);
    }

    /**
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            // Mirrors legacy `if (!$msg) stderr(...)` — empty body
            // after trim is rejected. `messages.msg` is `mediumtext`
            // (see `2023_04_30_054425_alter_table_messages_msg_column_type_from_text_to_mediumtext.php`).
            'msg' => ['required', 'string'],
            // Legacy accepted any subject (including empty); we keep
            // it optional but cap at the column width (`varchar(128)`
            // per `2021_06_08_113437_create_messages_table.php`).
            'subject' => ['nullable', 'string', 'max:128'],
            // The form-render in `public/staffmess.php` posts a
            // `classes[]` array of class IDs. Required-but-may-be-empty
            // post-filter is enforced in the controller (after
            // `apply_filter('role_query_conditions', ...)`); here we
            // just type-check the shape.
            'classes' => ['sometimes', 'array'],
            'classes.*' => ['integer', 'min:0'],
            // `sender` radio in the legacy form is `self` or `system`.
            // Default is `self` (legacy ternary
            // `$_POST['sender'] == 'system' ? 0 : (int) $CURUSER['id']`).
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
            'subject.max' => 'Subject is too long; please keep it under 128 characters.',
        ];
    }

    /**
     * Always reject with a 422 JSON body — see `docs/migration-recipe.md`
     * Pitfall 5 ("Handler::getHttpStatusCode collapses RuntimeException
     * to 200"). Throwing `HttpResponseException` short-circuits the
     * global ValidationException renderable so the 422 actually
     * arrives.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
