<x-filament::page :widget-data="['record' => $record]" class="filament-resources-view-record-page">
    <div class="flex flex-col md:flex-row justify-between">
        <table class="table text-left border-spacing-y-2 border-collapse divide-y w-full">
            <tbody>
            @foreach($cardData as $value)
                <tr class="">
                    <th class="border-spacing-3">{{ $value['label'] }}</th>
                    <td class="border-spacing-3">{!! $value['value'] !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <table class="table text-left border-spacing-y-2 border-collapse divide-y w-full">
            <thead>
            <tr>
                <th>{{ __('label.exam.index_required_label') }}</th>
                <th>{{ __('label.exam.index_required_value') }}</th>
                <th>{{ __('label.exam.index_current_value') }}</th>
                <th>{{ __('label.exam.index_result') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($record->progressFormatted as $index)
            <tr>
                <td>{{ $index['index_formatted'] }}</td>
                <td>{{ $index['require_value_formatted'] }}</td>
                <td>{{ $index['current_value_formatted'] }}</td>
                <td>{{ $index['passed'] ? __('admin.resources.exam_user.result_passed') : __('admin.resources.exam_user.result_not_passed') }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if (count($relationManagers = $this->getRelationManagers()))
        <x-filament::hr />

        <x-filament::resources.relation-managers :active-manager="$activeRelationManager" :managers="$relationManagers" :owner-record="$record" />
    @endif
</x-filament::page>
