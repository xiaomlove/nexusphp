<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentAllowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'family' => 'required|string',
            'start_name' => 'required|string',
            'peer_id_pattern' => 'required|string',
            'peer_id_match_num' => 'required|numeric',
            'peer_id_matchtype' => 'required|in:hex,dec',
            'peer_id_start' => 'required|string',
            'agent_pattern' => 'required|string',
            'agent_match_num' => 'required|numeric',
            'agent_matchtype' => 'required|in:hex,dec',
            'agent_start' => 'required|string',
            'exception' => 'required|in:yes,no',
            'allowhttps' => 'required|in:yes,no',
        ];
    }
}
