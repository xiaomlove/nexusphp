<x-filament::page>
    <form wire:submit.prevent="submit">
        {{ $this->form }}
        <div class="flex justify-center mt-10" style="margin-top: 20px;">
            <button type="submit" class="inline-flex items-center justify-center gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset filament-button h-9 px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700 filament-page-button-action">
                {{__('filament::resources/pages/edit-record.form.actions.save.label')}}
            </button>
        </div>
    </form>
</x-filament::page>
