(function($) {
  'use strict';

  var wpcbm_media;
  var $badges = $('#wpcbm-preview .wpcbm-badges');
  var $badge = $('#wpcbm-preview .wpcbm-badge');

  $(function() {
    // ready
    wpcbm_toggle_type();
    wpcbm_terms_select();
    wpcbm_badges_select();
    wpcbm_conditional_init();
    wpcbm_conditional_select();
    wpcbm_timer_init();
    wpcbm_timer_picker();

    // init style
    $('input[name="wpcbm_style"]:checked').trigger('change');

    // init roles
    $('.wpcbm_roles').selectWoo();

    // hide old wpc_group_badge
    $('a[href="edit-tags.php?taxonomy=wpc_group_badge&post_type=wpc_product_badge"]').
        closest('li').
        remove();
  });

  $(document).on('change', '.wpcbm_time_type', function() {
    wpcbm_timer_init($(this).closest('.wpcbm_time'));
  });

  $(document).on('click touch', '.wpcbm_new_time', function(e) {
    var $this = $(this);
    var data = {
      action: 'wpcbm_add_time', nonce: wpcbm_vars.nonce,
    };

    $this.addClass('disabled');
    $('.wpcbm_timer').addClass('wpcbm_timer_loading');

    $.post(ajaxurl, data, function(response) {
      $('.wpcbm_timer').append(response);
      wpcbm_timer_init();
      wpcbm_timer_picker();
      $('.wpcbm_timer').removeClass('wpcbm_timer_loading');
      $this.removeClass('disabled');
    });

    e.preventDefault();
  });

  $(document).on('click touch', '.wpcbm_time_remove', function(e) {
    e.preventDefault();

    if (confirm('Are you sure?')) {
      $(this).closest('.wpcbm_time').remove();
    }
  });

  $(document).
      on('change',
          '.wpcbm_time select:not(.wpcbm_time_type), .wpcbm_time input:not(.wpcbm_time_val)',
          function() {
            var val = $(this).val();
            var show = $(this).
                closest('.wpcbm_time').
                find('.wpcbm_time_type').
                find(':selected').
                data('show');

            $(this).
                closest('.wpcbm_time').
                find('.wpcbm_time_val').data(show, val).
                val(val).
                trigger('change');
          });

  $(document).on('click touch', '.wpcbm-activate-btn', function(e) {
    e.preventDefault();

    let $this = $(this), id = $this.data('id'),
        act = $this.hasClass('deactivate') ? 'deactivate' : 'activate';

    $.ajax({
      url: ajaxurl, method: 'POST', data: {
        action: 'wpcbm_activate', nonce: wpcbm_vars.nonce, id: id, act: act,
      }, dataType: 'html', beforeSend: function() {
        $this.addClass('updating');
      }, complete: function() {
        $this.removeClass('updating');
      }, success: function(response) {
        if (response === 'activate') {
          $this.removeClass('activate').addClass('deactivate button-primary');
        } else if (response === 'deactivate') {
          $this.removeClass('deactivate button-primary').addClass('activate');
        }
      },
    });
  });

  $('#wpcbm_add_image').on('click touch', function() {
    wpcbm_media = wp.media({
      title: 'Select or Upload Image', button: {
        text: 'Use this image',
      }, multiple: false,
    });

    wpcbm_media.on('select', function() {
      var attachment = wpcbm_media.state().get('selection').first().toJSON();

      $('#wpcbm_image').val(attachment.id);
      $('#wpcbm_image_img').attr('src', attachment.url).removeClass('hidden');
      $('#wpcbm_preview .wpcbm-badge .wpcbm-badge-inner').
          html('<img src="' + attachment.url + '"/>');
    });

    wpcbm_media.open();
  });

  $('#wpcbm_remove_image').on('click touch', function(e) {
    e.preventDefault();
    $('#wpcbm_image_img').addClass('hidden');
    $('#wpcbm_image').val('');
    $('#wpcbm_preview .wpcbm-badge .wpcbm-badge-inner').html('');
  });

  $('#wpcbm_position').on('change', function() {
    let $this = $(this);
    wpcbm_remove_class_prefix($badges, 'wpcbm-badges-');
    $badges.addClass('wpcbm-badges-' + $this.val());
  });

  $('#wpcbm_styles input[type=radio]').on('change', function() {
    var $this = $(this);
    var allow = $this.attr('data-allow');
    var allow_json = jQuery.parseJSON(allow);

    $('.wpcbm_configuration_tr_allow').hide();

    if (allow_json !== null) {
      for (var key in allow_json['allow']) {
        $('.wpcbm_configuration_tr_' + key).show();

        // apply default values
        var df_value = $this.data(key);

        if (df_value == undefined) {
          df_value = allow_json['allow'][key];
        }

        $('input[name="wpcbm_' + key + '"]').val(df_value).trigger('change');
      }
    }

    wpcbm_remove_class_prefix($badge, 'wpcbm-badge-style-');
    $badge.addClass('wpcbm-badge-style-' + this.value);

    if (this.value === 'image') {
      $badge.find('.wpcbm-badge-inner').
          html('<img src="' + $('#wpcbm_image_img').attr('src') + '"/>');
    } else {
      var txt = $('#wpcbm_text').val().replace(/\[(.+?)\]/g, '[#]');

      if (txt !== '') {
        $badge.find('.wpcbm-badge-inner').html(txt);
      } else {
        $badge.find('.wpcbm-badge-inner').html('ABC');
      }

      $badge.css('color', $('#wpcbm_text_color').val());
      $badge.css('backgroundColor', $('#wpcbm_background_color').val());
      $badge.css('borderColor', $('#wpcbm_border_color').val());
    }
  });

  $('#wpcbm_text').on('keyup change', function() {
    var style = $('input[name="wpcbm_style"]:checked').val();
    var txt = this.value.replace(/\[(.+?)\]/g, '[#]');

    if (txt === '') {
      txt = 'ABC';
    }

    $badge.find('.wpcbm-badge-inner').html(txt);
    $('#wpcbm_style_' + style).data('text', txt);
  });

  $('#wpcbm_text_color').wpColorPicker({
    change: function(event, ui) {
      var style = $('input[name="wpcbm_style"]:checked').val();
      var color = ui.color.toCSS('rgba');

      $badge.css('color', color);
      $('#wpcbm_style_' + style).data('text_color', color);
    },
  });

  $('#wpcbm_background_color').wpColorPicker({
    change: function(event, ui) {
      var style = $('input[name="wpcbm_style"]:checked').val();
      var color = ui.color.toCSS('rgba');

      $badge.css('backgroundColor', color);
      $('#wpcbm_style_' + style).data('background_color', color);
    },
  });

  $('#wpcbm_border_color').wpColorPicker({
    change: function(event, ui) {
      var style = $('input[name="wpcbm_style"]:checked').val();
      var color = ui.color.toCSS('rgba');

      $badge.css('borderColor', color);
      $('#wpcbm_style_' + style).data('border_color', color);
    },
  });

  $('#wpcbm_box_shadow').wpColorPicker({
    change: function(event, ui) {
      var style = $('input[name="wpcbm_style"]:checked').val();
      var color = ui.color.toCSS('rgba');

      $badge.css('box-shadow', '4px 4px ' + color);
      $('#wpcbm_style_' + style).data('box_shadow', color);
    }, clear: function(event, ui) {
      $badge.css('box-shadow', '');
      $('#wpcbm_style_' + style).data('box_shadow', '');
    },
  });

  $('#wpcbm_tooltip').on('keyup change', function() {
    $badge.attr('aria-label', this.value);
  });

  $('#wpcbm_tooltip_position').on('change', function() {
    let $this = $(this);
    wpcbm_remove_class_prefix($badge, 'hint--');
    $badge.addClass('hint--' + $this.val());
  });

  $('#wpcbm_apply').on('change', function() {
    var apply = $(this).val();
    var $terms = $('#wpcbm_terms');

    $('#wpcbm_configuration_combination').hide();
    $('#wpcbm_configuration_categories').hide();
    $('#wpcbm_configuration_tags').hide();
    $('#wpcbm_configuration_terms').hide();

    if (apply === '' || apply === 'none' || apply === 'all' || apply ===
        'sale' || apply === 'featured' || apply === 'bestselling' || apply ===
        'instock' || apply === 'outofstock' || apply === 'backorder') {
      return;
    }

    if (apply === 'combination') {
      $('#wpcbm_configuration_combination').show();
      return;
    }

    if (apply === 'categories') {
      $('#wpcbm_configuration_categories').show();
      return;
    }

    if (apply === 'tags') {
      $('#wpcbm_configuration_tags').show();
      return;
    }

    $('#wpcbm_configuration_terms').show();

    if ((typeof $terms.data(apply) === 'string' || $terms.data(apply) instanceof
        String) && $terms.data(apply) !== '') {
      $terms.val($terms.data(apply).split(',')).change();
    } else {
      $terms.val([]).change();
    }

    wpcbm_terms_select();
  });

  $(document).on('change', '.wpcbm_conditional_apply', function() {
    wpcbm_conditional_init();
    wpcbm_conditional_select();
  });

  $(document).on('click touch', '#wpcbm_icons_btn', function(e) {
    e.preventDefault();

    $('#wpcbm_dialog_icons').
        dialog({
          minWidth: 460,
          modal: true,
          dialogClass: 'wpc-dialog',
          open: function() {
            $('.ui-widget-overlay').bind('click', function() {
              $('#wpcbm_dialog_icons').dialog('close');
            });
          },
        });
  });

  $(document).on('click touch', '#wpcbm_shortcodes_btn', function(e) {
    e.preventDefault();

    $('#wpcbm_dialog_shortcodes').
        dialog({
          minWidth: 460,
          modal: true,
          dialogClass: 'wpc-dialog',
          open: function() {
            $('.ui-widget-overlay').bind('click', function() {
              $('#wpcbm_dialog_shortcodes').dialog('close');
            });
          },
        });
  });

  $(document).on('click touch', '.wpcbm_add_conditional', function(e) {
    e.preventDefault();

    var $this = $(this);

    $this.addClass('disabled');

    var data = {
      action: 'wpcbm_add_conditional', nonce: wpcbm_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('.wpcbm_conditionals').append(response);
      wpcbm_conditional_init();
      $this.removeClass('disabled');
    });
  });

  $(document).on('click touch', '.wpcbm_conditional_remove', function(e) {
    e.preventDefault();

    $(this).closest('.wpcbm_conditional').remove();
  });

  $(document).on('change', 'input[name="wpcbm_type"]', function() {
    wpcbm_toggle_type();
  });

  $(document).on('change', '.wpcbm_conditional_value', function() {
    var apply = $(this).
        closest('.wpcbm_conditional').
        find('.wpcbm_conditional_apply').
        val();

    $(this).data(apply, $(this).val());
  });

  $(document).on('change', '.wpcbm_conditional_select', function() {
    var apply = $(this).
        closest('.wpcbm_conditional').
        find('.wpcbm_conditional_apply').
        val();

    $(this).data(apply, $(this).val().join());
  });

  $(document).on('change', '#wpcbm_terms', function() {
    var apply = $('#wpcbm_apply').val();

    $(this).data(apply, $(this).val().join());
  });

  function wpcbm_terms_select() {
    var apply = $('#wpcbm_apply').val();
    var label = $('#wpcbm_apply').find(':selected').text().trim();

    $('#wpcbm_configuration_terms_label').html(label);

    $('#wpcbm_terms').selectWoo({
      ajax: {
        url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
          return {
            q: params.term,
            action: 'wpcbm_search_term',
            nonce: wpcbm_vars.nonce,
            taxonomy: apply,
          };
        }, processResults: function(data) {
          var options = [];
          if (data) {
            $.each(data, function(index, text) {
              options.push({id: text[0], text: text[1]});
            });
          }
          return {
            results: options,
          };
        }, cache: true,
      }, minimumInputLength: 1,
    });
  }

  function wpcbm_badges_select() {
    $('#wpcbm_badges').selectWoo({
      ajax: {
        url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
          return {
            action: 'wpcbm_search_badges',
            nonce: wpcbm_vars.nonce,
            q: params.term,
          };
        }, processResults: function(data) {
          var options = [];
          if (data) {
            $.each(data, function(index, text) {
              options.push({id: text[0], text: text[1]});
            });
          }
          return {
            results: options,
          };
        }, cache: true,
      }, minimumInputLength: 1, select: function(e) {
        var element = e.params.data.element;
        var $element = $(element);

        $(this).append($element);
        $(this).trigger('change');
      },
    });
  }

  function wpcbm_timer_init($time) {
    if (typeof $time !== 'undefined') {
      var show = $time.find('.wpcbm_time_type').find(':selected').data('show');
      var $val = $time.find('.wpcbm_time_val');

      if ($val.data(show) !== undefined) {
        $val.val($val.data(show)).trigger('change');
      } else {
        $val.val('').trigger('change');
      }

      $time.find('.wpcbm_hide').hide();
      $time.find('.wpcbm_show_if_' + show).
          show();
    } else {
      $('.wpcbm_time').each(function() {
        var show = $(this).
            find('.wpcbm_time_type').
            find(':selected').
            data('show');
        var $val = $(this).find('.wpcbm_time_val');

        $val.data(show, $val.val());

        $(this).find('.wpcbm_hide').hide();
        $(this).find('.wpcbm_show_if_' + show).show();
      });
    }
  }

  function wpcbm_timer_picker() {
    $('.wpcbm_dpk_date_time:not(.wpcbm_dpk_init)').wpcdpk({
      timepicker: true, onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcbm_time').
            find('.wpcbm_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcbm_time').
            find('.wpcbm_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcbm_dpk_init');

    $('.wpcbm_dpk_date:not(.wpcbm_dpk_init)').wpcdpk({
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcbm_time').
            find('.wpcbm_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcbm_time').
            find('.wpcbm_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcbm_dpk_init');

    $('.wpcbm_dpk_date_range:not(.wpcbm_dpk_init)').wpcdpk({
      range: true,
      multipleDatesSeparator: ' - ',
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcbm_time').
            find('.wpcbm_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcbm_time').
            find('.wpcbm_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcbm_dpk_init');

    $('.wpcbm_dpk_date_multi:not(.wpcbm_dpk_init)').wpcdpk({
      multipleDates: 5,
      multipleDatesSeparator: ', ',
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcbm_time').
            find('.wpcbm_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcbm_time').
            find('.wpcbm_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcbm_dpk_init');

    $('.wpcbm_dpk_time:not(.wpcbm_dpk_init)').wpcdpk({
      timepicker: true,
      onlyTimepicker: true,
      classes: 'only-time',
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcbm_time').
            find('.wpcbm_time_type').
            find(':selected').
            data('show');

        if (dpk.$el.hasClass('wpcbm_time_from') ||
            dpk.$el.hasClass('wpcbm_time_to')) {
          var time_range = dpk.$el.closest('.wpcbm_time').
                  find('.wpcbm_time_from').val() + ' - ' +
              dpk.$el.closest('.wpcbm_time').
                  find('.wpcbm_time_to').val();

          dpk.$el.closest('.wpcbm_time').
              find('.wpcbm_time_val').
              data(show, time_range).
              val(time_range).
              trigger('change');
        } else {
          dpk.$el.closest('.wpcbm_time').
              find('.wpcbm_time_val').data(show, fd).val(fd).trigger('change');
        }
      },
    }).addClass('wpcbm_dpk_init');
  }

  function wpcbm_conditional_init() {
    $('.wpcbm_conditional_apply').each(function() {
      var $this = $(this);
      var $value = $this.closest('.wpcbm_conditional').
          find('.wpcbm_conditional_value');
      var $select_wrap = $this.closest('.wpcbm_conditional').
          find('.wpcbm_conditional_select_wrap');
      var $select = $this.closest('.wpcbm_conditional').
          find('.wpcbm_conditional_select');
      var $compare = $this.closest('.wpcbm_conditional').
          find('.wpcbm_conditional_compare');
      var apply = $this.val();
      var compare = $compare.val();

      if (apply === 'sale' || apply === 'featured' || apply === 'bestselling' ||
          apply === 'instock' || apply === 'outofstock' || apply ===
          'backorder') {
        $compare.hide();
        $value.hide();
        $select_wrap.hide();
      } else {
        $compare.show();

        if (apply === 'price' || apply === 'rating' || apply === 'release' ||
            apply === 'stock') {
          $select_wrap.hide();
          $value.show();
          $compare.find('.wpcbm_conditional_compare_price option').
              prop('disabled', false);
          $compare.find('.wpcbm_conditional_compare_terms option').
              prop('disabled', true);

          if (compare === 'is' || compare === 'is_not') {
            $compare.val('equal').trigger('change');
          }
        } else {
          $select_wrap.show();
          $value.hide();
          $compare.find('.wpcbm_conditional_compare_price option').
              prop('disabled', true);
          $compare.find('.wpcbm_conditional_compare_terms option').
              prop('disabled', false);

          if (compare !== 'is' && compare !== 'is_not') {
            $compare.val('is').trigger('change');
          }
        }
      }

      if ($value.data(apply) !== '') {
        $value.val($value.data(apply));
      }

      if ((typeof $select.data(apply) === 'string' ||
              $select.data(apply) instanceof String) && $select.data(apply) !==
          '') {
        $select.val($select.data(apply).split(',')).change();
      } else {
        $select.val([]).change();
      }
    });
  }

  function wpcbm_conditional_select() {
    $('.wpcbm_conditional_select').each(function() {
      var $this = $(this);
      var apply = $this.closest('.wpcbm_conditional').
          find('.wpcbm_conditional_apply').
          val();

      $this.selectWoo({
        ajax: {
          url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
            return {
              action: 'wpcbm_search_term',
              nonce: wpcbm_vars.nonce,
              q: params.term,
              taxonomy: apply,
            };
          }, processResults: function(data) {
            var options = [];
            if (data) {
              $.each(data, function(index, text) {
                options.push({id: text[0], text: text[1]});
              });
            }
            return {
              results: options,
            };
          }, cache: true,
        }, minimumInputLength: 1,
      });
    });
  }

  function wpcbm_toggle_type() {
    $('input[name="wpcbm_type"]:checked').each(function() {
      var val = $(this).val();

      if (val === 'overwrite' || val === 'prepend' || val === 'append') {
        $(this).
            closest('.wpcbm_table').
            find('.wpcbm_show_if_overwrite').show();
      } else {
        $(this).
            closest('.wpcbm_table').
            find('.wpcbm_show_if_overwrite').hide();
      }
    });
  }

  function wpcbm_remove_class_prefix($e, prefix) {
    $e.removeClass(function(index, className) {
      return (className.match(new RegExp('(^|\\s)' + prefix + '\\S+', 'g')) ||
          []).join(' ');
    });
  }
})(jQuery);
