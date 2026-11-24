<?php

use App\Models\Transactions\Transaction;
use App\Models\User;
use App\Support\PrivateAttachments;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('anexo privado exige login e ownership', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = $owner->accounts()->firstOrFail();
    $category = $owner->categories()->firstOrFail();

    $path = 'transactions/privado.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 secret');

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_type' => \App\Enums\TransactionType::Expense,
        'amount' => 1000,
        'description' => 'Com anexo',
        'finished' => false,
        'recurrence' => false,
        'date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'attachment' => $path,
    ]);

    $this->get(route('attachments.show', ['path' => $path]))
        ->assertRedirect(financas_panel_url('login'));

    $this->actingAs($other)
        ->get(route('attachments.show', ['path' => $path]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->get(route('attachments.show', ['path' => $path]))
        ->assertOk();
});

test('caminho com traversal é rejeitado', function () {
    $user = User::factory()->create();

    expect(PrivateAttachments::normalize('../.env'))->toBeNull()
        ->and(PrivateAttachments::normalize('users/avatar.jpg'))->toBeNull();

    $this->actingAs($user)
        ->get(route('attachments.show', ['path' => 'boletos/../../.env']))
        ->assertNotFound();
});
