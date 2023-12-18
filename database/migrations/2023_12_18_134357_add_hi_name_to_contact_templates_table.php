<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHiNameToContactTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contact_templates', function (Blueprint $table) {
            $table->string('hi_surname')->nullable();
            $table->string('hi_lastname')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contact_templates', function (Blueprint $table) {
            $table->dropColumn('hi_surname');
            $table->dropColumn('hi_lastname');
        });
    }
}
