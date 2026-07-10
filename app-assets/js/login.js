
$(document).ready(function() {
    var form = $('#form'); // contact form
    var submit = $('#submit'); // submit button

    // Function to validate the form inputs
    function validateForm() {
        var username = $('#username').val().trim(); // get the username value
        var password = $('#password').val().trim(); // get the password value
        var isValid = true;

        // Basic validation: check if username and password fields are not empty
        if (username === "") {
            toastr.error('Username is required!');
            isValid = false;
        }

        if (password === "") {
            toastr.error('Password is required!');
            isValid = false;
        }

        return isValid; // return true if form is valid
    }

    // form submit event
    form.on('submit', function(e) {
        e.preventDefault(); // prevent default form submission

        // Perform validation before AJAX request
        if (validateForm()) {
            // sending ajax request through jQuery
            $.ajax({
                url: 'login_action.php', // form action url
                type: 'POST', // form submit method get/post
                dataType: 'html', // request type html/json/xml
                data: form.serialize(), // serialize form data
                beforeSend: function() {
                    submit.html('<i class="fa fa-spinner fa-spin"></i> Signing in, please wait'); // change submit button text
                },
                success: function(data) {
                    // Handle server response
                    if (data == 0) {
                        toastr.error('Error!! Invalid credentials.');
                    } else {
                        toastr.success('Login Success');
                        window.location = 'dashboard?menuslug=dashboard';
                    }

                    submit.html('Login Now'); // reset submit button text
                },
                error: function(e) {
                    toastr.error('An error occurred, please try again.');
                    console.log(e);
                }
            });
        }
    });
});
