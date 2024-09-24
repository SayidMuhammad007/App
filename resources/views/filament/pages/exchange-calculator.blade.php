<x-filament-panels::page>
    <form wire:submit.prevent="calculate">
        {{ $this->form }}

        <x-filament::button type="submit" style="margin-top: 20px;">
            Рассчитать
        </x-filament::button>
    </form>

    <div class="mt-8">
        <h2 class="text-xl font-semibold mb-2">Результаты:</h2>
        <p>Общая сумма: {{ number_format($totalSum, 2) }}</p>
        <p>Разность: {{ number_format($difference, 2) }}</p>
        <p>Сумма залога: {{ number_format($marginSum, 2) }}</p>
        <p>Комиссия биржи: {{ number_format($commission, 2) }}</p>
        <p>Общая залоговая сумма_разность: {{ number_format($totalMarginSum, 2) }}</p>
    </div>
</x-filament-panels::page>