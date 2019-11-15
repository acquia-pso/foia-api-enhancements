(function ($, drupalSettings, Drupal) {
  Drupal.behaviors.autocalcFields = {
    attach: function attach() {
      var autocalcSettings = drupalSettings.foiaAutocalc.autocalcSettings;
      Object.keys(autocalcSettings).forEach(function(fieldName, fieldIndex) {
        var fieldSettings = autocalcSettings[fieldName];
        fieldSettings.forEach(function(fieldSetting) {
          var fieldSelector = convertToFieldSelector(fieldSetting);
          $(fieldSelector + ' input').each(function(index) {
            // Calculate field on initial form load.
            $(fieldSelector + ' input').each(function(index) {
              calculateField(fieldName, fieldSettings);
            });
            // Bind event listeners to calculate field when input fields are changed.
            $(this).once(fieldSelector + '_' + fieldIndex + '_' + index).on('change', function() {
              calculateField(fieldName, fieldSettings);
            });
          });
        });
      });
    }
  };

  // Determines the value of the automatically calculated field.
  function calculateField(fieldName, fieldSettings) {
    var totalValues = {};
    var idSelector = '';
    var isTotalNA = true;

    fieldSettings.forEach(function (fieldSetting) {
      $(convertToFieldSelector(fieldSetting) + ' input').each(function() {
        var value = $(this).val();
        var selectedValue = 0;
        if (String(value).toLowerCase() === "n/a") {
          var selectedValue = null;
        }
        else {
          isTotalNA = false;
          if ( isNumeric(value) ) {
            selectedValue = Number($(this).val());
          }
        }

        // Get the selector for this field.
        if (fieldSetting.hasOwnProperty('this_entity') && fieldSetting.this_entity) {
          var index = $(this).attr('name').match(/\[(.*?)\]/)[1];
          idSelector = 'edit-' + fieldSetting.field.replace(/_/g, '-') + '-' + index;
        }
        else {
          idSelector = 'all';
        }

        // Add value to the selector.
        if (totalValues.hasOwnProperty(idSelector)) {
          if(selectedValue !== null) {
            totalValues[idSelector] += selectedValue;
          }
        }
        else {
          totalValues[idSelector] = selectedValue;
        }
      });
    });

    Object.keys(totalValues).forEach(function (selector) {
      // Set overall value to "N/A" if all fields are "N/A".
      if(isTotalNA) {
        totalValues[selector] = "N/A";
      }
      if (selector == 'all') {
        $(convertToFieldSelector({ field: fieldName }) + ' input').val(totalValues[selector]).trigger('change');
      }
      else {
        $('div[data-drupal-selector="' + selector + '"] ' + convertToFieldSelector({ field: fieldName }) + ' input').val(totalValues[selector]).trigger('change');
      }
    });
  }

  // Converts field autocalc settings to a jQuery selector.
  function convertToFieldSelector(fieldSetting) {
    var selector = '.field--name-' + fieldSetting.field.replace(/_/g, '-');
    if (fieldSetting.hasOwnProperty('subfield')) {
      selector += ' ' + convertToFieldSelector(fieldSetting.subfield);
    }
    return selector;
  }

  function isNumeric(n) {
    return !isNaN(parseFloat(n)) && isFinite(n);
  }

})(jQuery, drupalSettings, Drupal);
