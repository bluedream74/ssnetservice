<?php

use Illuminate\Database\Seeder;

class MailTemplateTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('mail_templates')->truncate();
        //
        DB::table('mail_templates')->insert([
            [
                'title' => '会員仮登録完了',
                'subject' => config('app.name') . ' 会員仮登録完了',
                'slug' => 'student.verify',
                'content' => '<p>お客様</p>
<p>メールアドレスを確認するには、次のリンクをクリックしてください。</p>
<p><a href="{url_verify}">{url_verify}</a></p>
<p>このアドレスの確認を依頼していない場合は、このメールを無視してください。</p>
<p>よろしくお願いいたします。</p>
<p>{app_name} 運営事務局です。</p>',
                'memo' => 'Send this email when user register',
            ]
        ]);
    }
}
