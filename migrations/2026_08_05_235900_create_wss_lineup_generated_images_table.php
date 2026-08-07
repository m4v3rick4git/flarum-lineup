<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'wss_lineup_generated_images',
    function (Blueprint $table): void {
        $table->increments('id');

        $table->unsignedInteger('actor_id');

        $table
            ->unsignedInteger('post_id')
            ->nullable();

        $table
            ->string('filename', 64)
            ->unique();

        $table->string('relative_path', 255);

        $table
            ->string('status', 20)
            ->default('pending');

        $table
            ->unsignedInteger('size_bytes')
            ->nullable();

        $table->dateTime('expires_at');

        $table
            ->dateTime('claimed_at')
            ->nullable();

        $table->timestamps();

        $table->index(
            ['actor_id', 'created_at'],
            'wss_lineup_images_actor_created_index'
        );

        $table->index(
            ['status', 'expires_at'],
            'wss_lineup_images_status_expiry_index'
        );

        $table->index(
            'post_id',
            'wss_lineup_images_post_index'
        );
    }
);
