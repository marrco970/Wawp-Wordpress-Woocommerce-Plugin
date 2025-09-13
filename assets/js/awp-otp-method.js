jQuery(document).ready(function ($) {
    // Handle icon click
    $('.awp-icon-option').on('click', function () {
        // Remove 'selected' class from all options
        $('.awp-icon-option').removeClass('selected');
        $('.awp-icon-option input[type="radio"]').prop('checked', false); // Uncheck all radio buttons

        // Add 'selected' class to the clicked option
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true); // Check the clicked radio button
    });
});
