<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(false);
            $table->unsignedTinyInteger('rollout_percentage')->nullable()->comment('Percentage of users (0–100) who should see this feature');
            $table->timestamp('enabled_from')->nullable()->comment('Feature becomes active at this UTC timestamp');
            $table->timestamp('enabled_until')->nullable()->comment('Feature deactivates after this UTC timestamp');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
