$(document).ready(function () {
    if (typeof $.fn.select2 === 'undefined') {
        return;
    }

    function initSelect2(context) {
        var $scope = context ? $(context) : $(document);
        $scope.find('select.select2').each(function () {
            var $this = $(this);
            if (!$this.hasClass('select2-hidden-accessible')) {
                var placeholderText = $this.attr('placeholder') || 
                                     $this.data('placeholder') || 
                                     $this.find('option[value=""]').text() || 
                                     '-- Select --';
                
                var hasEmptyOption = Boolean($this.find('option[value=""]').length);

                $this.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder: placeholderText,
                    allowClear: hasEmptyOption
                });
            }
        });
    }

    initSelect2();

    // Re-initialize when Bootstrap modals are opened
    $(document).on('shown.bs.modal', function () {
        initSelect2(this);
    });

    // Global custom event for dynamically added rows or AJAX content
    $(document).on('reinit:select2', function (e, target) {
        initSelect2(target || document);
    });
});
