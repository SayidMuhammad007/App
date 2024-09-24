<?php

namespace App\Filament\Pages;

use App\Models\XaridData as ModelsXaridData;
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
use Filament\Forms\Components\Actions;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;

class XaridData extends Page implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.xarid-data';

    public $proc_id = null; // Default value

    public function mount()
    {
        $this->proc_id = session('xarid_data_proc_id');
    }

    protected $listeners = ['refreshRelations' => '$refresh'];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('proc_id')
                            ->label('Proc ID')
                            ->required()
                            ->placeholder('Enter Proc ID')
                            ->maxWidth('md')
                            ->columnSpanFull(),
                        Actions::make([
                            Action::make('find')
                                ->label('Find')
                                ->action('findProcId')
                                ->button()
                                ->color('primary')
                                ->size('md')
                        ])
                            // ->alignment(Alignment::Right)
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
                    ->label('ID'),
                TextColumn::make('created_at')
                    ->label('Дата запроса')
                    ->formatStateUsing(fn($state) => date('d.m.Y H:i', strtotime($state))), // Format date
                // TextColumn::make('fields.desc.value')
                //     ->label('Наименование')
                //     ->verticalAlignment(VerticalAlignment::Start)
                //     ->size(TextColumnSize::Small)
                //     ->grow()
                //     ->wrap()
                //     ->width('lg')
                //     ->formatStateUsing(fn($state, $record) => '<a href="https://xt-xarid.uz/procedure/' . $record->proc_id . '/core" target="_blank" rel="noreferrer noopener">' . $state . '</a>')
                //     ->html(),
                TextColumn::make('fields.amount.value')
                    ->label('Объем'),
                TextColumn::make('fields.price.value')
                    ->label('Цена'),
                TextColumn::make('lot_id')
                    ->label('Сумма')
                    ->getStateUsing(function ($record) {
                        // Retrieve values from the record
                        $price = $record->price; // Assuming this is a numeric value
                        $amount = preg_replace('/\D/', '', $record->fields['amount']['value']); // Remove non-numeric characters
                        $currency = preg_replace('/[^a-zA-Z]/', '', $record->fields['price']['value']); // Extract currency

                        // Convert amount to an integer (if needed)
                        $amount = (int)$amount;

                        // Calculate the total sum
                        $sum = $price * $amount;

                        // Return the formatted result
                        return number_format($sum, 2) . ' ' . strtoupper($currency); // Format to two decimal places and append currency
                    }),
                // Add additional columns as necessary
                TextColumn::make('fields.close_at.value')
                    ->label('Дата завершения'),
                TextColumn::make('fields.regions.value')
                    ->html()
                    ->label('Регион заявленный в лоте'),
                TextColumn::make('company_data.full_title')
                    ->wrap()
                    ->label('Данные о покупателе1')
                    ->html()
                    ->formatStateUsing(
                        fn($state, $record) => ($record->company_data['full_title'] ?? '') . '<br>' .
                            ($record->company_data['title'] ?? '') . '<br>' .
                            ($record->debug['params']['data_sign']['inn'] ?? 'Нет ИНН')
                    ),
                TextColumn::make('debug?.params?.data_sign?.meta?.company_name')
                    ->label('Данные о покупателе2')
                    ->wrap()
                    ->html()
                    ->getStateUsing(
                        function ($record) {
                            return ($record->debug?->params?->data_sign?->meta?->company_name ?? 'Нет Компании') . '<br>' .
                                ($record->debug?->params?->data_sign?->meta?->city ?? 'Нет Города');
                        }
                    ),
                TextColumn::make('participants_count')
                    ->label('Конкурентов'),
                TextColumn::make('unique_viewers')
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
                    ->label('Статус')
                    ->formatStateUsing(fn($state) => $this->getStatusLabel($state)),
                TextColumn::make('fields.header.hide_value')
                    ->label('Категория')
                    ->wrap()
                    ->formatStateUsing(fn($state, $record) => '<a href="https://xt-xarid.uz/procedure/' . $record->proc_id . '/core" target="_blank" rel="noreferrer noopener">' . $state . '</a>')
                    ->html(),
                TextColumn::make('agree')
                    ->label('Выигравший')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        '<a href="https://xt-xarid.uz/workspace/contract/' . $record->proc_id . '.1.1/core" target="_blank" rel="noreferrer noopener">Выигравший</a>'
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
