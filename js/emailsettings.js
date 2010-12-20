$(function() {

function toggleIncomingOptions() {
    var enabled = $('#emailpost').attr('checked');
    if (enabled) {
        // Note: button style currently does not respond to disabled in our main themes.
        // Graying out the whole section with a 50% transparency will do for now. :)
        // @todo: add a general 'disabled' class style to the base themes.
        $('#emailincoming').removeAttr('style')
                           .find('input').removeAttr('disabled');
    } else {
        $('#emailincoming').attr('style', 'opacity: 0.5')
                           .find('input').attr('disabled', 'disabled');
    }
}

toggleIncomingOptions();

$('#emailpost').click(function() {
    toggleIncomingOptions();
});

});
