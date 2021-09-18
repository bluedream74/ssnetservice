<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('surname',30);
            $table->string('lastname',30);
            $table->string('fu_surname',30)->nullable();
            $table->string('fu_lastname',30)->nullable();
            $table->string('company',30);
            $table->string('email',30);
            $table->string('title', 1000)->nullable();
            $table->text('content')->nullable();
            $table->string('postalCode1',20)->nullable();
            $table->string('postalCode2',20)->nullable();
            $table->string('address',50)->nullable();
            $table->integer('phoneNumber1')->nullable();
            $table->integer('phoneNumber2')->nullable();
            $table->integer('phoneNumber3')->nullable();
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
        Schema::dropIfExists('contacts');
    }
}
