<?php

namespace App\Http\Requests\Legacy;

use App\Services\BonusRewardService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Input-shape validation for the "give magic" POST that
 * `public/js/common.js#saveMagicValue` fires at `/magic.php`.
 *
 * Phase 3 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 3 — big user pages". Domain validation (insufficient
 * bonus, self-reward, idempotency, daily limit) lives in
 * {@see BonusRewardService::attempt()} and is reported
 * via the legacy JSON envelope; this FormRequest only enforces the
 * payload shape so we don't have to keep `is_numeric()` guards
 * scattered through the controller.
 */
class MagicRewardRequest extends FormRequest
{
    /**
     * Authorization is handled by the route's
     * `auth.nexus:nexus-web` middleware. By the time the
     * FormRequest fires, the caller is already authenticated.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // `exists:torrents,id` keeps the legacy
            // `NexusDB::table('torrents')->where('id', $id)->select(['owner'])->first()`
            // existence check (legacy returned `Invalid torrent id!`
            // when the row was missing — we keep the same wire shape
            // for unknown ids by routing through
            // `failedValidation()` below).
            'id' => ['required', 'integer', 'min:1', 'exists:torrents,id'],
            // `value` is enforced as a positive integer here; the
            // "must be in the configured option set" check stays in
            // the service so it can be reused outside HTTP.
            'value' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * The legacy script always returned HTTP 200 with a
     * `{ret: -1, msg, data}` JSON body — even for malformed input.
     * The JS client (`saveMagicValue`) only inspects `res.ret`, so
     * a Laravel-default 422 with `{message, errors}` would silently
     * break the alert. Mirror the legacy envelope, swap in a
     * sensible message, and return HTTP 200 so the existing JS
     * keeps working unchanged.
     */
    protected function failedValidation(Validator $validator): void
    {
        $first = (string) $validator->errors()->first();

        throw new HttpResponseException(response()->json([
            'ret' => -1,
            'msg' => $first === '' ? 'Invalid input.' : $first,
            'data' => [],
        ]));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => 'A torrent id is required.',
            'id.integer' => 'A torrent id must be an integer.',
            'id.min' => 'A torrent id must be a positive integer.',
            'id.exists' => 'Invalid torrent id!',
            'value.required' => 'A bonus value is required.',
            'value.integer' => 'A bonus value must be an integer.',
            'value.min' => 'A bonus value must be a positive integer.',
        ];
    }

    /**
     * Normalize the payload — the legacy script silently coerced
     * `value` to `(int) abs(...)`. We keep the same behaviour for
     * the absolute value (negative inputs become positive) so we
     * don't break clients that send "-100" via a buggy widget, but
     * we still reject `0` via the `min:1` rule.
     */
    protected function prepareForValidation(): void
    {
        $value = $this->input('value');
        if (is_numeric($value)) {
            $this->merge(['value' => (int) abs((int) $value)]);
        }
    }
}
