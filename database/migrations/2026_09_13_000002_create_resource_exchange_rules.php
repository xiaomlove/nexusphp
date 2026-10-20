<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { if(!Schema::hasTable('resource_exchange_rules')) Schema::create('resource_exchange_rules', function(Blueprint $t){$t->id();$t->decimal('min_size_gb',12,3)->default(0);$t->decimal('max_size_gb',12,3)->nullable();$t->unsignedBigInteger('bonus_per_gb_day')->default(0);$t->unsignedBigInteger('tier1_bonus_per_day')->default(0);$t->unsignedBigInteger('tier2_bonus_per_day')->default(0);$t->unsignedBigInteger('tier3_bonus_per_day')->default(0);$t->boolean('enabled')->default(true);$t->timestamps();});}
 public function down(): void { Schema::dropIfExists('resource_exchange_rules'); }
};