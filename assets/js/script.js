jQuery(document).ready(function($){
    $(document).on('click', '#awp-whatsapp-button', function(){
        $.post(awp_ajax_obj.ajax_url, {
            action: 'awp_increment_chat_clicks',
            page_id: awp_ajax_obj.current_pid
        });
        $('#awp-chat-window').toggleClass('scale-toggle');
    });
    $(document).on('click', '#awp-minimize-icon', function(){
        $('#awp-chat-window').toggleClass('scale-toggle');
    });
    let originalIconHTML = null;
    $(document).on('mouseenter', '#awp-whatsapp-button', function(){
        let $btn = $(this);
        let $icon = $btn.find('i, svg, .awp-custom-svg, img');
        if ($icon.length) {
            originalIconHTML = $icon[0].outerHTML;
            $icon.remove();
        }
        if (!$btn.find('.awp-typing-dots').length) {
            $btn.append(`
                <div class="awp-typing-dots">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>
            `);
        }
    });
    $(document).on('mouseleave', '#awp-whatsapp-button', function(){
        let $btn = $(this);
        $btn.find('.awp-typing-dots').remove();
        if (originalIconHTML) {
            $btn.append(originalIconHTML);
        }
    });
    $(document).on('click', '.awp-qr-chat-icon', function(){
        let waLink = $(this).data('awp-link');
        let phoneMatch = waLink.match(/phone=(\d+)/);
        let phone = phoneMatch ? phoneMatch[1] : '';
        let name  = $(this).find('.awp-contact-name').text() || 'Unknown';
        $.post(awp_ajax_obj.ajax_url, {
            action: 'awp_contact_click',
            phone: phone,
            name: name,
            page_id: awp_ajax_obj.current_pid
        });
        $('#awp-contact-list').hide();
        $('.awp-social-container').hide();
        $('#awp-qr-card').show();
        $('#awp-dynamic-qr').empty();
        if (typeof QRCode !== 'undefined') {
            new QRCode(document.getElementById('awp-dynamic-qr'), {
                text: waLink,
                width: 150,
                height: 150,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }
        $('#awp-open-whatsapp').attr('href', waLink).data('phone', phone);
    });
    $(document).on('click', '#awp-open-whatsapp', function(){
        let phone = $(this).data('phone') || '';
        if (phone) {
            $.post(awp_ajax_obj.ajax_url, {
                action: 'awp_open_whatsapp_click',
                phone: phone,
                page_id: awp_ajax_obj.current_pid
            });
        }
    });
    $(document).on('click', '#awp-qr-back', function(){
        $('#awp-qr-card').hide();
        $('#awp-contact-list').show();
        $('.awp-social-container').show();
    });
});
