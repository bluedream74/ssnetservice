<?php 
namespace App\Services;

class MailService { 

  public function lookup($email) {
    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL => "https://email-checker.p.rapidapi.com/verify/v1?email={$email}",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => [
        "x-rapidapi-host: email-checker.p.rapidapi.com",
        "X-RapidAPI-Key: 37efbf6d7fmsh7b68f32f52564c4p172d39jsn5dded705ed25"
      ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      return [false, $err];
    } else {
      // {"email":"info@mediaboxcp.com","user":"info","domain":"mediaboxcp.com","status":"valid","reason":"The email address is valid.","disposable":false}
      return [true, json_decode($response, true)];
    }
  }
} 

?>