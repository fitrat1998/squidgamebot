<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Masalan: kino_101
            $table->text('file_name'); // Kino havolasi
            $table->text('file_id'); // Kino havolasi
            $table->text('file_size'); // Kino havolasi
            $table->text('mime_type'); // Kino havolasi
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
