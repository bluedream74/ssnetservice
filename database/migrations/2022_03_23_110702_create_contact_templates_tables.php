<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactTemplatesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contact_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_title', 1000)->nullable();
            $table->string('surname',30);
            $table->string('lastname',30);
            $table->string('fu_surname',30)->nullable();
            $table->string('fu_lastname',30)->nullable();
            $table->string('company',50);
            $table->string('email',50);
            $table->string('title', 1000)->nullable();
            $table->string('myurl')->nullable();
            $table->text('content')->nullable();
            $table->string('homepageUrl')->nullable();
            $table->string('area')->nullable();
            $table->string('postalCode1',20)->nullable();
            $table->string('postalCode2',20)->nullable();
            $table->string('address',50)->nullable();
            $table->string('phoneNumber1')->nullable();
            $table->string('phoneNumber2')->nullable();
            $table->string('phoneNumber3')->nullable();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->string('attachment')->nullable();
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
        Schema::dropIfExists('contact_templates');
    }
}
