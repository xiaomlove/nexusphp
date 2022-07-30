<?php

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Filament\Resources\User\UserResource;
use App\Models\Medal;
use App\Models\User;
use App\Repositories\ExamRepository;
use App\Repositories\MedalRepository;
use App\Repositories\UserRepository;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Pages\Actions;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;

class UserProfile extends Page
{
    use InteractsWithRecord;
    use HasRelationManagers;

    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user.user-resource.pages.user-profile';

    const EVENT_RECORD_UPDATED = 'recordUpdated';

    protected $listeners = [
        self::EVENT_RECORD_UPDATED => 'updateRecord'
    ];

    public function updateRecord($id)
    {
        $this->record = $this->resolveRecord($id);
    }

    public function mount($record)
    {
        static::authorizeResourceAccess();

        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canView($this->getRecord()), 403);

    }

    protected function getActions(): array
    {
        $actions = [];
        if (Auth::user()->class > $this->record->class) {
            $actions[] = $this->buildAssignExamAction();
            $actions[] = $this->buildGrantMedalAction();
            $actions[] = $this->buildChangeBonusEtcAction();
            if ($this->record->two_step_secret) {
                $actions[] = $this->buildDisableTwoStepAuthenticationAction();
            }
            if ($this->record->status == User::STATUS_PENDING) {
                $actions[] = $this->buildConfirmAction();
            }
            $actions[] = $this->buildResetPasswordAction();
            $actions[] = $this->buildEnableDisableAction();
            $actions[] = $this->buildEnableDisableDownloadPrivilegesAction();
        }
        return $actions;
    }

    private function buildEnableDisableAction(): Actions\Action
    {
        return Actions\Action::make($this->record->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_btn') : __('admin.resources.user.actions.enable_modal_btn'))
            ->modalHeading($this->record->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_title') : __('admin.resources.user.actions.enable_modal_title'))
            ->form([
                Forms\Components\TextInput::make('reason')->label(__('admin.resources.user.actions.enable_disable_reason'))->placeholder(__('admin.resources.user.actions.enable_disable_reason_placeholder')),
                Forms\Components\Hidden::make('action')->default($this->record->enabled == 'yes' ? 'disable' : 'enable'),
                Forms\Components\Hidden::make('uid')->default($this->record->id),
            ])
            ->action(function ($data) {
                $userRep = new UserRepository();
                try {
                    if ($data['action'] == 'enable') {
                        $userRep->enableUser(Auth::user(), $data['uid'], $data['reason']);
                    } elseif ($data['action'] == 'disable') {
                        $userRep->disableUser(Auth::user(), $data['uid'], $data['reason']);
                    }
                    $this->notify('success', 'Success!');
                    $this->emitSelf(self::EVENT_RECORD_UPDATED, $data['uid']);
                } catch (\Exception $exception) {
                    $this->notify('danger', $exception->getMessage());
                }
            });
    }

    private function buildDisableTwoStepAuthenticationAction(): Actions\Action
    {
        return Actions\Action::make(__('admin.resources.user.actions.disable_two_step_authentication'))
            ->modalHeading(__('admin.resources.user.actions.disable_two_step_authentication'))
            ->requiresConfirmation()
            ->action(function ($data) {
                $userRep = new UserRepository();
                try {
                    $userRep->removeTwoStepAuthentication(Auth::user(), $this->record->id);
                    $this->notify('success', 'Success!');
                    $this->emitSelf(self::EVENT_RECORD_UPDATED, $this->record->id);
                } catch (\Exception $exception) {
                    $this->notify('danger', $exception->getMessage());
                }
            });
    }

    private function buildChangeBonusEtcAction(): Actions\Action
    {
        return Actions\Action::make(__('admin.resources.user.actions.change_bonus_etc_btn'))
            ->modalHeading(__('admin.resources.user.actions.change_bonus_etc_btn'))
            ->form([
                Forms\Components\Radio::make('field')->options([
                    'uploaded' => __('label.user.uploaded'),
                    'downloaded' => __('label.user.downloaded'),
                    'invites' => __('label.user.invites'),
                    'seedbonus' => __('label.user.seedbonus'),
                    'attendance_card' => __('label.user.attendance_card'),
                ])->label(__('admin.resources.user.actions.change_bonus_etc_field_label'))->inline()->required(),
                Forms\Components\Radio::make('action')->options([
                    'Increment' => __("admin.resources.user.actions.change_bonus_etc_action_increment"),
                    'Decrement' => __("admin.resources.user.actions.change_bonus_etc_action_decrement"),
                ])->label(__('admin.resources.user.actions.change_bonus_etc_action_label'))->inline()->required(),
                Forms\Components\TextInput::make('value')->integer()->required()
                    ->label(__('admin.resources.user.actions.change_bonus_etc_value_label'))
                    ->helperText(__('admin.resources.user.actions.change_bonus_etc_value_help')),
                Forms\Components\Textarea::make('reason')->label(__('admin.resources.user.actions.change_bonus_etc_reason_label')),
            ])
            ->action(function ($data) {
                $userRep = new UserRepository();
                try {
                    $userRep->incrementDecrement(Auth::user(), $this->record->id, $data['action'], $data['field'], $data['value'], $data['reason']);
                    $this->notify('success', 'Success!');
                    $this->emitSelf(self::EVENT_RECORD_UPDATED, $this->record->id);
                } catch (\Exception $exception) {
                    $this->notify('danger', $exception->getMessage());
                }
            });
    }

    private function buildResetPasswordAction()
    {
        return Actions\Action::make(__('admin.resources.user.actions.reset_password_btn'))
            ->modalHeading(__('admin.resources.user.actions.reset_password_btn'))
            ->form([
                Forms\Components\TextInput::make('password')->label(__('admin.resources.user.actions.reset_password_label'))->required(),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label(__('admin.resources.user.actions.reset_password_confirmation_label'))
                    ->same('password')
                    ->required(),
            ])
            ->action(function ($data) {
                $userRep = new UserRepository();
                try {
                    $userRep->resetPassword($this->record->id, $data['password'], $data['password_confirmation']);
                    $this->notify('success', 'Success!');
                    $this->emitSelf(self::EVENT_RECORD_UPDATED, $this->record->id);
                } catch (\Exception $exception) {
                    $this->notify('danger', $exception->getMessage());
                }
            });
    }

    private function buildAssignExamAction()
    {
        return Actions\Action::make(__('admin.resources.user.actions.assign_exam_btn'))
            ->modalHeading(__('admin.resources.user.actions.assign_exam_btn'))
            ->form([
                Forms\Components\Select::make('exam_id')
                    ->options((new ExamRepository())->listMatchExam($this->record->id)->pluck('name', 'id'))
                    ->label(__('admin.resources.user.actions.assign_exam_exam_label'))->required(),
                Forms\Components\DateTimePicker::make('begin')->label(__('admin.resources.user.actions.assign_exam_begin_label')),
                Forms\Components\DateTimePicker::make('end')->label(__('admin.resources.user.actions.assign_exam_end_label'))
                    ->helperText(__('admin.resources.user.actions.assign_exam_end_help')),

            ])
            ->action(function ($data) {
                $examRep = new ExamRepository();
                try {
                    $examRep->assignToUser($this->record->id, $data['exam_id'], $data['begin'], $data['end']);
                    $this->notify('success', 'Success!');
                    $this->emitSelf(self::EVENT_RECORD_UPDATED, $this->record->id);
                } catch (\Exception $exception) {
                    $this->notify('danger', $exception->getMessage());
                }
            });
    }

    private function buildGrantMedalAction()
    {
        return Actions\Action::make(__('admin.resources.user.actions.grant_medal_btn'))
            ->modalHeading(__('admin.resources.user.actions.grant_medal_btn'))
            ->form([
                Forms\Components\Select::make('medal_id')
                    ->options(Medal::query()->pluck('name', 'id'))
                    ->label(__('admin.resources.user.actions.grant_medal_medal_label'))
                    ->required(),

                Forms\Components\TextInput::make('duration')
                    ->label(__('admin.resources.user.actions.grant_medal_duration_label'))
                    ->helperText(__('admin.resources.user.actions.grant_medal_duration_help'))
                    ->integer(),

            ])
            ->action(function ($data) {
                $medalRep = new MedalRepository();
                try {
                    $medalRep->grantToUser($this->record->id, $data['medal_id'], $data['duration']);
                    $this->notify('success', 'Success!');
                    $this->emitSelf(self::EVENT_RECORD_UPDATED, $this->record->id);
                } catch (\Exception $exception) {
                    $this->notify('danger', $exception->getMessage());
                }
            });
    }

    private function buildConfirmAction()
    {
        return Actions\Action::make(__('admin.resources.user.actions.confirm_btn'))
            ->modalHeading(__('admin.resources.user.actions.confirm_btn'))
            ->requiresConfirmation()
            ->action(function () {
                if (Auth::user()->class <= $this->record->class) {
                    $this->notify('danger', 'No permission!');
                    return;
                }
                $this->record->status = User::STATUS_CONFIRMED;
                $this->record->info= null;
                $this->record->save();
                $this->notify('success', 'Success!');
                $this->emitSelf(self::EVENT_RECORD_UPDATED, $this->record->id);
            });
    }


    private function buildEnableDisableDownloadPrivilegesAction(): Actions\Action
    {
        return Actions\Action::make($this->record->downloadpos == 'yes' ? __('admin.resources.user.actions.disable_download_privileges_btn') : __('admin.resources.user.actions.enable_download_privileges_btn'))
//            ->modalHeading($this->record->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_title') : __('admin.resources.user.actions.enable_modal_title'))
            ->requiresConfirmation()
            ->action(function () {
                $userRep = new UserRepository();
                try {
                    $userRep->updateDownloadPrivileges(Auth::user(), $this->record->id, $this->record->downloadpos == 'yes' ? 'no' : 'yes');
                    $this->notify('success', 'Success!');
                    $this->emitSelf(self::EVENT_RECORD_UPDATED, $this->record->id);
                } catch (\Exception $exception) {
                    $this->notify('danger', $exception->getMessage());
                }
            });
    }
}
