<x-filament::page>
    <div class="flex">
        <div class="w-full">
            <table class="table w-full text-left border-spacing-y-2 border-collapse divide-y">
                <tbody>
                    <tr>
                        <th>UID</th>
                        <td>{{$record->id}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Username</th>
                        <td>{{$record->username}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{$record->email}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>{{$record->status}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Enabled</th>
                        <td>{{$record->enabled}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Added</th>
                        <td>{{$record->added}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Last access</th>
                        <td>{{$record->last_access}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Class</th>
                        <td>{{$record->classText}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Invite by</th>
                        <td>{{$record->inviter->username ?? ''}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Tow-step authentication</th>
                        <td>{{$record->two_step_secret ? 'Enabled' : 'Disabled'}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Seed points</th>
                        <td>{{$record->seed_points}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Attendance card</th>
                        <td>{{$record->attendance_card}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Invites</th>
                        <td>{{$record->invites}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Uploaded</th>
                        <td>{{$record->uploadedText}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Downloaded</th>
                        <td>{{$record->downloadedText}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Bonus</th>
                        <td>{{$record->seedbonus}}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="w-full">

        </div>

    </div>

    @if (count($relationManagers = $this->getRelationManagers()))
        <x-filament::hr />

        <x-filament::resources.relation-managers :active-manager="$activeRelationManager" :managers="$relationManagers" :owner-record="$record" />
    @endif
</x-filament::page>
