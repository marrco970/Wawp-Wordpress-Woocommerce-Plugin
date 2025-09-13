jQuery(document).ready(function($){
  setInterval(function(){ doCheck(false); }, 1800000);
  doCheck(false);

  $('#mrsb-refresh-button').on('click', function(e){
    e.preventDefault();
    var $icon = $(this).find('i');
    $icon.removeClass('ri-refresh-line').addClass('ri-loader-4-line ri-spin');
    doCheck(true);
  });

  function doCheck(isManualClick){
    $.ajax({
      url: MRSBAutoCheck.ajaxUrl,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'mrsb_auto_check_status',
        security: MRSBAutoCheck.nonce
      },
      success: function(res){
        if(isManualClick){
          window.location.reload();
          return;
        }
        if(res && res.success && res.data && res.data.html){
          $('#mrsb-status-area').html(res.data.html);
        }
      },
      error: function(){
        $('#mrsb-refresh-button i')
          .removeClass('ri-loader-4-line ri-spin')
          .addClass('ri-refresh-line');
      },
      complete: function(){
        if(!isManualClick){
          $('#mrsb-refresh-button i')
            .removeClass('ri-loader-4-line ri-spin')
            .addClass('ri-refresh-line');
        }
      }
    });
  }
});

