<?php

namespace App\Http\Requests\Legacy;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the "send a message to staff" POST that the legacy
 * `public/contactstaff.php` form submits to `/takecontact.php`.
 *
 * Phase 2.3 of the legacy migration — see
 * `docs/migration-recipe.md` § "Common test pitfalls (Phase 2 lessons)".
 *
 * The legacy script (`public/takecontact.php`) trimmed both fields,
 * rejected empty values via `stderr()` (HTTP 200 + body) and silently
 * accepted any subject up to whatever MySQL truncated. We keep the
 * trimming + emptiness check, additionally cap the subject at the
 * column width (`varchar(128)` per `2021_06_08_113437_create_staffmessages_table.php`),
 * and surface every failure as a 422 instead of the legacy 200/HTML
 * body so the new endpoint is usable from non-browser callers.
 */
class SendStaffMessageRequest extends FormRequest
{
    /**
     * Authorization is handled by the route's `auth.nexus:nexus-web`
     * middleware; if we got here, the caller is already logged in.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim leading/trailing whitespace before validation runs so the
     * "non-empty" rules below match the legacy `trim($_POST[...])`
     * semantics exactly.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => is_string($this->input('subject')) ? trim($this->input('subject')) : $this->input('subject'),
            'body' => is_string($this->input('body')) ? trim($this->input('body')) : $this->input('body'),
        ]);
    }

    /**
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            // Mirrors legacy `if (!$subject) stderr(...)` — empty
            // subject after trim is rejected. 128 char cap matches
            // the `staffmessages.subject` column type.
            'subject' => ['required', 'string', 'max:128'],
            // Mirrors legacy `if (!$msg) stderr(...)` — body is
            // required; column is `text`, so no upper bound is
            // needed here (MySQL caps at 64 KiB which is well past
            // anything a sane staff message would carry).
            'body' => ['required', 'string'],
            // The legacy script accepted any string in `returnto`
            // and passed it through `htmlspecialchars` before the
            // `Location:` header. We keep an optional, type-checked
            // field; the controller is responsible for rejecting
            // off-host targets (open-redirect protection).
            'returnto' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Please define a subject.',
            'subject.max' => 'Subject is too long; please keep it under 128 characters.',
            'body.required' => 'Please enter something for the body.',
        ];
    }

    /**
     * Always reject with a 422 JSON body — see
     * `docs/migration-recipe.md` Pitfall 5 ("Handler::getHttpStatusCode
     * collapses RuntimeException to 200"). Throwing
     * `HttpResponseException` here short-circuits the global
     * ValidationException renderable so the 422 actually arrives.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
