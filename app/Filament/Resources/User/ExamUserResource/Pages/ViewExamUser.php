<?php

namespace App\Filament\Resources\User\ExamUserResource\Pages;

use App\Filament\Resources\User\ExamUserResource;
use App\Models\Exam;
use App\Repositories\ExamRepository;
use Carbon\Carbon;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Table;
use Filament\Forms;

class ViewExamUser extends ViewRecord
{
    protected static string $resource = ExamUserResource::class;

    protected static string $view = 'filament.resources.user.exam-user-resource.pages.detail';

    private function getDetailCardData(): array
    {
//        dd($this->record);
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
            'label' => __('label.exam.label'),
            'value' => $record->exam->name,
        ];
        $data[] = [
            'label' => __('label.begin'),
            'value' => $record->begin,
        ];
        $data[] = [
            'label' => __('label.end'),
            'value' => $record->end,
        ];
        $data[] = [
            'label' => __('label.exam_user.is_done'),
            'value' => $record->isDoneText,
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
        /** @var Exam $exam */
        $exam = $this->record->exam;
        return [
            'cardData' => $this->getDetailCardData(),
            'result_pass_trans_key' => $exam->getPassResultTransKey('pass'),
            'result_not_pass_trans_key' => $exam->getPassResultTransKey('not_pass'),
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Avoid')
                ->requiresConfirmation()
                ->action(function () {
                    $examRep = new ExamRepository();
                    try {
                        $examRep->avoidExamUser($this->record->id);
                        $this->notify('success', 'Success !');
                        $this->record = $this->resolveRecord($this->record->id);
                    } catch (\Exception $exception) {
                        $this->notify('danger', $exception->getMessage());
                    }
                })
                ->label(__('admin.resources.exam_user.action_avoid')),

            Actions\Action::make('UpdateEnd')
                ->mountUsing(fn (Forms\ComponentContainer $form) => $form->fill([
                    'end' => $this->record->end,
                ]))
                ->form([
                    Forms\Components\DateTimePicker::make('end')
                        ->required()
                        ->label(__('label.end'))
                    ,
                    Forms\Components\Textarea::make('reason')
                        ->label(__('label.reason'))
                    ,
                ])
                ->action(function (array $data) {
                    $examRep = new ExamRepository();
                    try {
                        $examRep->updateExamUserEnd($this->record, Carbon::parse($data['end']), $data['reason'] ?? "");
                        $this->notify('success', 'Success !');
                        $this->record = $this->resolveRecord($this->record->id);
                    } catch (\Exception $exception) {
                        $this->notify('danger', $exception->getMessage());
                    }
                })
                ->label(__('admin.resources.exam_user.action_update_end')),

            Actions\DeleteAction::make(),
        ];
    }
}
