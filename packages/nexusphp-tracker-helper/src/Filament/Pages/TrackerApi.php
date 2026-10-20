<?php

namespace NexusPlugin\Tracker\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use NexusPlugin\Tracker\Enums\ApiEnum;
use NexusPlugin\Tracker\Tracker;
use UnitEnum;
class TrackerApi extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'tracker::api';

//    protected Width | string | null $maxContentWidth = 'full';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 101;

    protected static string|null|UnitEnum $navigationGroup = 'Tracker';

    public ?array $data = [
        'api_endpoint' => null,
    ];

    // 用于在 Blade 中显示结果
    public $apiResponse = null;
    public ?int $statusCode = null;
    public ?string $errorMessage = null;

    public function getTitle(): string|Htmlable
    {
        return self::getNavigationLabel();
    }

    public static function getNavigationLabel(): string
    {
        return Tracker::trans("api.label");
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('api_endpoint')
                    ->label(Tracker::trans("api.api_endpoint"))
                    ->options(ApiEnum::class)
                    ->searchable()
                    ->live(true)
                    ->required()
                    ->afterStateUpdated(function (HasForms $livewire, Select $component) {
                        $livewire->validateOnly($component->getStatePath());
                    })
                ,

                TextInput::make('user_id')
                    ->label(Tracker::trans("api.request_parameters.user_id"))
                    ->numeric()
                    ->required()
                    ->hidden(fn (Get $get) => !in_array("user_id", $get('api_endpoint')?->getRequireParameters() ?? []))
                ,
                TextInput::make('torrent_id')
                    ->label(Tracker::trans("api.request_parameters.torrent_id"))
                    ->numeric()
                    ->required()
                    ->hidden(fn (Get $get) => !in_array("torrent_id", $get('api_endpoint')?->getRequireParameters() ?? []))
                ,
                TextInput::make('peer_id')
                    ->required()
                    ->label(Tracker::trans("api.request_parameters.peer_id"))
                    ->hidden(fn (Get $get) => !in_array("peer_id", $get('api_endpoint')?->getRequireParameters() ?? []))
                ,
                TextInput::make('ip')
                    ->required()
                    ->label(Tracker::trans("api.request_parameters.ip"))
                    ->hidden(fn (Get $get) => !in_array("ip", $get('api_endpoint')?->getRequireParameters() ?? []))
                ,
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('submitRequest')
                ->label(Tracker::trans("api.submit_request"))
                ->submit('submitRequest'), // 告诉表单提交时调用 'submitRequest' 方法
        ];
    }

    // 6. 实现核心的 API 请求逻辑
    public function submitRequest(): void
    {
        $data = $this->form->getState();
        $apiEndpoint = $data['api_endpoint'];
        unset($data['api_endpoint']);
        $data['id'] = $data['user_id'] ?? $data['torrent_id'] ?? 0;
        $data['ids'] = array_map('intval', preg_split("/[\r\n\s,，]+/", $data['id']));
        $url = sprintf(
            "%s/api/%s",
            trim(get_setting("go_tracker.internal_host"), '/'),
            trim($apiEndpoint->value, '/')
        );
        do_log("going to post $url with data: " . json_encode($data));

        // 每次请求前清空旧数据
        $this->apiResponse = null;
        $this->statusCode = null;
        $this->errorMessage = null;

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Passkey' =>  Auth::user()->passkey,
            ])->post($url, $data);

            $this->statusCode = $response->status();

            if ($response->successful()) {
                $this->apiResponse = $response->json() ?? $response->body();
            } else {
                $this->errorMessage = 'Failed';
                $this->apiResponse = $response->body(); // 显示错误信息
            }

        } catch (RequestException $e) {
            $this->statusCode = $e->response ? $e->response->status() : 500;
            $this->errorMessage = 'Exception: ' . $e->getMessage();
            $this->apiResponse = $e->getMessage();
        } catch (\Exception $e) {
            $this->statusCode = 500;
            $this->errorMessage = 'Unknown error: ' . $e->getMessage();
            $this->apiResponse = $e->getMessage();
        }
    }

}
