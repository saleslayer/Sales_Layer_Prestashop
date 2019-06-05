(function ($) {
    
$(function () {
    // queria implementar ajax, pero es igual de lento...
    
    /*
    var saleslayerimport_settings_obj = JSON.parse(saleslayerimport_settings);
    var saleslayerimport_form = $('.sales-layer-import-ajax-form');
    
    if (saleslayerimport_form.length) {
        saleslayerimport_form.on('submit', function (event) {
            event.preventDefault();
            
            var request = $.ajax({
                url: saleslayerimport_settings_obj.ajaxUrl,
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json'
            });

            request.done(function (response) {
                console.log(response);
                
                if (response.hasOwnProperty('action')) {
                    console.log(response);
                    
                    switch(response.action) {
                        case 'import':
                        break;

                        case 'update':
                        break;

                        case 'logout':
                            window.location.reload(true);
                        break;
                    }   
                }
            });

            request.fail(function (xhr, status) {
                console.error(status);
            });       
        });
    }
    */
});
    
}(jQuery));