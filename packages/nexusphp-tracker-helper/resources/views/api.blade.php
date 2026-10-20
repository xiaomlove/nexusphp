<x-filament-panels::page>

    <form wire:submit.prevent="submitRequest" class="fi-sc-form">

        {{ $this->form }}

        <div class="mt-6">
            {{ $this->getFormActions()[0] }}
        </div>
    </form>

    <div class="mt-8">

        <div wire:loading wire:target="submitRequest">
            <div class="flex items-center space-x-2 text-gray-500">
                <x-filament::loading-indicator class="h-5 w-5" />
                <span>Loading ...</span>
            </div>
        </div>

        <div wire:loading.remove wire:target="submitRequest">

            @if ($errorMessage)
                <div class="p-4 mb-4 bg-danger-100 dark:bg-danger-500/20 text-danger-600 dark:text-danger-400 rounded-lg">
                    <p>{{ $errorMessage }}</p>
                </div>
            @endif

            @if ($apiResponse !== null)
                <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white mb-4" style="margin-bottom: 15px">
                    Response (HTTP Status Code: {{ $statusCode }})
                </h2>

                <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg shadow-inner" style="font-size: small;max-height: 50vh;overflow-y: scroll">
                        @php
                            if (is_array($apiResponse) || is_object($apiResponse)) {
                                echo sprintf('<pre class="overflow-x-auto text-sm">%s</pre>', json_encode($apiResponse, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
                            } else {
                                echo sprintf('<pre class="overflow-x-auto text-sm">%s</pre>', htmlspecialchars($apiResponse));
                            }
                        @endphp
                </div>
            @endif
        </div>
    </div>

</x-filament-panels::page>
