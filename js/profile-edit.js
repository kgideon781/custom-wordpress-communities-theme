jQuery(document).ready(function($) {
    $('#profile-edit-form').on('submit', function(event) {
        event.preventDefault();

        var displayName = $('#display_name').val();
        var userEmail = $('#user_email').val();

        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'update_profile',
                display_name: displayName,
                user_email: userEmail,
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                } else {
                    alert(response.data);
                }
            }
        });
    });
});
