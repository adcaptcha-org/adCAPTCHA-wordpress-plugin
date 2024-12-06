<?php
/**
 * PasswordResetTest
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\Woocommerce;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Woocommerce\PasswordReset;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class PasswordResetTest extends TestCase
{
    private $verifyMock;
    private $forms;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];
        Functions\when('sanitize_text_field')->justReturn('invalid_token'); 
        Functions\when('wp_unslash')->justReturn('invalid_token'); 

        $this->forms = new PasswordReset();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    
    public function testSetup() {
        $this->forms->setup();
        global $mocked_actions, $mocked_filters;
      var_dump($mocked_filters);
        $this->assertTrue(method_exists($this->forms, 'setup'));
    
    }
}