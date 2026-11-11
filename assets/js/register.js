
$(function () {

    function showAlert(type, msg) {
        $('#alert')
            .removeClass('d-none alert-success alert-danger')
            .addClass('alert-' + type)
            .text(msg);
    }

    $('#registerForm').on('submit', function (e) {
        e.preventDefault();

        var username = $.trim($('#username').val());
        var email = $.trim($('#email').val());
        var password = $('#password').val();
        var confirm = $('#confirm').val();

        // Username validation
        if (!/^[A-Za-z0-9_]{3,30}$/.test(username)) {
            return showAlert(
                'danger',
                'Username must be 3-30 letters, numbers or underscores.'
            );
        }

        // Email validation
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return showAlert('danger', 'Enter a valid email address.');
        }

        // Password validation
        if (password.length < 8 || password.length > 72) {
            return showAlert(
                'danger',
                'Password must be 8-72 characters.'
            );
        }

        // Confirm password
        if (password !== confirm) {
            return showAlert('danger', 'Passwords do not match.');
        }

        $('#submitBtn')
            .prop('disabled', true)
            .text('Creating...');

        $.ajax({
            url: 'php/register.php',
            type: 'POST',
            dataType: 'json',
            data: {
                username: username,
                email: email,
                password: password,
                confirm: confirm
            }
        })
        .done(function (res) {

            showAlert('success', res.message);

            setTimeout(function () {
                window.location.href = 'login.html';
            }, 1200);

        })
        .fail(function (xhr) {

            var msg =
                (xhr.responseJSON && xhr.responseJSON.message) ||
                'Something went wrong. Please try again.';

            showAlert('danger', msg);

            $('#submitBtn')
                .prop('disabled', false)
                .text('Register');
        });

    });

});
