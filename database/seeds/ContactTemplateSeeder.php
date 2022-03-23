<?php

use App\Models\ContactTemplate;
use Illuminate\Database\Seeder;


class ContactTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ContactTemplate::updateOrCreate(
            [
                'id' => 1
            ],
            [
                'title' => "タイトル",
                'surname' => "名前",
                'lastname' => "名前",
                'fu_surname' => "フリガナ",
                'fu_lastname' => "フリガナ",
                'company' => "会社名",
                'email' => "trashh67@gmail.com",
                'myurl' => "https://www.google.com/",
                'content' => "会社名",
                'homepageUrl' => "https://www.google.com/",
                'area' => "会社名",
                'attachment' => null,
                'postalCode1' => "100",
                'postalCode2' => "0000",
                'address' => "会社名",
                'phoneNumber1' => "09",
                'phoneNumber2' => "4564",
                'phoneNumber3' => "4564",
                'date' => null,
                'time' => null,
            ]
        );
    }
}
