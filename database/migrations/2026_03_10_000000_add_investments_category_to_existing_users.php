<?php

use App\Models\Transactions\Category;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        User::query()
            ->with(['categories' => fn ($query) => $query->withTrashed()])
            ->each(function (User $user): void {
                $category = $user->categories()
                    ->withTrashed()
                    ->where('slug', Category::INVESTMENTS_SLUG)
                    ->first();

                if ($category) {
                    if ($category->trashed()) {
                        $category->restore();
                    }

                    $category->update([
                        'name' => Category::INVESTMENTS_NAME,
                        'icon' => 'fas-chart-line',
                        'color' => '#0f766e',
                    ]);

                    return;
                }

                $user->categories()->create([
                    'name' => Category::INVESTMENTS_NAME,
                    'slug' => Category::INVESTMENTS_SLUG,
                    'icon' => 'fas-chart-line',
                    'color' => '#0f766e',
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        User::query()
            ->with(['categories' => fn ($query) => $query->withTrashed()])
            ->each(function (User $user): void {
                $user->categories()
                    ->withTrashed()
                    ->where('slug', Category::INVESTMENTS_SLUG)
                    ->forceDelete();
            });
    }
};
