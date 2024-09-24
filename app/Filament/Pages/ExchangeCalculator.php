<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Support\RawJs;

class ExchangeCalculator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static string $view = 'filament.pages.exchange-calculator';

    public $startSum = 0;
    public $quantity = 0;
    public $unitPrice = 0;
    public $totalSum = 0;
    public $difference = 0;
    public $marginSum = 0;
    public $commission = 0;
    public $totalMarginSum = 0;

    public function mount(): void
    {
        $this->form->fill();
    }
    public function form(Form $form): Form
    {
        // Retrieve session data safely
        $data = session('result') ?? []; // Default to an empty array if 'result' is not set
        $qty = session('qty', 0); // Default to 0 if 'qty' is not set

        // Check if $data is an array and has the 'price' key
        $defaultPrice = is_array($data) && isset($data['price']) ? $data['price'] : '';

        return $form
            ->schema([
                TextInput::make('startSum')
                    ->label('Стартовая сумма товара')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(',')
                    ->default($defaultPrice)
                    ->required(),
                TextInput::make('quantity')
                    ->label('Количество')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(',')
                    ->default($qty ?? '') // Default to empty string if qty is not set
                    ->required(),
                TextInput::make('unitPrice')
                    ->label('Моя цена за единицу товара')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(',')
                    ->numeric()
                    ->required(),
            ]);
    }



    public function calculate(): void
    {
        $this->validate();

        $startSum = (float) str_replace(',', '', $this->startSum);
        $quantity = (float) str_replace(',', '', $this->quantity);
        $unitPrice = (float) str_replace(',', '', $this->unitPrice);

        // Perform calculations
        $this->totalSum = $quantity * $unitPrice;
        $this->difference = $startSum - $this->totalSum;

        if (($this->difference / $startSum) < 0.2) {
            $this->marginSum = $this->difference * 0.03;
        } else {
            $this->marginSum = ($this->difference + $startSum) * 0.03;
        }

        $this->commission = $this->totalSum * 0.0015;
        $this->totalMarginSum = $this->marginSum + $this->commission;
    }

    public function getTitle(): string
    {
        return "Биржевой калькулятор ";
    }
}
