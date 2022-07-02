<x-filament::page :widget-data="['record' => $record]" class="filament-resources-view-record-page">
    <div class="">
        <table class="table table-fixed text-left border-spacing-y-2 border-collapse divide-y w-full">
            <tbody>
            @foreach($cardData as $value)
                <tr class="">
                    <th class="border-spacing-3">{{ $value['label'] }}</th>
                    <td class="border-spacing-3">{!! $value['value'] !!}</td>
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
