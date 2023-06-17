jQuery(document).ready(function($) {
    // Get the collection date field ID from localized script data
    var collectionDateFieldId = clickCollectBranchesOptions.collectionDateFieldId;

    // Function to show or hide the branch field based on the collection date field
    function toggleBranchField() {
        var collectionDateField = $('#' + collectionDateFieldId);
        var branchField = $('#pickup_location');

        if (collectionDateField.length && collectionDateField.val() !== '') {
            branchField.show();
            branchField.prop('required', true);
        } else {
            branchField.hide();
            branchField.prop('required', false);
        }
    }

    // Initial check when the page loads
    toggleBranchField();

    // Check the collection date field when it changes
    $('body').on('change', '#' + collectionDateFieldId, function() {
        toggleBranchField();
    });
});
