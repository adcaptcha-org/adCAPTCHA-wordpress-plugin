<?php

namespace AdCaptcha\Plugin;

abstract class AdCaptchaPlugin {

    abstract public function setup();

    public function __construct() {
		$this->setup();
    }
}
