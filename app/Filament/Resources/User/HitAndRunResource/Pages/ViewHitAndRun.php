<?php

namespace App\Filament\Resources\User\HitAndRunResource\Pages;

use App\Filament\Resources\User\HitAndRunResource;
use App\Models\HitAndRun;
use App\Repositories\HitAndRunRepository;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;

class ViewHitAndRun extends ViewRecord
{
    protected static string $resource = HitAndRunResource::class;

    protected static string $view = 'filament.detail-card';

    private function getDetailCardData(): array
    {
        $data = [];
        $record = $this->record;
        $data[] = [
            'label' => 'ID',
            'value' => $record->id,
        ];
        $data[] = [
            'label' => __('label.status'),
            'value' => $record->statusText,
        ];
        $data[] = [
            'label' => __('label.username'),
            'value' => $record->user->username,
        ];
        $data[] = [
            'label' => __('label.torrent.label'),
            'value' => $record->torrent->name,
        ];
        $data[] = [
            'label' => __('label.uploaded'),
            'value' => $record->snatch->uploadedText,
        ];
        $data[] = [
            'label' => __('label.downloaded'),
            'value' => $record->snatch->downloadedText,
        ];
        $data[] = [
            'label' => __('label.ratio'),
            'value' => $record->snatch->shareRatio,
        ];
        $data[] = [
            'label' => __('label.seed_time_required'),
            'value' => $record->seedTimeRequired,
        ];
        $data[] = [
            'label' => __('label.inspect_time_left'),
            'value' => $record->inspectTimeLeft,
        ];
        $data[] = [
            'label' => __('label.comment'),
            'value' => nl2br($record->comment),
        ];
        $data[] = [
            'label' => __('label.created_at'),
            'value' => $record->created_at,
        ];
        $data[] = [
            'label' => __('label.updated_at'),
            'value' => $record->updated_at,
        ];
        return $data;
    }

    protected function getViewData(): array
    {
        return [
            'cardData' => $this->getDetailCardData(),
        ];
    }

    protected function getActions(): array
    {
        $actions = [];
        if (in_array($this->record->status, HitAndRun::CAN_PARDON_STATUS)) {
            $actions[] = Actions\Action::make('Pardon')
                ->requiresConfirmation()
                ->action(function () {
                    $hitAndRunRep = new HitAndRunRepository();
                    try {
                        $hitAndRunRep->pardon($this->record->id, Auth::user());
                        $this->notify('success', 'Success !');
                        $this->record = $this->resolveRecord($this->record->id);
                    } catch (\Exception $exception) {
                        $this->notify('danger', $exception->getMessage());
                    }
                })
                ->label(__('admin.resources.hit_and_run.action_pardon'))
            ;
        }
        $actions[] = Actions\DeleteAction::make();

        return $actions;
    }

}
