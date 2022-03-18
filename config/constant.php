<?php

return [
    'prioritizedMappers' => [
        'phoneNumber1' => [
            'tel_no_1', 'tel-num[data][0]', 'fax-num[data][0]', 'tkph971-1', 'fax[data][0]',
            '電話番号[data][0]', 'tel1', 'TEL1', 'tel1', 'PHONE1', 'field_93664_1',
            'item_13_phone1', 'e_29[tel1]', 'e_30[tel1]', 'tel_1', 'telphone:a', 'contact_tel_tel01',
            'TEL1_need', 'items[tel01]',
        ],

        'phoneNumber2' => ['tel_no_1', 'tel-num[data][1]', 'fax-num[data][1]', 'fax[data][1]', 'tel2', 'tel3', 'TEL2',
            'TEL3', 'tel2', 'tel3', 'PHONE2', 'PHONE3', 'field_93664_2', 'field_93664_3', 'item_13_phone2',
            'item_13_phone3', 'c_q28_subscribercode', 'e_29[tel2]', 'e_29[tel3]', 'e_30[tel2]', 'e_30[tel3]', 'tel_2', 'tel_3',
            'InquiryFront[phone2]', 'InquiryFront[phone3]', 'telphone:e', 'telphone:n', 'contact_tel_tel02', 'contact_tel_tel03',
            'TEL2_need', 'TEL3_need', 'items[tel02]', 'お電話番号[data][1]',
        ],

        'phoneNumber3' => ['items[tel02]', 'お電話番号[data][2]'],

        'postalCode1' => [
            'j_zip_code_1', 'formElementVal124Zip1', 'address-1257114-zip1', '郵便番号1', 'zip1',
            'zip-code', '郵便番号01', 'smauto_prcsr_company_addr_zip', 'contact[zip][zip01]', 'zip',
            'items[pc01]',
        ],

        'postalCode2' => [
            'formElementVal124Zip2', 'address-1257114-zip2',
            '郵便番号2', 'zip-code-4', '郵便番号02', 'zipCode:t', 'items[pc02]', '郵便番号[data][1]',
        ],

        'fullPhoneNumber1' => ['txtTEL', 'TEL', 'tel', '電話番号(必須)'],

        'fullPhoneNumber2' => ['your-tel', 'efo-form01-tel', 'data[NomuraInquiry][tel]', 'dataTelephone', 'input[tel]', 'tel', 'fFax'],

        'fullPostCode1' => ['txtZipCode', 'postalCode', 'efo-form01-apa-zip', 'efo-form01-zip', 'data[NomuraInquiry][zip]', 'postal_code', 'zipcode', 'zip01', 'yubin'],

        'email' => [
            'mailConfirm', 'mail_address_conf', 'e_854_re', 'InquiryFront[email]', 'email_confirm',
            'data[FormItem][65][3][34][val][confirm]', 'confirm_email(必須)', 'email_conf2', 'mail_confirm',
            'email-confirm', 'メールアドレス', 'メールアドレス（確認）', 'Email',
        ],

        'address' => ['丁目番地', 'InquiryFront[address01]', 'InquiryFront[address02]'],

        'fu_surname' => ['item_11_name1'],

        'fu_lastname' => ['item_11_name2'],

        'randomNumber' => ['c_q29'],

        'furigana' => ['お名前フリガナ', '会社名フリガナ', 'companyname-furigana'],
    ],

    'mapper' => [
        'furiganaMatch' => ['company-kana', 'company_furi', 'フリガナ', 'kcn', 'ふりがな',
            'singleAnswer(ANSWER3-1)', 'singleAnswer(ANSWER3-2)',
            'department', 'f000003200', 'f000003202', 'f000003194',
            'ext_04', 'kana', 'フリガナ(必須)',
            'ReqKind',  'cde_Gst_Furigana', 'NAME_F', 'kana_name_sei',
            'f000027212', 'f000027213', 'singleAnswer(ANSWER3402)', 'qEnq5464', 'qEnq5465',
            'e_26', 'input9', 'busyo',
            'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID10$fldValue$txtSingleTextBox',
            'aform-field-187', 'furi1', 'furi2', 'f000224117', 'f000224108', 'RequestForm$Attr-2-2', 'RequestForm$Attr-2-4',
            'txtName', 'senddata2', 'fName', 'data2', 'name_kana', 'vl022', 'f000667749', 'items[ruby]',
        ],

        'furiganaPattern' => ['氏名（カナ）', 'フリガナ'],

        'companyMatch' => ['company', 'cn', 'kaisha', 'cop', 'corp', '会社', '社名', 'タイトル',
            'txtCompanyName', 'f000003193', 'singleAnswer(ANSWER3405)', 'singleAnswer(ANSWER3406)',
            'company', 'cn', 'kaisha', 'cop', 'corp', '会社', '社名', 'タイトル', 'fCompany', 'UserCompanyName', 'en1244884030',
            'item_maker', 'organization', 'f000104023', 'txtCompany', 'txtDepart', 'section', 'product', 'dataCompany',
            'e2', 'RequestForm$Attr-2-1', 'RequestForm$Attr-2-3', 'singleAnswer(ANSWER162)', 'singleAnswer(ANSWER262)',
            'txtCompName', 'txtPostName', 'ご担当者様', '所属部署', 'company_name', 'department_name', 'position', '物件番号',
        ],

        'companyPattern' => ['会社名', '企業名', '貴社名', '御社名', '法人名', '団体名', '機関名',
            '屋号', '組織名', 'お店の名前', '社名', '店舗名', '職種',
            '会社名', '機関名', 'お名前 フリガナ (全角カナ)',
        ],

        'emailMatch' => ['mail_add', 'mail', 'Mail', 'mail_confirm', 'ールアドレス', 'M_ADR', '部署',
            'E-Mail', 'メールアドレス', 'Email', 'email', 'f000026560', '03.E-メール', '03.E-メール2',
            'mail_address_confirm', 'qEnq5463', 'f000003203', 'f000003203:cf',
            'singleAnswer(ANSWER4)', 'mail_add', 'mail', 'Mail', 'mail_confirm',
            'ールアドレス', 'M_ADR', '部署',
            'singleAnswer(ANSWER4-R)', 'c_q18_confirm',
            'mailaddress', 'i_email', 'i_email_check', 'email(必須)', 'confirm_email(必須)',
            'c_q8', 'c_q8_confirm', 'f000027220', 'f000027221', 'en1262055277_match', 'f012956240',
            'input30', 'your-email', 're_mail', 'e_2274_re', 'mailaddress_confirm', 'query[3]',
            'EMAIL', 'EMAIL2', 'email_confirm', 'INQ_MAIL_ADDR_CONF', 'mail_address2', 'mailConfirm',
            'f000104021', 'f000104021:cf', 'MAIL_CONF', 'RE_MAILADDRESS', 'RequestForm$Attr-5-2',
            'singleAnswer(ANSWER163)', 'item_12', 'c_q37_confirm', 'c_q25_confirm', 'e_28', 'senddata16',
            'f000667756:cf', 'your-email', 'E-mail_need', 'E-mail2_need', 'email(必須)', 'email1', 'email2', 'E-MAIL', 'your_email_validate', 'mail2', 'avia_6_1',
        ],

        'emailPattern' => ['メールアドレス', 'メールアドレス(確認用)', 'Mail アドレス', 'E-mail (半角)', 'ペライチに登録しているメールアドレス', 'メールアドレス［確認］
        （E-mail）', 'メールアドレス（確認用）', 'メールアドレス（確認）'],

        'emailKey' => ['singleAnswer(ANSWER4)', 'singleAnswer(ANSWER4-R)', 'mailaddress', 'mailaddress2', 'email', 'f012956240:cf', 'f000224114', 'f000224114:cf'],

        'postalCode1Match' => [
            'addressnum', 'zip', 'zipcode1',
            'f000026563:a', 'txt_zipcode[]', 'zip-code', 'ZIP1', 'zipcode[data][0]',
            'f013017420:a', 'txtZip1',
        ],

        'postalCode1Key' => ['ZipcodeL', 'j_zip_code_1', 'f000003518:a', 'item_14_zip1', 'C019_LAST'],

        'fullPostcode1Match' => ['郵便番号', 'zipcode', 'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID15$fldValue$txtSingleTextBox',
            'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID20$fldValue$txtSingleTextBox', ],

        'fullPostcode1Key' => ['zipcode', 'postal'],

        'postCode2Match' => ['field_2437489_2', 'f000003518:t',
            'zip[data][1]', 'item_14_zip2', 'c_q10_right',
            'zip2', 'j_zip_code_2', 'c_q3_right', 'f000026563:t', 'txt_zipcode[]',
            'zip-code-4', 'ZIP2', 'field_2437489_3', 'zipcode[data][1]', 'f013017420:t', 'zip02',
            'ZIPCODE2_HOME', 'c_q31_right', 'txtZip2', 'smauto_prcsr_sender_addr_zip', 'senddata5',
        ],

        'postCode2Key' => ['zip1', '郵便番号(必須)', 'C019_FIRST'],

        'fullPostCode2Match' => ['fZipCode', 'efo-form01-apa-zip', '郵便番号', 'addressnum', 'postal-code',
            'en1240790938', 'input34', 'RequestForm$Attr-4-1', 'data3',
        ],

        'fullPostCode2Pattern' => ['郵便番号', '〒', '郵便番号 (半角数字のみ)'],

        'addressMatch' => ['住所', 'addr', 'add_detail', 'town', 'f000003520', 'f000003521', 'add2',
            'c_q21', 'block', 'ext_08', 'fCity', 'fBuilding', 'efo-form01-district',
            '住所', 'addr', 'item117', 'UserAddress', '番地', '建物名・施設名',
            'f000027223', 'f000027225', 'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID17$fldValue$txtSingleTextBox',
            'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID18$fldValue$txtSingleTextBox', 'query[10]',
            'ADDR_2', 'ADDR_3', 'building', 'f000224118', 'add_01', 'add_02', 'RequestForm$Attr-4-4', 'txtAddress', 'e_25', 'e_27',
            'txtToAddress', 'ご住所', 'fAddress',
        ],

        'addressPattern' => ['住所', '所在地', '市区',
            '町名', '建物名・施設名', 'item117', 'ご住所', '市区町村郡/町名/丁目', 'C020', 'data4',
            'data5',
        ],

        'titleMatch' => [
            'title', 'subject', '件名', 'pref', 'job', 'form_fields[field_42961a5]', 'executive',
            'text', 'singleAnswer(ANSWER263)', 'items[affiliation]',
        ],

        'titlePattern' => ['件名', 'Title', 'Subject', '題名', '用件名'],

        'homePageUrlMatch' => ['URL', 'url', 'HP'],

        'lastNameMatch' => [
            '姓', 'lastname', 'name1', 'singleAnswer(ANSWER2-1)', 'f000003197', 'i_name_sei',
            'fFirstName', 'お名前（漢字）[]', 'c_q16_first', 'sei',
            'Public::Application::Userenquete_D__P__D_name2', 'f000027211', 'LastName',
            'query[1][1]', '162441_68591pi_162441_68591', 'txtName2', 'customer[last_name]',
            'singleAnswer(ANSWER158)', 'c_q4_second', 'c_q10_second',
            '553162_110236pi_553162_110236', 'お名前(姓)', 'vl002', 'nereline', 'f000667747',
        ],

        'lastNameKey' => ['f013008539', 'seiName'],

        'surnameMatch' => [
            '名', 'firstname', 'name2', 'given_name', 'txtNameMei',
            'singleAnswer(ANSWER2-2)', 'f000003198', 'i_name_mei', 'name-mei',
            'c_q23_second', 'fLastName', 'お名前（漢字）[]', 'c_q16_second', 'f000027210',
            'fname',  'f013017368', 'mei', 'FirstName', 'txtName1', 'customer[first_name]',
            'RequestForm$Attr-3-1', 'singleAnswer(ANSWER159)', 'firstName', 'お名前(名)', 'nerestation',
        ],

        'surnameKey' => ['f013008540'],

        'fullnameMatch' => [
            'ご担当者名', 'お名前(必須)', 'UserName', 'singleAnswer(ANSWER3400)', 'qEnq5461', 'qEnq5462', 'ご担当者名', 'NAME', 'f013008540', 'f013017369',
            'input8', 'your-name', 'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID9$fldValue$txtSingleTextBox',
            'f000104020', 'C016', 'G012', 'RequestForm$Attr-3-2', 'c_q24_second', '法人名', 'mansionName', 'fNamey', 'name',
            'your-name', 'name', 'Name',
        ],

        'fullnamePattern' => ['名前', '氏名', '担当者', '差出人', 'ネーム', 'お名前(漢字)', 'お名前(必須)', 'お名前', 'おなまえ'],

        'fullFurnameMatch' => ['your-name-ruby', 'form_answers[parts][8df997826280be8a58fc27fc61ad3da96f63fccf][6bc765a30a0115f51a47c62b94196fa3ef7d3df8]', 'kana_s', 'onf'],

        'fullFurnamePattern' => ['名前', '氏名', '担当者', '差出人', 'ネーム', 'お名前(漢字)', 'お名前(必須)', 'お名前'],

        'fursurnameMatch' => ['セイ', 'せい', 'lastname_kana', 'sei_kana', 'kana_sei', 'furi_sei', 'txtNameSeiFuri',
            'i_kana_sei', 'name-furi-sei', 'c_q22_first', 'fFirstNamey', 'c_q17_first',
            'Public::Application::Userenquete_D__P__D_name1_ka', 'first_kana', 'sei_k', 'meiName',
            'aform-field-276-firstname-kana', '担当者名：姓（カナ）', 'aform-field-166-firstname-kana',
            'NAME_F_SEI', 'RequestForm$Attr-3-3', 'firstkana', 'singleAnswer(ANSWER161)', 'c_q29_first',
            'smauto_prcsr_sender_name', 'smauto_prcsr_sender_name_kana', 'read_1', 'フリガナ(姓)', 'furi_first', 'ご氏名（フリガナ）',
            'ご担当者名',
        ],

        'fursurnamePattern' => ['名 フリガナ'],

        'furlastnameMatch' => [
            'メイ', 'めい', 'firstname_kana', 'mei_kana', 'kana_mei', 'e_8276', 'furi_neme', 'i_kana_mei', 'name-furi-mei', 'c_q22_second', 'fLastNamey', 'c_q17_second', 'Public::Application::Userenquete_D__P__D_name2_ka', 'last_kana', 'mei_k', 'query[2][1]',
            'form_answers[parts][235b8adea4b8bc1685dd57688c0f9cab0d03ca86][a2ea06f5af2b22a6fb316451a4e00ba3b32a0781]', '担当者名：名（カナ）',
            'customer[last_name_reading]', 'NAME_F_MEI', 'RequestForm$Attr-3-4', 'lastkana', 'singleAnswer(ANSWER160)', 'c_q29_second', 'read_2',
            'フリガナ(名)', 'furi_last', 'ご担当者名（フリガナ）',
        ],

        'furlastnamePattern' => ['姓 フリガナ'],

        'areaPattern' => ['都道府県'],

        'areaMatch' => [
            'info_perception_etc', 't_message', 'お問い合わせ内容(必須)', 'fSection', 'fPosition', 'fOption1',
            'fOption3', 'position', 'industry', 'Public::Application::Userenquete_D__P__D_division', 'item_spec',
            'your-message', 'ご意見・ご希望・お問合せ内容', 'your-subject', 'your-message', 'Message',
        ],

        'fullPhoneNumer1Pattern' => ['fax', 'FAX番号', '電話', '携帯電話', '連絡先', 'TEL', 'Phone', '電話番号2', '電話番号', '確認のため再度ご入力下さい。', 'C021', 'c_q30'],

        'fullPhoneNumer1Match' => ['FAX', 'singleAnswer(ANSWER3408)', '電話番号', 'FAX番号', 'cf2_field_5'],

        'fullPhoneNumber2Match' => ['FAX', 'txtTEL', 'singleAnswer(ANSWER5)', 'singleAnswer(ANSWER6)', 'input/zip_code', 'telnum',
            'fTel', 'fFax', '市区町村', 'input35', 'cp_tels', 'RequestForm$Attr-5-1', 'singleAnswer(ANSWER164)',
            'data6',
        ],

        'fullPhoneNumber2Key' => ['txtTEL', 'tel'],

        'phoneNumber1match' => [
            'f000003204:a', 'f000009697:a', 'i_tel1', 'tel[data][0]', 'tel00_s', 'tel_:a',
            'c_q9_areacode', 'TelNumber1', 'f000026565:a', 'txt_tel[]', 'form-tel[data][0]',
            'inputs[fax1]',  'tel_no_1', 'f012956241:a', 'Tel1', 'phone', 'query[11][0]',
            'query[5][0]', 'f000224113:a', 'f000224112:a', 'c_q28_areacode', 'txtPhonea',
            'senddata4', 'ファックス番号[data][0]',
        ],

        'phoneNumber1key' => ['PhoneL', 'tel[data][0]', 'item_16_phone1', 'item_17_phone1', 'e_28[tel1]', 'tel01', 'phone', 'tel-num[data][0]', 'fax-num[data][0]', 'inq_tel[data][0]', 'TEL1', 'tel_1'],

        'phoneNumber2Match' => [
            'PhoneC', 'f000003204:e', 'f000009697:e', 'i_tel2', 'tel[data][1]', 'item_16_phone2',
            'tel01_s', 'tel_:e', 'c_q9_citycode', 'TelNumber2', 'f000026565:e',
            'txt_tel_1', 'tel_no_2', 'f012956241:e', 'tel02', 'Tel2', 'query[11][1]',
            'query[5][1]', 'tkph971-2',  'f000224112:e', 'f000224113:e', 'c_q27_citycode', 'c_q28_citycode',
            'c_q27_subscribercode', 'txtPhoneb', 'txtPhonec', 'senddata11', 'senddata12', 'ファックス番号[data][1]',
            'ファックス番号[data][2]',
        ],

        'phoneNumber2Key' => ['tel-num[data][1]', 'fax-num[data][1]', 'inq_tel[data][1]', 'inq_tel[data][2]', 'TEL2', 'tel_2', 'tel_3'],

        'phoneNumber3Match' => ['PhoneR', 'f000003204:n', 'f000009697:n', 'i_tel3', 'tel[data][2]', 'item_16_phone3', 'tel02_s', 'tel_:n', 'c_q9_subscribercode', 'TelNumber3', 'f000026565:n', 'txt_tel_2', 'tel_no_3', 'f012956241:n', 'tel03', 'Tel3', 'query[11][2]', 'query[5][2]', 'tkph971-3', 'f000224112:n', 'f000224113:n'],

        'phoneNumber3Key' => ['TEL3'],

        'address2Match' => ['ext_07', '市区町村', 'fHouseNumber'],

        'randomNumber1Match' => ['丁目番地', '建物名'],

        'randomNumber2Match' => ['年齢', '築年数'],

        'randomStringPattern' => ['部署'],

        'orderPattern' => ['オーダー'],

        'answerPattern' => ['answer[category]'],

        'urlPattern' => ['fUrl', '作成中ページの公開用URL'],

        'urlKey' => ['e_29', 'customer[web_site]'],

        'yearMatch' => ['f012956299:y', 'Birthday1', '生年_need'],

        'monthMatch' => ['f012956299:m', 'roomNumber', '月_need'],

        'dayMatch' => ['f012956299:d', '日_need'],

        'fullDateMatch' => ['birth', 'ご来店日(必須)'],

        'randomString2Match' => ['資料'],

        'mailConfirm1Match' => ['email_conf1'],

        'mailConfirm2Match' => ['email_conf2'],
    ],

    'successMessages' => [
        'ありがとうございま',
        'メール送信が正常終了',
        '内容を確認させていただき',
        '受け付けま',
        '問い合わせを受付',
        '完了いたしま',
        '完了しまし',
        '成功しました',
        '有難うございま',
        '自動返信メール',
        '送信いたしま',
        '送信されま',
        '送信しました',
        '送信完了',
        '受け付けました',
        'ございました',
        'ありがとうございます',
        'お問い合わせを承りました',
        'ご返事させていただきます',
        'お申し込みを承りました',
        'ご連絡させて頂',
        'ご連絡させていただき',
        '受けしました',
    ],

    'xpathButton' => '
    //button[contains(text(),"確認")]
    | //input[@type="submit" and contains(@value,"送信する") and contains(@name,"submitSubmit")]
    | //input[@type="submit" and contains(@value,"送信する") and contains(@name,"ACMS_POST_Form_Submit")]
    | //input[@type="submit" and contains(@value,"送信する")]
    | //input[@type="submit" and contains(@value,"確認画面へ")]
    | //input[@type="button" and contains(@id,"button_mfp_goconfirm")]
    | //input[@type="button" and contains(@name,"_check_x")]
    | //input[@type="button" and contains(@name,"_submit_x")]
    | //input[@type="button" and contains(@name,"conf")]
    | //input[@type="submit" and contains(@name,"sendmail")]
    | //input[@type="submit" and contains(@value,"　送　信　")]
    | //input[@type="submit" and contains(@name,"submitConfirm")]
    | //button[@type="submit"][contains(@data-disable-with-permanent, "true")]
    | //button[@type="submit"][contains(@class, "btn-cmn--red")]
    | //input[@type="submit" and contains(@value,"入力内容を確認する")]
    | //input[@type="submit" and contains(@value,"送信")]
    | //button[@type="submit" and contains(@value,"送信")]
    | //input[@type="submit" and contains(@value,"内容確認へ")]
    | //input[@type="submit" and contains(@value,"入力内容確認")]
    | //input[@type="submit" and contains(@value,"送信する")]
    | //*[contains(text(), "この内容で送信する")]
    | //*[contains(text(),"に同意する")]
    | //*[contains(text(),"確認する")]
    | //a[@class="js-formSend btnsubmit"]
    | //a[@id="js__submit"]
    | //a[@href="kagawa-casting-08.php"]
    | //a[contains(text(),"次へ")]
    | //a[contains(text(),"確認")]
    | //a[contains(text(),"送信")]
    | //button[contains(@class,"mfp_element_button")]
    | //button[@type="submit"][contains(@value,"send")]
    | //button[@type="submit"][contains(@name,"_exec")]
    | //button[@type="submit"][contains(@name,"Action")]
    | //button[@type="submit" and (contains(@class,"　上記の内容で送信する　"))]
    | //button[@class="nttdatajpn-submit-button"]
    | //button[@type="button" and (contains(@class,"ahover"))]
    | //button[@type="submit" and (contains(@class,"mfp_element_submit"))]
    | //button[@type="submit"][contains(@name,"__送信ボタン")]
    | //button[@type="submit" ][contains(@class, "btn")]
    | //button[@type="submit"][contains(@value,"この内容で無料相談する")]
    | //button[@type="submit"]//span[contains(text(),"同意して進む")]
    | //button[@type="submit" and @class="btn"]
    | //button[@type="submit"][contains(@value,"送信する")]
    | //button[contains(@value,"送信")]
    | //button[contains(text(),"上記の内容で登録する")]
    | //button[contains(text(),"次へ")]
    | //button[contains(text(),"送　　信")]
    | //button[contains(text(),"送信")]
    | //button[span[contains(text(),"送信")]]
    | //input[@type="button" and @id="submit_confirm"]
    | //input[@type="button"][contains(@value,"確認画面へ")]
    | //input[@type="button"][contains(@value,"入力画面に戻る")]
    | //input[@type="image"][contains(@value,"この内容で登録する") and @type!="hidden"]
    | //input[@type="image"][contains(@alt,"この内容で送信する") and @type!="hidden"]
    | //input[@type="image"][contains(@alt,"この内容で送信する") and @type!="hidden"]
    | //input[@type="image"][contains(@alt,"送信") and @type!="hidden"]
    | //input[@type="image" and contains(@value,"SEND")]
    | //input[@type="image" and contains(@name,"_send2_")]
    | //input[@type="image" and contains(@name,"send")]
    | //input[@type="image" and contains(@src,"../images/entry/btn_send.png")]
    | //input[contains(@alt,"確認") and @type!="hidden"]
    | //input[@type="image"][contains(@name,"conf") and @type!="hidden"]
    | //input[@type="submit" and not(contains(@value,"戻る") or contains(@value,"クリア"))]
    | //input[contains(@alt,"次へ") and @type!="hidden"]
    | //input[contains(@value,"次へ") and @type!="hidden"]
    | //input[contains(@value,"確 認") and @type!="hidden"]
    | //input[contains(@value,"確認") and @type!="hidden"]
    | //input[contains(@value,"送　信") and @type!="hidden"]
    | //input[contains(@value,"送信") and @type!="hidden"]
    | //input[@type="checkbox"]
    | //label[@for="sf_KojinJouhou__c" and not(contains(@value,"戻る") or contains(@value,"クリア"))]
    | //img[contains(@alt,"内容を確認する")]
    | //img[contains(@alt,"確認画面に進む")]
    | //img[contains(@alt,"この内容で送信する")]
    | //img[contains(@alt,"完了画面へ")]
    | //input[@type="image"][contains(@name,"check_entry_button") and @type!="hidden"]
    ',

    'xpathMessage' => '
    //*[contains(text(),"ありがとうございま")]
    | //*[contains(text(),"メール送信が正常終了")]
    | //*[contains(text(),"内容を確認させていただき")]
    | //*[contains(text(),"受け付けま")]
    | //*[contains(text(),"問い合わせを受付")]
    | //*[contains(text(),"完了いたしま")]
    | //*[contains(text(),"完了しまし")]
    | //*[contains(text(),"成功しました")]
    | //*[contains(text(),"有難うございま")]
    | //*[contains(text(),"自動返信メール")]
    | //*[contains(text(),"送信いたしま")]
    | //*[contains(text(),"送信されま")]
    | //*[contains(text(),"送信しました")]
    | //*[contains(text(),"送信完了")]
    | //*[text()[contains(.,"受け付けました")]]
    | //*[text()[contains(.,"ございました")]]
    | //*[contains(text(),"ありがとうございます")]
    | //*[text()[contains(.,"お問い合わせを承りました")]]
    | //*[text()[contains(.,"ご返事させていただきます")]]
    | //*[contains(text(),"お申し込みを承りました")]
    | //*[contains(text(),"ご連絡させて頂")]
    | //*[contains(text(),"ご連絡させていただき")]
    | //*[contains(text(),"受けしました")]
',
];
