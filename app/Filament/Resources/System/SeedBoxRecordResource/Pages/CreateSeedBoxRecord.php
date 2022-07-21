<?php

namespace App\Filament\Resources\System\SeedBoxRecordResource\Pages;

use App\Filament\Resources\System\SeedBoxRecordResource;
use App\Models\SeedBoxRecord;
use App\Repositories\SeedBoxRepository;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSeedBoxRecord extends CreateRecord
{
    protected static string $resource = SeedBoxRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uid'] = auth()->id();
        $data['type'] = SeedBoxRecord::TYPE_ADMIN;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $seedBoxRep = new SeedBoxRepository();
        try {
            return $seedBoxRep->store($data);
        } catch (\Exception $exception) {
            //this wont work...
            $this->notify('danger', $exception->getMessage());
            die();
        }
    }
}
