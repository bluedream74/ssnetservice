$(document).ready(function() {
    $('#btnAddressAutoInput').click(function() {
        var value = $('#address_postalCode1').val() + $('#address_postalCode2').val();
        var regexp = /^[0-9]{7}$/;
        if (regexp.test(value)) {
            postal_code.get(value, function(address) {
                $('#address_prefecture').val(address.prefecture); // => "東京都"
                $('#address_address1').val(address.city); // => "千代田区"
                $('#address_address2').val(address.area); // => "千代田"
                $('#address_address3').val(address.street); // => ""
            });
        } else {
            toastr.fire({
                type: 'warning',
                icon: 'warning',
                text: "正しい郵便番号を入力してください。"
            })
        }
    })
})