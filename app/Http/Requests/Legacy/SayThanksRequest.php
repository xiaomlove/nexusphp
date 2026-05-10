<?php

namespace App\Http\Requests\Legacy;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the "I'm thankful for this torrent" POST that the
 * `saythanks(torrentid)` JS helper in `public/js/common.js` fires.
 *
 * Phase 2 of the legacy migration — see
 * `docs/migration-recipe.md` § "Recipe for thanks.php".
 *
 * The legacy script (`public/thanks.php`) accepted a single
 * `id` parameter and silently ignored anything else; we keep
 * that contract so the JS doesn't have to change in this PR.
 */
class SayThanksRequest extends FormRequest
{
    /**
     * Authorization is handled by the route's `auth.nexus:nexus-web`
     * middleware. By the time the FormRequest runs, we already know
     * the caller is authenticated.
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
            // `exists:torrents,id` mirrors the legacy
            // `NexusDB::table('torrents')->where('id', $id)->value('owner')`
            // existence check — invalid IDs surface as 422, the same
            // shape Laravel uses for every other FormRequest in this
            // app, instead of the legacy `stderr("Invalid torrent id!")`
            // HTML page.
            'id' => ['required', 'integer', 'min:1', 'exists:torrents,id'],
        ];
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
            'id.exists' => 'No torrent exists with that id.',
        ];
    }

    /**
     * Always reject with a 422 JSON body — the legacy AJAX helper
     * (`public/js/common.js#saythanks`) sends a plain
     * `application/x-www-form-urlencoded` POST, so by default
     * Laravel would 302-redirect-back-with-errors. The caller only
     * looks at HTTP status, so a structured 422 is both nicer for
     * future API consumers and a no-op for the existing JS.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
