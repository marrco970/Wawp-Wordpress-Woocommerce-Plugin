jQuery(document).ready(function($) {
    var table = $('#awp-logs-table').DataTable({
        dom: '<"dt-top-bar">t<"bottom"ip>',
        lengthMenu: [
            [20, 40, 60, 80, 100, 500, 1000, -1],
            [20, 40, 60, 80, 100, 500, 1000, "All"]
        ],
        pageLength: -1,
        order: [[ 5, "desc" ]], // Order by date descending by default
        columnDefs: [
            { targets: [0], orderable: false }, // Checkbox column
            { targets: [2, 13, 14], visible: false } // Hide User ID, Info, and Ref ID
        ]
    });

    $('.dataTables_length').appendTo('#awp-entries-menu');
    $('.dataTables_filter').remove();

    let typingTimer;
    const searchInput = $('#awp-live-search');
    searchInput.on('input', function() {
        if ($(this).val().length > 0) {
            $(this).removeClass('search-icon').addClass('loading-icon');
        } else {
            $(this).removeClass('loading-icon').addClass('search-icon');
        }
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function() {
            table.search(searchInput.val()).draw();
        }, 400);
    });

    $('#awp-select-all').on('click', function() {
        $('.awp-row-select').prop('checked', this.checked);
    });

    $(document).on('click', '.awp-row-select', function() {
        if (!this.checked) $('#awp-select-all').prop('checked', false);
    });

    $('.delete-selected, .awp-delete-selected').on('click', function() {
        if (!confirm('Are you sure?')) return;
        let selected = [];
        $('.awp-row-select:checked').each(function() {
            selected.push($(this).val());
        });
        if (!selected.length) {
            alert('No items selected.');
            return;
        }
        $.post(awpLog.ajax_url, {
            action: 'awp_delete_logs',
            log_ids: selected,
            nonce: awpLog.nonce
        }, function(r) {
            if (r.success) location.reload();
            else alert('Delete failed: ' + r.data);
        });
    });

    // Use event delegation for dynamically added rows
    $('#awp-logs-table tbody').on('click', '.awp-resend-button', function(e) {
        e.preventDefault();
        let id = $(this).data('log-id');
        if (!confirm('Are you sure you want to resend this notification?')) return;
        $.post(awpLog.ajax_url, {
            action: 'awp_resend_notification',
            log_id: id,
            nonce: awpLog.nonce
        }, function(r) {
            if (r.success) {
                alert('Notification resent successfully.');
                location.reload();
            } else {
                alert('Failed to resend notification: ' + r.data);
            }
        });
    });

    let lastOpenPopup = null;
    $('.filter-pill').on('click', function(e) {
        if (!$(e.target).hasClass('pill-close')) {
            if (lastOpenPopup && lastOpenPopup[0] !== $(this).find('.filter-popup')[0]) {
                lastOpenPopup.hide();
            }
            $(this).find('.filter-popup').show();
            lastOpenPopup = $(this).find('.filter-popup');
        }
        e.stopPropagation();
    });

    $('.pill-close').on('click', function(e) {
        e.stopPropagation();
        const pill = $(this).closest('.filter-pill');
        const popup = pill.find('.filter-popup');
        if (pill.is('#awp-pill-items')) {
            $('#awp-items-select').val('20');
            $('#awp-value-items').text('20');
            table.page.len(20).draw();
        } else if (pill.is('#awp-pill-date')) {
            $('#awp-date-operator').val('last');
            $('#awp-date-value').val('7').attr('type','number');
            $('#awp-date-unit').val('days').show();
            $('#awp-date-value2').hide();
            $('#awp-value-date').text('Last 7 days');
        } else if (pill.is('#awp-pill-msgtype')) {
            $('#awp-msgtype-boxes input[type="checkbox"]').prop('checked', false);
            $('#awp-value-msgtype').text('All');
        } else if (pill.is('#awp-pill-status')) {
            $('#awp-status-boxes input[type="checkbox"]').prop('checked', false);
            $('#awp-value-status').text('All');
        } else if (pill.is('#awp-pill-columns')) {
            $('.col-toggle').prop('checked', true);
            for (let i = 1; i <= 14; i++){
                table.column(i).visible(true);
            }
            table.column(2).visible(false); // Hide User ID
            table.column(13).visible(false); // Hide Info
            table.column(14).visible(false); // Hide Ref ID
            pill.find('.pill-value').text('All');
        }
        popup.hide();
        table.draw();
    });

    $(document).on('click', function(e) {
        if (lastOpenPopup && !$(e.target).closest('.filter-pill').length) {
            lastOpenPopup.hide();
            lastOpenPopup = null;
        }
    });
    
    // Use event delegation for dynamically added rows
    $('#awp-logs-table tbody').on('click', '.awp-status', function() {
        const raw = $(this).attr('data-json') || '{}';
        let parsed;
        try {
            parsed = JSON.parse(raw);
        } catch(e) {
            parsed = { status: 'unknown', message: raw };
        }
        const displayStr = JSON.stringify(parsed, null, 2);
        $('#awp-modal-content').text(displayStr);
        $('#awp-modal').show();
    });

    $('#awp-modal-close').on('click', function() {
        $('#awp-modal').hide();
    });

    $.fn.dataTable.ext.search.push(function(settings, data) {
        const dateStr = data[5] || '';
        let rowTime = 0;
        const nowTime = new Date().getTime();
        if (dateStr) {
            const dateObj = new Date(dateStr.replace(' ', 'T') + 'Z');
            rowTime = dateObj.getTime();
        }
        const op = $('#awp-date-operator').val();
        const val1 = $('#awp-date-value').val();
        const unit = $('#awp-date-unit').val();
        const val2 = $('#awp-date-value2').val();

        if (op === 'last') {
            let mult = 86400000;
            if (unit==='hours') mult = 3600000;
            else if (unit==='months') mult = 2592000000;
            let offset = parseInt(val1,10)*mult;
            if (rowTime < (nowTime-offset)) return false;
        } else if (op === 'equal') {
            const rowDateStr = new Date(rowTime).toDateString();
            if (rowDateStr !== new Date().toDateString()) return false;
        } else if (op === 'between') {
            if (!val2) return true;
            const d1 = new Date(val1).getTime();
            const d2 = new Date(val2).getTime();
            if (rowTime<d1 || rowTime>d2) return false;
        } else if (op === 'onorafter') {
            const start = new Date(val1).getTime();
            if (rowTime<start) return false;
        } else if (op === 'beforeoron') {
            const end = new Date(val1).getTime();
            if (rowTime>end) return false;
        }

        let chosenStatuses = [];
        $('#awp-status-boxes input[type="checkbox"]:checked').each(function() {
            chosenStatuses.push($(this).val().toLowerCase());
        });
        if (chosenStatuses.length > 0) {
            const rowStatusText = $(table.cell(settings.iDataRow, 10).node()).text().trim().toLowerCase();
            if (!chosenStatuses.includes(rowStatusText)) {
                return false;
            }
        }

        let chosenMsgTypes = [];
        $('#awp-msgtype-boxes input[type="checkbox"]:checked').each(function(){
            chosenMsgTypes.push($(this).val());
        });
        if (chosenMsgTypes.length>0) {
            const rowMsg = (data[9] || '').trim();
            if (!chosenMsgTypes.includes(rowMsg)) return false;
        }
        return true;
    });

    $('#awp-apply-columns').on('click', function() {
        let allCheckedCols = 0;
        let totalCols = 14; 
        $('.col-toggle').each(function() {
            const idx = parseInt($(this).data('col'),10);
            const chk = $(this).is(':checked');
            table.column(idx).visible(chk);
            if(chk) allCheckedCols++;
        });
        
        // Count total visible columns (excluding checkbox column) to decide label
        const visibleCols = table.columns(':visible').count();
        if(visibleCols === 11) { // 14 total - 3 hidden by default = 11
            $('#awp-pill-columns .pill-value').text('Default');
        } else {
            $('#awp-pill-columns .pill-value').text('Custom');
        }
        $(this).closest('.filter-popup').hide();
    });

    $('#awp-apply-items').on('click', function() {
        const items = parseInt($('#awp-items-select').val(),10);
        $('#awp-value-items').text(items===-1 ? 'All' : items);
        table.page.len(items).draw();
        $(this).closest('.filter-popup').hide();
    });

    $('#awp-apply-date').on('click', function() {
        table.draw();
        const o = $('#awp-date-operator').val();
        const v = $('#awp-date-value').val();
        const u = $('#awp-date-unit').val();
        let lbl = '';
        if (o==='last') lbl='Last '+v+' '+u;
        else if (o==='equal') lbl='Equal to today';
        else if (o==='between') lbl='Between '+v+' and '+$('#awp-date-value2').val();
        else if (o==='onorafter') lbl='On or after '+v;
        else if (o==='beforeoron') lbl='Before or on '+v;
        $('#awp-value-date').text(lbl);
        $(this).closest('.filter-popup').hide();
    });

    $('#awp-date-operator').on('change', function() {
        const val = $(this).val();
        if(val==='between') {
            $('#awp-date-value').attr('type','date');
            $('#awp-date-unit').hide();
            $('#awp-date-value2').show();
        } else if(['equal','onorafter','beforeoron'].includes(val)) {
            $('#awp-date-value').attr('type','date');
            $('#awp-date-unit').hide();
            $('#awp-date-value2').hide();
        } else {
            $('#awp-date-value').attr('type','number');
            $('#awp-date-unit').show();
            $('#awp-date-value2').hide();
        }
    });

    $('#awp-apply-msgtype').on('click', function() {
        table.draw();
        let arr=[];
        $('#awp-msgtype-boxes input[type="checkbox"]:checked').each(function(){
            arr.push($(this).val());
        });
        if(!arr.length) {
            $('#awp-value-msgtype').text('All');
        } else {
            $('#awp-value-msgtype').text(arr.join(', '));
        }
        $(this).closest('.filter-popup').hide();
    });

    $('#awp-apply-status').on('click', function(){
        table.draw();
        let arr=[];
        $('#awp-status-boxes input[type="checkbox"]:checked').each(function(){
            arr.push($(this).val());
        });
        if(!arr.length) {
            $('#awp-value-status').text('All');
        } else {
            $('#awp-value-status').text(arr.join(', '));
        }
        $(this).closest('.filter-popup').hide();
    });

   let statusHtml = `
      <div class="switch-block"><label class="switch"><input type="checkbox" class="status-chk" value="success"><span class="slider"></span></label><label>Success</label></div>
      <div class="switch-block"><label class="switch"><input type="checkbox" class="status-chk" value="error"><span class="slider"></span></label><label>Error</label></div>
      <div class="switch-block"><label class="switch"><input type="checkbox" class="status-chk" value="failure"><span class="slider"></span></label><label>Failure</label></div>
      <div class="switch-block"><label class="switch"><input type="checkbox" class="status-chk" value="unknown"><span class="slider"></span></label><label>Unknown</label></div>
      <div class="switch-block"><label class="switch"><input type="checkbox" class="status-chk" value="blocked"><span class="slider"></span></label><label>Blocked</label></div>
      <div class="switch-block"><label class="switch"><input type="checkbox" class="status-chk" value="info"><span class="slider"></span></label><label>Info</label></div>
      <div class="switch-block"><label class="switch"><input type="checkbox" class="status-chk" value="need-upgrade"><span class="slider"></span></label><label>Need Upgrade</label></div>
    `;
    $('#awp-status-boxes').html(statusHtml);

    // Dynamically build Message Type checkboxes from the server-provided "message_types"
    if (awpLog.message_types && Array.isArray(awpLog.message_types)) {
        let boxHtml = '';
        awpLog.message_types.forEach(function(m) {
            boxHtml += `
                      <div class="switch-block">
                        <label class="switch">
                          <input type="checkbox" class="msgtype-chk" value="${m}">
                          <span class="slider"></span>
                        </label>
                        <label>${m}</label>
                      </div>
                    `;
        });
        $('#awp-msgtype-boxes').html(boxHtml);
    }

    // "Clear All Filters" button
    $('#awp-clear-filters').on('click', function() {
        searchInput.val('').removeClass('loading-icon').addClass('search-icon');
        table.search('').draw();

        // Reset Items
        $('#awp-items-select').val('20');
        $('#awp-value-items').text('20');
        table.page.len(20).draw();

        // Reset Date
        $('#awp-date-operator').val('last');
        $('#awp-date-value').val('7').attr('type','number');
        $('#awp-date-unit').val('days').show();
        $('#awp-date-value2').hide();
        $('#awp-value-date').text('Last 7 days');

        // Reset Message Type
        $('#awp-msgtype-boxes input[type="checkbox"]').prop('checked', false);
        $('#awp-value-msgtype').text('All');

        // Reset Status
        $('#awp-status-boxes input[type="checkbox"]').prop('checked', false);
        $('#awp-value-status').text('All');

        // Reset Columns to default view
        $('.col-toggle').each(function(){
            const col = $(this).data('col');
            const shouldBeVisible = ![2, 13, 14].includes(col);
            $(this).prop('checked', shouldBeVisible);
            table.column(col).visible(shouldBeVisible);
        });
        
        $('#awp-pill-columns .pill-value').text('Default');

        table.draw();
    });
});
