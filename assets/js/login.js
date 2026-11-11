
$(function () {

    // Redirect if an authentication token already exists
    if (localStorage.getItem('auth_token')) {
        window.location.replace('profile.html');
        return;
    }

    function showAlert(type, msg) {
        $('#alert')
            .removeClass('d-none alert-success alert-danger')
            .addClass('alert-' + type)
            .text(msg);
    }

    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        var identity = $.trim($('#identity').val());
        var password = $('#password').val();

        if (!identity || !password) {
            return showAlert(
                'danger',
                'Enter your username/email and password.'
            );
        }

        $('#submitBtn')
            .prop('disabled', true)
            .text('Signing in...');

        $.ajax({
            url: 'php/login.php',
            type: 'POST',
            dataType: 'json',
            data: {
                identity: identity,
                password: password
            }
        })
        .done(function (res) {

            if (!res.success || !res.token) {
                showAlert('danger', res.message || 'Login failed.');

                $('#submitBtn')
                    .prop('disabled', false)
                    .text('Log in');

                return;
            }

            localStorage.setItem('auth_token', res.token);
            localStorage.setItem('auth_user', res.username);

            window.location.replace('profile.html');
        })
        .fail(function (xhr) {

            var msg =
                (xhr.responseJSON && xhr.responseJSON.message) ||
                'Something went wrong. Please try again.';

            showAlert('danger', msg);

            $('#submitBtn')
                .prop('disabled', false)
                .text('Log in');
        });

    });

});
