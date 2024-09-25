<?php

namespace App\Filament\Pages;

use App\Models\XaridData as ModelsXaridData;
use Filament\Actions\Action as ActionsAction;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\RawJs;
use Illuminate\Support\HtmlString;

class XaridData extends Page implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.xarid-data';

    protected static ?int $navigationSort = 1;

    public $proc_id = null; // Default value

    public function mount()
    {
        $this->proc_id = session('xarid_data_proc_id');
    }

    protected $listeners = ['refreshRelations' => '$refresh'];

    protected function getHeaderActions(): array
    {
        $data = session('result') ?? []; // Default to an empty array if 'result' is not set
        $defaultPrice = is_array($data) && isset($data['price']) ? $data['price'] : '';
        $qty = session('qty', 0); // Default to 0 if 'qty' is not set
        return [
            ActionsAction::make('ExchangeCalculator')
                ->label('Exchange calculator')
                ->icon('heroicon-o-calculator')
                ->form([
                    Forms\Components\Fieldset::make('Exchange Calculator')
                        ->schema([
                            TextInput::make('startSum')
                                ->label('Стартовая сумма товара')
                                ->mask(RawJs::make('$money($input)'))
                                ->stripCharacters(',')
                                ->default($defaultPrice)
                                ->required()
                                ->disabled()
                                ->reactive(),
                            TextInput::make('quantity')
                                ->label('Количество')
                                ->mask(RawJs::make('$money($input)'))
                                ->stripCharacters(',')
                                ->default($qty ?? '')
                                ->required()
                                ->disabled()
                                ->reactive(),
                            TextInput::make('unitPrice')
                                ->label('Моя цена за единицу товара')
                                ->mask(RawJs::make('$money($input)'))
                                ->stripCharacters(',')
                                ->numeric()
                                ->required()
                                ->columnSpanFull()
                                ->reactive(),
                            Placeholder::make('results')
                                ->label('Результаты расчета')
                                ->content(function (Forms\Get $get) {
                                    $startSum = $get('startSum');
                                    $quantity = $get('quantity');
                                    $unitPrice = $get('unitPrice');

                                    if ($startSum && $quantity && $unitPrice) {
                                        return $this->calculateResults($startSum, $quantity, $unitPrice);
                                    }

                                    return 'Заполните все поля для расчета.';
                                })
                        ])
                ])
                ->action(function (array $data) {
                    // This action will be called when the form is submitted
                    // You can add any additional logic here if needed
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
        ];
    }

    private function calculateResults($startSum, $quantity, $unitPrice): HtmlString
    {
        $startSum = (float) str_replace(',', '', $startSum);
        $quantity = (float) str_replace(',', '', $quantity);
        $unitPrice = (float) str_replace(',', '', $unitPrice);

        // Perform calculations
        $totalSum = $quantity * $unitPrice;
        $difference = $startSum - $totalSum;

        if (($difference / $startSum) < 0.2) {
            $marginSum = $difference * 0.03;
        } else {
            $marginSum = ($difference + $startSum) * 0.03;
        }

        $commission = $totalSum * 0.0015;
        $totalMarginSum = $marginSum + $commission;
        $formatMoney = fn($value) => number_format($value, 2);

        $resultHtml = "
    <div class='space-y-2'>
        <div><span class='font-medium'>Общая сумма:</span> {$formatMoney($totalSum, 2)}</div>
        <div><span class='font-medium'>Разность:</span> <span class='" . ($difference >= 0 ? 'text-green-600' : 'text-red-600') . "'>{$formatMoney($difference, 2)}</span></div>
        <div><span class='font-medium'>Сумма залога:</span> <span class='" . ($marginSum >= 0 ? 'text-green-600' : 'text-red-600') . "'>{$formatMoney($marginSum)}</span></div>
        <div><span class='font-medium'>Комиссия биржи:</span> {$formatMoney($commission)}</div>
        <div><span class='font-medium'>Общая залоговая сумма:</span> <span class='" . ($totalMarginSum >= 0 ? 'text-green-600' : 'text-red-600') . "'>{$formatMoney($totalMarginSum)}</span></div>
    </div>";

        return new HtmlString($resultHtml);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('proc_id')
                            ->label('Proc ID')
                            ->required()
                            ->numeric()
                            ->placeholder('Enter Proc ID')
                            ->columnSpanFull(),
                        Actions::make([
                            Action::make('find')
                                ->label('Find')
                                ->action('findProcId')
                                ->button()
                                ->color('primary')
                                ->size('lg')
                        ])
                            ->columnSpanFull()
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->compact()
            ]);
    }

    public function findProcId()
    {
        $data = $this->form->getState();
        $this->proc_id = $data['proc_id'];
        session(['xarid_data_proc_id' => $this->proc_id]);
        redirect('admin/xarid-data');
    }

    public function refreshTable()
    {
        $this->dispatch('refreshTable');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ModelsXaridData::query())
            ->emptyStateDescription('No data found for the given Proc ID.')
            ->columns([
                TextColumn::make('proc_id')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->label('ID'),
                TextColumn::make('created_at')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->wrap()
                    ->label('Дата запроса')
                    ->formatStateUsing(fn($state) => date('d.m.Y H:i', strtotime($state))), // Format date
                TextColumn::make('fields.desc.value')
                    ->label('Наименование')
                    ->extraHeaderAttributes(['style' => 'min-width: 40rem;'])
                    ->wrap()
                    ->grow()
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->html()
                    ->formatStateUsing(fn($state, $record) => '<a href="https://xt-xarid.uz/procedure/' . $record->proc_id . '/core" target="_blank" rel="noreferrer noopener" class="text-primary-600 hover:text-primary-500 underline">' . $state . '</a>'),
                TextColumn::make('fields.amount.value')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->formatStateUsing(function ($state) {
                        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(\p{L}*)/u', $state, $matches)) {
                            $quantity = str_replace(',', '.', $matches[1]); // Replace comma with dot for decimal
                            $quantity = (float)$quantity; // Convert to float
                            session(['qty' => $quantity]); // Store the numeric part in the session
                            $unit = $matches[2] ?: 'шт'; // Default to 'шт' if no unit is specified
                            session(['unit' => $unit]);
                            return number_format($quantity, 0, '.', ' ') . ' ' . $unit;
                        } else {
                            session(['qty' => null]); // Set to null if parsing fails
                            session(['unit' => null]);
                            return $state; // Return original state if parsing fails
                        }
                    })
                    ->label('Объем'),
                TextColumn::make('fields.price.value')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->label('Цена'),
                TextColumn::make('lot_id')
                    ->label('Сумма')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->getStateUsing(function ($record) {
                        $price = $record->price; // Assuming this is a numeric value
                        $amount = preg_replace('/\D/', '', $record->fields['amount']['value']); // Remove non-numeric characters
                        $currency = preg_replace('/[^a-zA-Z]/', '', $record->fields['price']['value']); // Extract currency
                        $amount = (int)$amount;
                        $sum = $price * $amount;
                        return number_format($sum, 2) . ' ' . strtoupper($currency); // Format to two decimal places and append currency
                    }),
                // Add additional columns as necessary
                TextColumn::make('fields.regions.value')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->html()
                    ->label('Регион заявленный в лоте'),
                TextColumn::make('company_data.full_title')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->wrap()
                    ->label('Данные о покупателе1')
                    ->html()
                    ->formatStateUsing(
                        fn($state, $record) => ($record->company_data['full_title'] ?? 'No full title available') . '<br>' .
                            ($record->company_data['title'] ?? 'No title available') . '<br>' .
                            (
                                isset($record->debug['params']['data_sign']['inn'])
                                ? '<a href="https://orginfo.uz/search/all/?q=' . e($record->debug['params']['data_sign']['inn']) . '" target="_blank" rel="noreferrer noopener" class="text-primary-600 hover:text-primary-500 underline">' . e($record->debug['params']['data_sign']['inn']) . '</a>'
                                : 'Нет ИНН'
                            )
                    ),
                TextColumn::make('debug?.params?.data_sign?.meta?.company_name')
                    ->label('Данные о покупателе2')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->wrap()
                    ->html()
                    ->getStateUsing(
                        function ($record) {
                            return ($record->debug?->params?->data_sign?->meta?->company_name ?? 'Нет Компании') . '<br>' .
                                ($record->debug?->params?->data_sign?->meta?->city ?? 'Нет Города');
                        }
                    ),
                TextColumn::make('participants_count')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->label('Конкурентов'),
                TextColumn::make('unique_viewers')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->getStateUsing(function ($record) {
                        if (is_null($record->unique_viewers)) {
                            return 0; // Or any default value you prefer
                        }

                        if (!is_array($record->unique_viewers)) {
                            // If it's a string (e.g., JSON), try to decode it
                            $viewers = json_decode($record->unique_viewers, true);
                            if (is_null($viewers)) {
                                // If JSON decode fails, return the original value or a default
                                return $record->unique_viewers ?: 0;
                            }
                        } else {
                            $viewers = $record->unique_viewers;
                        }

                        $uniqueCount = count(array_unique(array_map('trim', $viewers)));
                        return $uniqueCount;
                    })
                    ->label('👁'),
                TextColumn::make('status')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->label('Статус')
                    ->formatStateUsing(fn($state) => $this->getStatusLabel($state)),
                TextColumn::make('fields.close_at.value')
                    ->verticalAlignment(VerticalAlignment::Start)
                    // ->getStateUsing(fn($record)=>dd($record))
                    ->label('Дата завершения'),
                TextColumn::make('fields.header.hide_value')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->label('Категория')
                    ->wrap()
                    ->formatStateUsing(fn($state, $record) => '<a href="https://xt-xarid.uz/procedure/' . $record->proc_id . '/core" target="_blank" rel="noreferrer noopener" class="text-primary-600 hover:text-primary-500 underline">' . $state . '</a>')
                    ->html(),
                TextColumn::make('procedure')
                    ->label('Выигравший')
                    ->verticalAlignment(VerticalAlignment::Start)
                    ->formatStateUsing(
                        fn($state, $record) =>
                        '<a href="https://xt-xarid.uz/workspace/contract/' . $record->proc_id . '.1.1/core" target="_blank" rel="noreferrer noopener" class="text-primary-600 hover:text-primary-500 underline">Выигравший</a>'
                    )
                    ->html()
            ])
            ->filters([
                //
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('find')
                ->label('Find')
                ->action('findProcId'),
        ];
    }


    protected function getStatusLabel($code)
    {
        $statuses = [
            ["name" => "Открыт", "code" => "open"],
            ["name" => "Закрыт", "code" => "finish"],
            ["name" => "Ожидает пополнения счета со стороны Победителя", "code" => "wait_winner_money"],
            ["name" => "Ожидает одобрения заявки", "code" => "wait_agree"],
            ["name" => "Черновик", "code" => "draft"],
            ["name" => "Отклонен ИСУГФ", "code" => "rejected"],
            ["name" => "Отменен", "code" => "cancel"],
            ["name" => "Торги", "code" => "chaffer"],
            ["name" => "Закрыт с одним участником", "code" => "close_with_one"],
            ["name" => "Закрыт", "code" => "finish"],
            ["name" => "Предложение отклонено", "code" => "reject_offer"],
        ];

        $status = collect($statuses)->firstWhere('code', $code);
        return $status ? $status['name'] : $code; // Return name or code if not found
    }
}
