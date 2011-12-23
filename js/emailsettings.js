$(function() {

function toggleIncomingOptions() {
    var enabled = $('#emailpost').prop('checked', true);
    if (enabled) {
        // Note: button style currently does not respond to disabled in our main themes.
        // Graying out the whole section with a 50% transparency will do for now. :)
        // @todo: add a general 'disabled' class style to the base themes.
        $('#emailincoming').css('opacity', '')
                           .find('input').prop('disabled', false);
    } else {
        $('#emailincoming').css('opacity', '0.5')
                           .find('input').prop('disabled', true);
    }
}

toggleIncomingOptions();

$('#emailpost').click(function() {
    toggleIncomingOptions();
});

});
