define(['jquery'], function($) {
    return {
        init: function() {
            $(document).ready(function() {

                $("#date-start").change(function() {
                    var start = $('#date-start').val();
                    $('#date-end').attr('min', start)
                });

                $("#clear-filter").click(function() {
                    $('#date-start').val("");
                    $('#date-end').val("");
                });
            });
        }
    };
});