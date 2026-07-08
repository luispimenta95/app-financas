<x-filament-actions::action
    :action="$action"
    :badge="$getBadge()"
    :badge-color="$getBadgeColor()"
    dynamic-component="filament::dropdown.list.item"
    :icon="$getGroupedIcon()"
    class="fi-ac-grouped-action"
    wire:click="mountAction('{{ $action->getName() }}')" 
        wire:click="mountAction('{{ $action->getName() }}')" 


>
    {{ $getLabel() }}
</x-filament-actions::action>
