<?php

namespace App\Filament\Resources\System\ExamResource\Pages;

use App\Filament\Resources\System\ExamResource;
use App\Repositories\ExamRepository;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();
//        dd($data);
        $examRep = new ExamRepository();
        try {
            $this->record = $examRep->store($data);
            $this->notify('success', $this->getCreatedNotificationMessage());
            if ($another) {
                // Ensure that the form record is anonymized so that relationships aren't loaded.
                $this->form->model($this->record::class);
                $this->record = null;

                $this->fillForm();

                return;
            }
            $this->redirect($this->getRedirectUrl());
        } catch (\Exception $exception) {
            $this->notify('danger', $exception->getMessage());
        }
    }
}
