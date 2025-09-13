//  assets/js/awp-prefs.js
jQuery(function ($) {
	$('#awp-save-prefs').on('click', function (e) {
		e.preventDefault();

		const $msg = $('#awp-prefs-msg').text(awpPrefs.i18n.saving);

		$.post(awpPrefs.ajax, {
			action : 'awp_toggle_prefs',
			nonce  : awpPrefs.nonce,
			login  : $('#awp-login-pref').is(':checked'),
			wc     : $('#awp-wc-pref').is(':checked')
		})
		.done(()   => $msg.text(awpPrefs.i18n.saved))
		.fail(()   => $msg.text(awpPrefs.i18n.error));
	});
});
jQuery(function($){
    $('#awp-save-prefs').on('click',function(){
        const btn  = $(this);
        const msg  = $('#awp-prefs-msg').removeClass('awp-ok awp-err');
        btn.prop('disabled',true);   msg.text(awpPrefs.i18n.saving);

        $.post(awpPrefs.ajax,{
            action : 'awp_toggle_prefs',
            nonce  : awpPrefs.nonce,
            login  : $('#awp-login-pref').is(':checked'),
            wc     : $('#awp-wc-pref').is(':checked')
        }).done(res=>{
            msg.addClass(res.success ? 'awp-ok':'awp-err')
               .text( res.success ? awpPrefs.i18n.saved : awpPrefs.i18n.error );
        }).fail(()=>{
            msg.addClass('awp-err').text(awpPrefs.i18n.error);
        }).always(()=>btn.prop('disabled',false));
    });
});
