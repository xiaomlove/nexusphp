<?php
namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;

class OauthController extends Controller
{
    private int $clientId = 3;
    private string $baseUri;

    public function __construct()
    {
        $this->baseUri = getSchemeAndHttpHost();
    }
    public function Redirect(Request $request)
    {
        $request->session()->put('state', $state = Str::random(40));

        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->baseUri."/oauth/callback",
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
            'prompt' => 'none', // "none", "consent", or "login"
        ]);

        return redirect($this->baseUri.'/oauth/authorize?'.$query);

    }

    public function Callback(Request $request)
    {
//        $state = $request->session()->pull('state');
//
//        throw_unless(
//            strlen($state) > 0 && $state === $request->state,
//            \InvalidArgumentException::class
//        );

        $clientInfo = Client::query()->findOrFail($this->clientId);
        $response = Http::asForm()->post($this->baseUri.'/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $clientInfo->secret,
            'redirect_uri' => $this->baseUri.'/oauth/callback',
            'code' => $request->code,
        ]);

        return $response->json();
    }


    public function userInfo(): array
    {
        $user = Auth::user();
        $resource = new UserResource($user);
        return $resource->response()->getData(true);
    }
}
