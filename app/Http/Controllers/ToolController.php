<?php

namespace App\Http\Controllers;

use App\Repositories\ToolRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Telegram\Bot\Api;
use Telegram\Bot\Commands\HelpCommand;

class ToolController extends Controller
{
    private $repository;

    public function __construct(ToolRepository $repository)
    {
        $this->repository = $repository;
    }

    public function notifications(): array
    {
        $user = Auth::user();
        $result = $this->repository->getNotificationCount($user);
        return $this->success($result);
    }

    public function tgBot(): string
    {
        $token = "xxxxxx";
        $telegram = new Api($token);
        $commandHelp = new HelpCommand();
        $commandBind = new TgCommandBind();
        $telegram->addCommand($commandHelp);
        $telegram->addCommand($commandBind);
        $update = $telegram->commandsHandler(true);
        return 'OK';
    }

    public function test(Request $request)
    {
        $token = "xxxxxx";
        $telegram = new Api($token);
//        $response = $telegram->getMe();
//        $response = $telegram->sendMessage([
//            "chat_id" => "-4170177008",
//            "text" => "Comes from xiaomlove!",
//        ]);

        $commandHelp = new HelpCommand();
        $commandBind = new TgCommandBind();

        $commands = [
            ['command' => $commandHelp->getName(), 'description' => $commandHelp->getDescription()],
            ['command' => $commandBind->getName(), 'description' => $commandBind->getDescription()],
        ];
        $response = $telegram->setMyCommands([
            "commands" => json_encode($commands)
        ]);
//        $response = $telegram->setWebhook(['url' => 'https://dev.nexusphp.org/nexusphp-tgbot/webhook']);

//        $response = $telegram->getWebhookUpdate();
        dd($response);
    }

}
