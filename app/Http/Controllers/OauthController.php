<?php
namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\OauthClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;

class OauthController extends Controller
{
    private int $clientId = 8;
    private string $baseUri;

    private ?OauthClient $client = null;

//    public function __construct()
//    {
//        $this->baseUri = getSchemeAndHttpHost();
//
//        $this->client = OauthClient::query()->find($this->clientId);
//    }
    public function redirect(Request $request)
    {
//        $request->session()->put('state', $state = Str::random(40));

        $query = http_build_query([
            'client_id' => $this->client->id,
            'redirect_uri' => $this->client->redirect,
            'response_type' => 'code',
            'scope' => '',
//            'state' => $state,
//            'prompt' => 'none', // "none", "consent", or "login"
        ]);

        return redirect($this->baseUri.'/oauth/authorize?'.$query);

    }

    public function callback(Request $request)
    {
//        $state = $request->session()->pull('state');
//
//        throw_unless(
//            strlen($state) > 0 && $state === $request->state,
//            \InvalidArgumentException::class
//        );

        $response = Http::asForm()->post($this->baseUri.'/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'redirect_uri' => $this->client->redirect,
            'code' => $request->code,
        ]);

        return $response->json();
    }

    public function debug(Request $request)
    {
        dd($request->all());
    }


    public function userInfo(): array
    {
        $user = Auth::user();
        $resource = new UserResource($user);
        return $resource->response()->getData(true)['data'];
    }
}
