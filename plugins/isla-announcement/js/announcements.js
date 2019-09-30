jQuery(document).ready(function($) {
    if ($('#isla_start_date').length) {
        $(function () {
            var pickerOpts = {
                dateFormat: "yy-mm-dd"
            };
            $("#isla_start_date").datepicker(pickerOpts);
            $("#isla_end_date").datepicker(pickerOpts);

        });
    }
});
