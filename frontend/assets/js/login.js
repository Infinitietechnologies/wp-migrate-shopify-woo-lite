jQuery(document).ready(function($) {
    // Auto-fill demo credentials on page load
    function fillDemoCredentials() {
        $('#user_login').val(wmswDemoCredentials.username);
        $('#user_pass').val(wmswDemoCredentials.password);
        $('#rememberme').prop('checked', true);
        
        // Show the credentials display
        $('.credentials-container').show();
        
        // Add a success message
        if (!$('#wmsw-demo-message').length) {
            $('#wmsw-demo-login-container').prepend(
                '<div id="wmsw-demo-message">' +
                '<strong>Demo Mode:</strong> Credentials have been pre-filled. You can now click "Log In" to access the demo account.' +
                '</div>'
            );
        }
        
        // Focus on the login button
        $('#wp-submit').focus();
    }
    
    // Fill credentials immediately when page loads
    fillDemoCredentials();
    
    // Demo login button functionality (for re-filling if user clears fields)
    $('#wmsw-demo-login-btn').on('click', function() {
        fillDemoCredentials();
    });
}); 