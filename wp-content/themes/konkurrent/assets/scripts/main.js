/* ========================================================================
 * DOM-based Routing
 * Based on http://goo.gl/EUTi53 by Paul Irish
 *
 * Only fires on body classes that match. If a body class contains a dash,
 * replace the dash with an underscore when adding it to the object below.
 *
 * .noConflict()
 * The routing is enclosed within an anonymous function so that you can
 * always reference jQuery with $, even when in .noConflict() mode.
 * ======================================================================== */

/* global kk_script_vars */

(function($) {

  // Use this variable to set up the common and page specific functions. If you
  // rename this variable, you will also need to rename the namespace below.
  var Sage = {
    // All pages
    'common': {
      init: function() {
        // JavaScript to be fired on all pages
        $('.navbar-toggle').on('click', function(e){
            e.preventDefault();
            $('#header-menu').toggleClass('in');
            $('body').find('.navbar-toggle').toggleClass('collapsed').toggleClass('in');
            $('body').toggleClass('menu-open');
            return false;
        });
        
        $('#header-menu').on('click', function(e){
            $('#header-menu').toggleClass('in');
            $('body').find('.navbar-toggle').toggleClass('collapsed').toggleClass('in');
            $('body').toggleClass('menu-open');
        });
        
        $('#contact-form').on('submit', function(e){
            e.preventDefault();
            var name = jQuery(this).find('#name').val();
            var email = jQuery(this).find('#email').val();
            var message = jQuery(this).find('#message').val();
            var $form = $(this);
            var formData = {};
            formData.name = name;
            formData.email = email;
            formData.message = message;
            var flag = true;
            if(name === '') {
                $form.find('#name').parent().find('.error').text('This field is required.').show();
                flag = false;
            } else {
                $('#contact-form').find('#name').parent().find('.error').hide();
            }
            
            var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            if(email === '') {
                $('#contact-form').find('#email').parent().find('.error').text('This field is required.').show();
                flag = false;
            } else if(!email.match(re)) {
                $('#contact-form').find('#email').parent().find('.error').text('Email is invalid.').show();
                flag = false;
            } else {
                $('#contact-form').find('#email').parent().find('.error').hide();
            }
            
            if(message === '') {
                $('#contact-form').find('#message').parent().find('.error').text('This field is required.').show();
                flag = false;
            } else {
                $('#contact-form').find('#message').parent().find('.error').hide();
            }
            
            if(flag) {
                $.ajax({
                    type:'POST',
                    url:kk_script_vars.ajaxurl,
                    dataType:'html',
                    data:{
                        action:'kk_submit_contact_form',
                        formData: formData
                    },
                    success:function(data){
                      $('#name').val('');
                      $('#email').val('');
                      $('#message').val('');
                      $('#contact-form-container .success-message').fadeIn();
                      setTimeout(function(){ $('#contact-form-container .success-message').fadeOut(); }, 3000);
                    }
                });
            }
            
            return false;
        });
        
      },
      finalize: function() {
        // JavaScript to be fired on all pages, after page specific JS is fired
      }
    },
    // Home page
    'home': {
      init: function() {
        // JavaScript to be fired on the home page
      },
      finalize: function() {
        // JavaScript to be fired on the home page, after the init JS
      }
    },
    // About us page, note the change from about-us to about_us.
    'about_us': {
      init: function() {
        // JavaScript to be fired on the about us page
      }
    }
  };

  // The routing fires all common scripts, followed by the page specific scripts.
  // Add additional events for more control over timing e.g. a finalize event
  var UTIL = {
    fire: function(func, funcname, args) {
      var fire;
      var namespace = Sage;
      funcname = (funcname === undefined) ? 'init' : funcname;
      fire = func !== '';
      fire = fire && namespace[func];
      fire = fire && typeof namespace[func][funcname] === 'function';

      if (fire) {
        namespace[func][funcname](args);
      }
    },
    loadEvents: function() {
      // Fire common init JS
      UTIL.fire('common');

      // Fire page-specific init JS, and then finalize JS
      $.each(document.body.className.replace(/-/g, '_').split(/\s+/), function(i, classnm) {
        UTIL.fire(classnm);
        UTIL.fire(classnm, 'finalize');
      });

      // Fire common finalize JS
      UTIL.fire('common', 'finalize');
    }
  };

  // Load Events
  $(document).ready(UTIL.loadEvents);
  
  $(window).load(function(){
      $('#homepage-wrapper .overlay').addClass('in');
  });

})(jQuery); // Fully reference jQuery after this point.
