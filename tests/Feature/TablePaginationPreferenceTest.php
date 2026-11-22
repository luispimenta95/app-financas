<?php

use App\Models\User;
use App\Support\TablePagination;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

test('opções de paginação são 50, 100 e 200 com padrão 50', function () {
    expect(TablePagination::OPTIONS)->toBe([50, 100, 200])
        ->and(TablePagination::DEFAULT)->toBe(50)
        ->and(TablePagination::normalize(999))->toBe(50)
        ->and(TablePagination::normalize(100))->toBe(100)
        ->and(TablePagination::for(null))->toBe(50);
});

test('usuário usa o valor salvo e rejeita valores inválidos', function () {
    $user = User::factory()->create(['table_records_per_page' => 200]);

    expect($user->tableRecordsPerPage())->toBe(200)
        ->and(TablePagination::for($user))->toBe(200);

    $user->forceFill(['table_records_per_page' => 15])->save();

    expect($user->fresh()->tableRecordsPerPage())->toBe(50);
});

test('migration adiciona coluna table_records_per_page com default 50', function () {
    expect(Schema::hasColumn('users', 'table_records_per_page'))->toBeTrue();

    $user = User::factory()->create();

    expect($user->table_records_per_page)->toBe(50);
});

test('perfil permite salvar preferência de paginação para todas as tabelas', function () {
    $user = User::factory()->create(['table_records_per_page' => 50]);

    $this->actingAs($user);

    livewire(\App\Filament\Pages\Auth\EditProfile::class)
        ->fillForm([
            'name' => $user->name,
            'email' => $user->email,
            'table_records_per_page' => 100,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->table_records_per_page)->toBe(100)
        ->and($user->fresh()->tableRecordsPerPage())->toBe(100);
});

test('hook sincroniza mudança de por página da tabela no usuário', function () {
    $user = User::factory()->create(['table_records_per_page' => 50]);
    $this->actingAs($user);

    $hook = new \App\Livewire\Hooks\SyncTableRecordsPerPage;
    $hook->setComponent(new class
    {
        public int $tableRecordsPerPage = 200;
    });
    $hook->update('tableRecordsPerPage', 'tableRecordsPerPage', 200);

    expect($user->fresh()->table_records_per_page)->toBe(200);
});
