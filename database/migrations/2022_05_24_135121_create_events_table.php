<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_panitia')->constrained('panitias');

            $table->string('nama_event');
            $table->integer('harga_tiket');
            $table->date('tanggal_acara');
            $table->string('lokasi_acara');
            $table->string('batasan_waktu');
            $table->string('tipe_acara');
            $table->string('famplet_acara');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('events');
    }
}