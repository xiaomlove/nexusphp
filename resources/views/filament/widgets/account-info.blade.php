<x-filament::widget>
    <x-filament::card>
        @php
            $user = \Filament\Facades\Filament::auth()->user();
        @endphp

        <div class="h-12 flex items-center space-x-4 rtl:space-x-reverse">
            <div>
                <h2 class="text-lg sm:text-xl font-bold tracking-tight">
                    {{ __('filament::widgets/account-widget.welcome', ['user' => \Filament\Facades\Filament::getUserName($user) . '(' . $user->classText . ')']) }}
                </h2>

            </div>
        </div>
    </x-filament::card>
</x-filament::widget>
