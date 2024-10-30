(function ($) {
    $(document).ready(function () {
        $("#ScanToggleDiv").click(function () {
            if ($("#lightningimport_ScanToggle").is(':checked')) {                
                $('#lightningimport_ScanToggle').prop('checked', false);                
                $('#lightningimport_ScanToggle').val("0");
            }
            else {                
                $('#lightningimport_ScanToggle').prop('checked', true);                
                $('#lightningimport_ScanToggle').val("1");
            }
        });
		
		$("#SearchToggleDiv").click(function () {
            if ($("#lightningimport_SearchWidgetToggle").is(':checked')) {                
                $('#lightningimport_SearchWidgetToggle').prop('checked', false);                
                $('#lightningimport_SearchWidgetToggle').val("0");
            }
            else {                
                $('#lightningimport_SearchWidgetToggle').prop('checked', true);                
                $('#lightningimport_SearchWidgetToggle').val("1");
            }
        });
		
		$("#lightningimport_debugToggleDiv").click(function () {
            if ($("#lightningimport_debug").is(':checked')) {                
                $('#lightningimport_debug').prop('checked', false);                
                $('#lightningimport_debug').val("0");
            }
            else {                
                $('#lightningimport_debug').prop('checked', true);                
                $('#lightningimport_debug').val("1");
            }
        });
    });
} (jQuery));

