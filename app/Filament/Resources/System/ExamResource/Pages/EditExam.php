<?php

namespace App\Filament\Resources\System\ExamResource\Pages;

use App\Filament\Resources\System\ExamResource;
use App\Repositories\ExamRepository;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function save(bool $shouldRedirect = true): void
    {
        $data = $this->form->getState();
        $examRep = new ExamRepository();
        try {
            $this->record = $examRep->update($data, $this->record->id);
            $this->notify('success', $this->getSavedNotificationMessage());
            $this->redirect($this->getResource()::getUrl('index'));
        } catch (\Exception $exception) {
            $this->notify('danger', $exception->getMessage());
        }
    }
}
