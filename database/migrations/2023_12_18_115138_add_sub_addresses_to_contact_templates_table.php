<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubAddressesToContactTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contact_templates', function (Blueprint $table) {
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
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
            $table->dropColumn('address1');
            $table->dropColumn('address2');
        });
    }
}
