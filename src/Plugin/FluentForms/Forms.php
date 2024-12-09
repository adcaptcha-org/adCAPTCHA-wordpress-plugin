<?php

namespace AdCaptcha\Plugin\FluentForms;

use AdCaptcha\Plugin\FluentForms\AdCaptchaElements;
use AdCaptcha\Plugin\AdCaptchaPlugin;

class Forms extends AdCaptchaPlugin {
    /**
     * Setup
     *
     * @return void
     */
    public function setup(){
      add_action('plugins_loaded', function() {
        add_action('fluentform/loaded', function () {
          new AdCaptchaElements();
        });
      });
    }
}






