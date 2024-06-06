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
        $examRep = new ExamRepository();
        try {
            $this->record = $examRep->store($data);
            $this->notify('success', $this->getCreatedNotificationTitle());
            if ($another) {
                // Ensure that the form record is anonymized so that relationships aren't loaded.
                $this->form->model($this->record::class);
                $this->record = null;

                $this->fillForm();

                return;
            }
            $this->redirect($this->getResource()::getUrl('index'));
        } catch (\Exception $exception) {
            do_log($exception->getMessage() . "\n" . $exception->getTraceAsString(), "error");
            $this->notify('danger', $exception->getMessage());
        }
    }

}
