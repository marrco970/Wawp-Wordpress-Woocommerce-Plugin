jQuery(document).ready(function($){
  $(document).on('click','.awp-send-otp-front',function(e){
    e.preventDefault();
    var uid=$(this).data('userid');
    $.ajax({
      url: awpFrontendOTP.ajaxUrl,
      type:'POST',
      data:{
        action:'awp_send_otp_frontend',
        nonce: awpFrontendOTP.nonce,
        user_id:uid
      },
      success:function(r){
        if(r.success){ alert(r.data); } else { alert(r.data); }
      },
      error:function(){ alert('Error sending OTP'); }
    });
  });
  $(document).on('click','.awp-confirm-otp-front',function(e){
    e.preventDefault();
    var uid=$(this).data('userid');
    var code=$(this).prev('.awp-otp-code-input').val();
    $.ajax({
      url: awpFrontendOTP.ajaxUrl,
      type:'POST',
      data:{
        action:'awp_confirm_otp_frontend',
        nonce: awpFrontendOTP.nonce,
        user_id:uid,
        code:code
      },
      success:function(r){
        if(r.success){ alert(r.data); location.reload(); } else { alert(r.data); }
      },
      error:function(){ alert('Error confirming OTP'); }
    });
  });
});
