<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Subsource extends Migration
{
     /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subsources', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable()->unsigned()->comment('Source ID');
            $table->foreign('source_id')->references('id')->on('sources')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->integer('sort_no')->default(1);
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
        Schema::dropIfExists('subsources');
    }
}
