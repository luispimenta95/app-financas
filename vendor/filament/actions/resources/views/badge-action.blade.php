<x-filament-actions::action
    :action="$action"
    dynamic-component="filament::badge"
    :icon-position="$getIconPosition()"
    :size="$getSize()"
    class="fi-ac-badge-action"
    wire:click="mountAction('{{ $action->getName() }}')" 

    
>
    {{ $getLabel() }}
</x-filament-actions::action>
