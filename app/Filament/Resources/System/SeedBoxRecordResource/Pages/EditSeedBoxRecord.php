<?php

namespace App\Filament\Resources\System\SeedBoxRecordResource\Pages;

use App\Filament\Resources\System\SeedBoxRecordResource;
use App\Repositories\SeedBoxRepository;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeedBoxRecord extends EditRecord
{
    protected static string $resource = SeedBoxRecordResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function save(bool $shouldRedirect = true): void
    {
        $data = $this->form->getState();
        $rep = new SeedBoxRepository();
        try {
            $this->record = $rep->update($data, $this->record->id);
            $this->notify('success', $this->getSavedNotificationMessage());
            $this->redirect($this->getResource()::getUrl('index'));
        } catch (\Exception $exception) {
            $this->notify('danger', $exception->getMessage());
        }
    }
}
