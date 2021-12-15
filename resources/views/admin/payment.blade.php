@extends('layouts.admin.app')
<style>

.card-brand {
  margin:.375rem .5rem;
}
 </style>
@section('content_header_label')
    <h3 class="m-0">支払い管理</h3>
@stop

@section('content')

  {!! Form::open(['url' => route('admin.payment.check'), 'data-parsley-validate', 'id' => 'payment-form']) !!}
  @if ($message = Session::get('success'))
  <div class="alert alert-success alert-block">
    <button type="button" class="close" data-dismiss="alert">×</button> 
          <strong>{{ $message }}</strong>
  </div>
  @endif
  <div class="form-group" id="product-group">
    <label class="col-sm-4">プラン</label>
    <div class="col-sm-12 form-group">
          {!! Form::select('plan', [
            $planKey => $planVal
            ], null, [
              'class'                       => 'form-control',
              'required'                    => 'required',
              'data-parsley-class-handler'  => '#product-group'
              ]) !!}
    </div>
  </div>
  <div class="row">
      <div class="col-md-12">
          <div class="form-group">
              <div id="card-element"></div>
          </div>
      </div>
        
  </div>
    <div class="form-group">
        @if($user->paycheck)
            <br>サブスクリプションが完了しました。
            <!-- <button id="card-button" class="btn btn-lg btn-block btn-danger btn-order">サブスクリプション停止</button> -->
        @else
            <button id="card-button" class="btn btn-lg btn-block btn-success btn-order">サブスクリプション</button>
        @endif
    </div>
    <div class="row">
      <div class="col-md-12">
          <span class="payment-errors" id="card-errors" style="color: red;margin-top:10px;"></span>
      </div>
    </div>
    {!! Form::close() !!}

  {{ Form::open(['route' => 'admin.check.stop', 'method' => 'POST', 'id' => 'checkForm']) }}
  {{ Form::close() }}

  {{ Form::open(['route' => 'admin.check.start', 'method' => 'POST', 'id' => 'startForm']) }}
  {{ Form::close() }}


        
@stop


@section('stripe_js')
  <script>
      window.ParsleyConfig = {
          errorsWrapper: '<div></div>',
          errorTemplate: '<div class="alert alert-danger parsley" role="alert"></div>',
          errorClass: 'has-error',
          successClass: 'has-success'
      };
  </script>

  <script src="https://parsleyjs.org/dist/parsley.js"></script>

  <script type="text/javascript" src="https://js.stripe.com/v2/"></script>
  <script src="https://js.stripe.com/v3/"></script>
  <script>
      var style = {
          base: {
              color: '#32325d',
              lineHeight: '18px',
              fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
              fontSmoothing: 'antialiased',
              fontSize: '16px',
              '::placeholder': {
                  color: '#aab7c4'
              }
          },
          invalid: {
              color: '#fa755a',
              iconColor: '#fa755a'
          }
      };

      const stripe = Stripe('{{ env("STRIPE_KEY") }}', { locale: 'ja' }); // Create a Stripe client.
      const elements = stripe.elements(); // Create an instance of Elements.
      const card = elements.create('card', { hidePostalCode : true , style: style }); // Create an instance of the card Element.

      card.mount('#card-element'); // Add an instance of the card Element into the `card-element` <div>.

      card.on('change', function(event) {
          var displayError = document.getElementById('card-errors');  
          if (event.error) {
              displayError.textContent = event.error.message;
          } else {
              displayError.textContent = '';
          }
      });

      // Handle form submission.

      var form = document.getElementById('payment-form');
      form.addEventListener('submit', function(event) {
          event.preventDefault();

          stripe.createToken(card).then(function(result) {
              if (result.error) {
                  // Inform the user if there was an error.
                  var errorElement = document.getElementById('card-errors');
                  errorElement.textContent = result.error.message;
              } else {
                  // Send the token to your server.
                  stripeTokenHandler(result.token);
              }
          });
      });

      // Submit the form with the token ID.
      function stripeTokenHandler(token) {
          // Insert the token ID into the form so it gets submitted to the server
          var form = document.getElementById('payment-form');
          var hiddenInput = document.createElement('input');
          hiddenInput.setAttribute('type', 'hidden');
          hiddenInput.setAttribute('name', 'stripeToken');
          hiddenInput.setAttribute('value', token.id);
          form.appendChild(hiddenInput);

          // Submit the form
          form.submit();
      }
  </script>
@stop

@section('scripts')
  <script>

  $('.stop').click(function() {
    toastr.fire({
        html: "サブスクリプションを停止しますか？",
        showDenyButton: false,
        showCancelButton: true,
        showConfirmButton: true,
        confirmButtonText: "停止",
        cancelButtonText: "キャンセル",
        confirmButtonColor: "#dc3545",
        allowOutsideClick: false,
        allowEscapeKey: false,
        timer: undefined
    }).then((result) => {
        if (result.isConfirmed) {
            $('#checkForm').submit();
            $('#showLoading').click();
        }
    })
  })

  $('.start').click(function() {
    toastr.fire({
        html: "サブスクリプションを開始しますか？",
        showDenyButton: false,
        showCancelButton: true,
        showConfirmButton: true,
        confirmButtonText: "開始",
        cancelButtonText: "キャンセル",
        confirmButtonColor: "#007bff",
        allowOutsideClick: false,
        allowEscapeKey: false,
        timer: undefined
    }).then((result) => {
        if (result.isConfirmed) {
            $('#startForm').submit();
            $('#showLoading').click();
        }
    })
  })

  </script>
@stop
