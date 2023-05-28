<x-filament::widget class="filament-widgets-table-widget">
    <div class="p-2 space-y-2 bg-white rounded-xl shadow">
        <div class="space-y-2">
            <div class="px-4 py-2 space-y-4">
                <div class="flex items-center justify-between gap-8">
                    <h2 class="text-xl font-semibold tracking-tight filament-card-heading">
                        {{$header}}
                    </h2>
                </div>
                <div aria-hidden="true" class="border-t filament-hr"></div>
                <div class="overflow-y-auto relative">
                    <table class="w-full text-left rtl:text-right divide-y table-auto filament-tables-table">
                        <tbody class="divide-y whitespace-nowrap">
                            @foreach(array_chunk($data, 2) as $chunk)
                            <tr class="filament-tables-row">
                                @foreach($chunk as $item)
                                <th class="filament-tables-cell"><div class="px-4 py-3 filament-tables-text-column">{{$item['text']}}</div></th>
                                <td class="filament-tables-cell"
                                @if($loop->count == 1)
                                    colspan="3"
                                @endif
                                >
                                    <div class="px-4 py-3 filament-tables-text-column">{{$item['value']}}</div>
                                </td>
                                @endforeach
                            </tr>
                            @endforeach

                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
</x-filament::widget>
