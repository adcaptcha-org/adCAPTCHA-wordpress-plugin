<?php
/**
 * WooCommerce Registration Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\Woocommerce;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Woocommerce\Registration;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class RegistrationTest extends TestCase {

    private $registration;
    private $verifyRegistrationMock;
    
    protected function setUp(): void {

        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];

        $this->verifyRegistrationMock = $this->createMock(Verify::class);

        $this->registration = new Registration($this->verifyRegistrationMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testSetup() {
        $this->assertTrue(method_exists($this->registration, 'setup'));
        global $mocked_actions, $mocked_filters;
        $this->registration->setup();
        var_dump($mocked_actions);
    }
}
