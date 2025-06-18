jQuery(document).ready(function($) {
    
    // Clear cache button
    $('#scf-clear-cache').on('click', function() {
        var button = $(this);
        
        if (!confirm(scfShortcodes.strings.confirmClearCache)) {
            return;
        }
        
        button.prop('disabled', true).text('Clearing...');
        
        $.ajax({
            url: scfShortcodes.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scf_clear_cache',
                nonce: scfShortcodes.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(scfShortcodes.strings.cacheCleared, 'success');
                    // Refresh cache stats
                    location.reload();
                } else {
                    showNotice(response.data.message || scfShortcodes.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(scfShortcodes.strings.error, 'error');
            },
            complete: function() {
                button.prop('disabled', false).text('Clear All Cache');
            }
        });
    });
    
    // Clear errors button
    $('#scf-clear-errors').on('click', function() {
        var button = $(this);
        
        button.prop('disabled', true).text('Clearing...');
        
        $.ajax({
            url: scfShortcodes.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scf_clear_errors',
                nonce: scfShortcodes.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.scf-admin-box').has('.scf-error-log').fadeOut();
                    showNotice('Error log cleared successfully!', 'success');
                } else {
                    showNotice(response.data.message || scfShortcodes.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(scfShortcodes.strings.error, 'error');
            },
            complete: function() {
                button.prop('disabled', false).text('Clear Error Log');
            }
        });
    });
    
    // Show notice function
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'scf-success' : 'scf-error';
        var notice = $('<div class="' + noticeClass + '">' + message + '</div>');
        
        $('.scf-admin-container').before(notice);
        
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 3000);
    }
    
    // Field info lookup (for future enhancement)
    $('.scf-field-lookup').on('click', function() {
        var fieldName = prompt('Enter field name:');
        
        if (!fieldName) {
            return;
        }
        
        $.ajax({
            url: scfShortcodes.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scf_get_field_info',
                field_name: fieldName,
                nonce: scfShortcodes.nonce
            },
            success: function(response) {
                if (response.success) {
                    var info = response.data;
                    var message = 'Field Info:\n';
                    message += 'Label: ' + (info.label || 'N/A') + '\n';
                    message += 'Type: ' + (info.type || 'N/A') + '\n';
                    message += 'Append: ' + (info.append || 'N/A') + '\n';
                    message += 'Prepend: ' + (info.prepend || 'N/A');
                    
                    alert(message);
                } else {
                    alert(response.data.message || scfShortcodes.strings.error);
                }
            },
            error: function() {
                alert(scfShortcodes.strings.error);
            }
        });
    });
});
