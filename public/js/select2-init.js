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

    // =========================================================================
    // GLOBAL ENTER KEY PREVENTION: Prevent accidental form submission on Enter
    // =========================================================================
    // On data-entry forms (POST/PUT/PATCH), pressing Enter in an input field
    // should NOT submit the form or clear entered data. User must click Save,
    // or use Tab to navigate from field to field.
    $(document).on('keydown', 'form input:not([type="submit"]):not([type="button"]):not([type="reset"]):not(.select2-search__field)', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            var $form = $(this).closest('form');
            var method = ($form.attr('method') || 'GET').toUpperCase();

            // Allow Enter to trigger search ONLY on GET filter forms
            if (method === 'GET') {
                return true;
            }

            // On all Create / Edit / Data-entry forms: prevent accidental submit!
            e.preventDefault();
            return false;
        }
    });

    // Ensure Select2 dropdown cleanly passes focus on Tab
    $(document).on('keydown', '.select2-container', function (e) {
        if (e.key === 'Tab' || e.keyCode === 9) {
            var $select = $(this).prev('select.select2');
            if ($select.length && $select.data('select2') && $select.data('select2').isOpen()) {
                $select.select2('close');
            }
        }
    });
});
