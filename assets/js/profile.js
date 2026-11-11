
$(function () {

    var token = localStorage.getItem('auth_token');

    if (!token) {
        window.location.replace('login.html');
        return;
    }

    function showAlert(type, msg) {
        $('#alert')
            .removeClass('d-none alert-success alert-danger')
            .addClass('alert-' + type)
            .text(msg);
    }

    function forceLogin() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        window.location.replace('login.html');
    }

    function api(type, data) {
        return $.ajax({
            url: 'php/profile.php',
            type: type,
            dataType: 'json',
            data: data,
            headers: {
                'X-Auth-Token': token
            }
        });
    }

    function render(user, p) {

        user = user || {};
        p = p || {};

        var displayName = p.name || user.username || 'User';

        $('#displayName').text(displayName);
        $('#displayEmail').text(user.email || '');
        $('#avatar').text(displayName.charAt(0).toUpperCase());

        $('#name').val(p.name || '');
        $('#age').val(p.age ?? '');
        $('#dob').val(p.dob || '');
        $('#contact').val(p.contact || '');
        $('#bio').val(p.bio || '');

        $('#interests').val(
            Array.isArray(p.interests)
                ? p.interests.join(', ')
                : ''
        );

        var $badges = $('#interestBadges').empty();

        (Array.isArray(p.interests) ? p.interests : [])
            .forEach(function (interest) {

                $('<span class="badge badge-interest me-1"></span>')
                    .text(interest)
                    .appendTo($badges);

            });
    }

    function load() {

        api('GET')
            .done(function (res) {

                if (!res.success) {
                    showAlert('danger', res.message || 'Could not load profile.');
                    return;
                }

                render(res.user, res.profile);

            })
            .fail(function (xhr) {

                if (xhr.status === 401) {
                    forceLogin();
                    return;
                }

                var msg =
                    (xhr.responseJSON && xhr.responseJSON.message) ||
                    'Could not load profile.';

                showAlert('danger', msg);
            });
    }

    $('#profileForm').on('submit', function (e) {

        e.preventDefault();

        $('#submitBtn')
            .prop('disabled', true)
            .text('Saving...');

        api('POST', {
            name: $.trim($('#name').val()),
            age: $.trim($('#age').val()),
            dob: $('#dob').val(),
            contact: $.trim($('#contact').val()),
            bio: $.trim($('#bio').val()),
            interests: $.trim($('#interests').val())
        })
        .done(function (res) {

            showAlert('success', res.message || 'Profile updated successfully.');
            load();

        })
        .fail(function (xhr) {

            if (xhr.status === 401) {
                forceLogin();
                return;
            }

            var msg =
                (xhr.responseJSON && xhr.responseJSON.message) ||
                'Something went wrong.';

            showAlert('danger', msg);

        })
        .always(function () {

            $('#submitBtn')
                .prop('disabled', false)
                .text('Save profile');

        });

    });

    $('#logoutBtn').on('click', function () {

        $('#logoutBtn').prop('disabled', true);

        api('POST', {
            action: 'logout'
        }).always(function () {
            forceLogin();
        });

    });

    load();

});
