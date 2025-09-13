jQuery(function($){
    let $modal, $overlay, $closeBtn, $instanceSelect, $msgArea;
    let userId = 0;
    let emojiArea = null;

    // 1) Define placeholders, same as your snippet
    const placeholders = {
        '{{id}}': 'Order ID',
        '{{order_key}}': 'Order Key',
        '{{order_date}}': 'Order Date',
        '{{order_link}}': 'Order Summary Link',
        '{{product}}': 'Product List',
        '{{product_name}}': 'Product Name',
        '{{order_discount}}': 'Order Discount',
        '{{cart_discount}}': 'Cart Discount',
        '{{order_tax}}': 'Tax',
        '{{currency}}': 'Currency Symbol',
        '{{order_subtotal}}': 'Subtotal Amount',
        '{{order_total}}': 'Total Amount',
        '{{billing_first_name}}': 'First Name',
        '{{billing_last_name}}': 'Last Name',
        '{{billing_company}}': 'Company',
        '{{billing_address_1}}': 'Address 1',
        '{{billing_address_2}}': 'Address 2',
        '{{billing_city}}': 'City',
        '{{billing_postcode}}': 'Postcode',
        '{{billing_country}}': 'Country',
        '{{billing_state}}': 'Province',
        '{{billing_email}}': 'Email',
        '{{billing_phone}}': 'Phone',
        '{{shop_name}}': 'Shop Name',
        '{{site_link}}': 'Site Link',
        '{{transaction_id}}': 'Transaction ID',
        '{{note}}': 'Order Note'
    };

    // 2) On DOM ready, build the popup + attach events
    $(document).ready(function(){
        // Insert the popup markup inside #wpwrap
        $('#wpwrap').append(`
            <div id="awp-send-msg-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#00000099; z-index:9998;"></div>
            <div id="awp-send-msg-modal" style="display:none; position:fixed; top:50%; left:50%; width:400px; transform:translate(-50%,-50%); background:#fff; padding:20px; border-radius:4px; z-index:9999;">
              <h3 style="margin-top:0;">Send WhatsApp Message</h3>
              <label>Instance:</label>
              <select id="awp-instance-select" style="width:100%; margin-bottom:10px;"></select>

              <label>Message:</label>
              <!-- The container for our placeholders dropdown -->
              

              <!-- Our emoji-enabled textarea -->
              <textarea id="awp-message-text" rows="4" style="width:100%;"></textarea>

              <div style="margin-top:12px; text-align:right;">
                <button type="button" id="awp-send-msg-cancel" class="button button-secondary">Cancel</button>
                <button type="button" id="awp-send-msg-confirm" class="button button-primary">Send</button>
              </div>
            </div>
        `);

        // Cache references
        $overlay = $('#awp-send-msg-overlay');
        $modal   = $('#awp-send-msg-modal');
        $closeBtn= $('#awp-send-msg-cancel');
        $instanceSelect = $('#awp-instance-select');
        $msgArea = $('#awp-message-text');

        // 3) Initialize EmojioneArea on the #awp-message-text
        emojiArea = $msgArea.emojioneArea({
            pickerPosition: "bottom",
            filtersPosition: "top",
            tonesStyle: "radio"
        });

        // 4) Populate instance dropdown
        $instanceSelect.empty();
        if (awpSendMsgData.onlineInstances && awpSendMsgData.onlineInstances.length > 0) {
            awpSendMsgData.onlineInstances.forEach(function(obj){
                $instanceSelect.append(`<option value="${obj.instance_id}">${obj.name} (${obj.instance_id})</option>`);
            });
        } else {
            $instanceSelect.append(`<option value="">${awpSendMsgData.noOnlineInstance}</option>`);
        }

        // 5) Create the placeholders dropdown inside .placeholder-containerlogin
        buildPlaceholderDropdown('.placeholder-containerlogin', placeholders);

        // 6) Cancel button
        $closeBtn.on('click', function(){
            hideModal();
        });

        // 7) "Send" button
        $('#awp-send-msg-confirm').on('click', function(){
            const instance_id = $instanceSelect.val();
            const text = emojiArea[0].emojioneArea.getText().trim();

            if (!instance_id) {
                alert('No instance selected.');
                return;
            }
            if (!text) {
                alert('Please enter a message.');
                return;
            }

            // Do AJAX
            $.ajax({
                url: awpSendMsgData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'awp_send_user_custom_message',
                    security: awpSendMsgData.security,
                    user_id: userId,
                    instance_id: instance_id,
                    text: text
                },
                success: function(resp){
                    if (resp && resp.success) {
                        alert(resp.data);
                        hideModal();
                    } else {
                        alert((resp && resp.data) || 'Error sending message.');
                    }
                },
                error: function(){
                    alert('AJAX error occurred.');
                }
            });
        });

        // 8) When user clicks "Send" in the table row
        $('.awp-send-msg-btn').on('click', function(e){
            e.preventDefault();
            userId = $(this).data('user-id') || 0;
            // Clear old text from EmojioneArea
            emojiArea[0].emojioneArea.setText('');
            showModal();
        });
    });

    // Show/hide modal
    function showModal(){
        $overlay.show();
        $modal.show();
    }
    function hideModal(){
        $overlay.hide();
        $modal.hide();
    }

    // 9) Utility: Build a <select> with placeholders, insert into container
    function buildPlaceholderDropdown(containerSelector, placeholdersObj) {
        // Start with an empty <select>
        let dropdownHTML = `<select class="placeholder-dropdown" style="width:100px;">
                                <option value="">-- Insert --</option>`;
        // Add each placeholder
        Object.entries(placeholdersObj).forEach(([token, label]) => {
            dropdownHTML += `<option value="${token}">${label}</option>`;
        });
        dropdownHTML += `</select>`;

        // Insert it into the container
        $(containerSelector).html(dropdownHTML);

        // Watch for changes
        $(containerSelector).on('change', 'select.placeholder-dropdown', function(){
            let selected = $(this).val();
            if (!selected) return;

            // Get current text from EmojioneArea
            let currentText = emojiArea[0].emojioneArea.getText();
            // Append placeholder to it
            emojiArea[0].emojioneArea.setText(currentText + ' ' + selected);

            // Reset the <select>
            $(this).val('');
        });
    }
});
