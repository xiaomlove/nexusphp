<?php

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\AgentAllowResource;
use App\Repositories\AgentAllowRepository;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListAgentAllows extends PageList
{
    protected static string $resource = AgentAllowResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('check')
                ->label(__('admin.resources.agent_allow.check_modal_btn'))
                ->form([
                    Forms\Components\TextInput::make('peer_id')->required(),
                    Forms\Components\TextInput::make('agent')->required(),
                ])
                ->modalHeading(__('admin.resources.agent_allow.check_modal_header'))
                ->action(function ($data) {
                    $agentAllowRep = new AgentAllowRepository();
                    try {
                        $result = $agentAllowRep->checkClient($data['peer_id'], $data['agent']);
                        $this->notify('success', __('admin.resources.agent_allow.check_pass_msg', ['id' => $result->id]));
                    } catch (\Exception $exception) {
                        $this->notify('danger', $exception->getMessage());
                    }
                })

        ];
    }

}
