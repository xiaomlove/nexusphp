<x-filament::page>
    <div class="flex">
        <div class="w-full">
            <table class="table w-full text-left border-spacing-y-2 border-collapse divide-y">
                <tbody>
                    <tr>
                        <th>UID</th>
                        <td>{{$user->id}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Username</th>
                        <td>{{$user->username}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{$user->email}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>{{$user->status}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Enabled</th>
                        <td>{{$user->enabled}}</td>
                        <td><button class="border px-1 rounded">Disable</button></td>
                    </tr>
                    <tr>
                        <th>Added</th>
                        <td>{{$user->added}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Last access</th>
                        <td>{{$user->last_access}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Class</th>
                        <td>{{$user->classText}}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Invite by</th>
                        <td>{{$user->inviter->username ?? ''}}</td>
                        <td><button class="border px-1 rounded">View</button></td>
                    </tr>
                    <tr>
                        <th>Tow-step authentication</th>
                        <td>{{$user->two_step_secret ? 'Enabled' : 'Disabled'}}</td>
                        <td><button class="border px-1 rounded">Disable</button></td>
                    </tr>
                    <tr>
                        <th>Seed points</th>
                        <td>{{$user->seed_points}}</td>
                        <td><button class="border px-1 rounded">Change</button></td>
                    </tr>
                    <tr>
                        <th>Attendance card</th>
                        <td>{{$user->attendance_card}}</td>
                        <td><button class="border px-1 rounded">Change</button></td>
                    </tr>
                    <tr>
                        <th>Invites</th>
                        <td>{{$user->invites}}</td>
                        <td><button class="border px-1 rounded">Change</button></td>
                    </tr>
                    <tr>
                        <th>Uploaded</th>
                        <td>{{$user->uploadedText}}</td>
                        <td><button class="border px-1 rounded">Change</button></td>
                    </tr>
                    <tr>
                        <th>Downloaded</th>
                        <td>{{$user->downloadedText}}</td>
                        <td><button class="border px-1 rounded">Change</button></td>
                    </tr>
                    <tr>
                        <th>Bonus</th>
                        <td>{{$user->seedbonus}}</td>
                        <td><button class="border px-1 rounded">Change</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="w-full">

        </div>

    </div>
</x-filament::page>
