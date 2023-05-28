<x-filament::page>
    <div class="flex">
        <table class="table w-full text-left border-spacing-y-2 border-collapse divide-y w-full">
            <tbody>
            <tr>
                <th>UID</th>
                <td>{{$record->id}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.user.username')}}</th>
                <td>{!! get_username($record->id, false, true, true, true) !!}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.email')}}</th>
                <td>{{$record->email}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.status')}}</th>
                <td>{{$record->status}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.enabled')}}</th>
                <td>{{$record->enabled}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.added')}}</th>
                <td>{{$record->added}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.last_access')}}</th>
                <td>{{$record->last_access}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.user.class')}}</th>
                <td>{{$record->classText}}</td>
                <td></td>
            </tr>
            @if($props)
                <tr>
                    <th>{{__('user.labels.props')}}</th>
                    <td><div style="display: flex">{!! implode('&nbsp;|&nbsp;', $props) !!}</div></td>
                    <td></td>
                </tr>
            @endif
            {!! do_action('user_detail_rows', $record->id, 'admin') !!}
            <tr>
                <th>{{__('label.user.invite_by')}}</th>
                <td>{{$record->inviter->username ?? ''}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.user.two_step_authentication')}}</th>
                <td>{{$record->two_step_secret ? 'Enabled' : 'Disabled'}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.user.downloadpos')}}</th>
                <td>{{$record->downloadpos}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.user.parked')}}</th>
                <td>{{$record->parked}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.user.offer_allowed_count')}}</th>
                <td>{{$record->offer_allowed_count}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{__('label.user.seed_points')}}</th>
                <td>{{$record->seed_points}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{ __('label.user.attendance_card') }}</th>
                <td>{{$record->attendance_card}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{ __('label.user.invites') }}</th>
                <td>{{sprintf('%s(%s)', $record->invites, $temporary_invite_count)}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{ __('label.uploaded') }}</th>
                <td>{{$record->uploadedText}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{ __('label.downloaded') }}</th>
                <td>{{$record->downloadedText}}</td>
                <td></td>
            </tr>
            <tr>
                <th>{{ __('label.user.seedbonus') }}</th>
                <td>{{$record->seedbonus}}</td>
                <td></td>
            </tr>
            </tbody>
        </table>
    </div>

    @if (count($relationManagers = $this->getRelationManagers()))
        <x-filament::hr />

        <x-filament::resources.relation-managers :active-manager="$activeRelationManager" :managers="$relationManagers" :owner-record="$record" />
    @endif
</x-filament::page>
