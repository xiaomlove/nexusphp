<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name') }} - Authorization</title>

    <!-- Styles -->
    <link href="{{ asset('/css/app.css') }}" rel="stylesheet">

    <style>
        .passport-authorize {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-image: url("/pic/oauth2-authorize-bg.jpg");
        }

        .passport-authorize .card {
            padding: 40px;
            background-color: #ffffff;
        }

        .passport-authorize .card-header {
            font-size: 36px;
            text-align: center;
            margin-bottom: 15px;
        }

        .passport-authorize .scopes {
            margin-top: 20px;
        }

        .passport-authorize .buttons {
            margin-top: 25px;
            text-align: center;
        }

        .passport-authorize .btn {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #00BFFF;
            width: 125px;
            color: white;
        }

        .passport-authorize .btn-danger {
            background-color: #a83838;
        }

        .passport-authorize .btn-approve {
            margin-right: 15px;
        }

        .passport-authorize form {
            display: inline;
        }
    </style>
</head>
<body class="passport-authorize">
    <div class="card card-default">
        <div class="card-header">
            {{ __('oauth.authorization_request_title') }}
        </div>
        <div class="card-body">
            <!-- Introduction -->
            <p><strong>{{ $client->name }}</strong> {{ __('oauth.authorization_request_desc') }}.</p>

            <!-- Scope List -->
            @if (count($scopes) > 0)
                <div class="scopes">
                    <p><strong>This application will be able to:</strong></p>

                    <ul>
                        @foreach ($scopes as $scope)
                            <li>{{ $scope->description }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="buttons">
                <!-- Authorize Button -->
                <form method="post" action="{{ route('passport.authorizations.approve') }}">
                    @csrf

                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="btn btn-success btn-approve">{{ __('oauth.btn_approve') }}</button>
                </form>

                <!-- Cancel Button -->
                <form method="post" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')

                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button class="btn btn-danger">{{ __('oauth.btn_deny') }}</button>
                </form>
            </div>
        </div>
</div>
</body>
</html>
