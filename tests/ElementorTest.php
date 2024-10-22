<?php
/**
 * ElementorTest.php
 *
 * @package AdCaptcha
 */
use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Elementor\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use WP_Mock as M;

if (!function_exists('is_admin')) {
    function is_admin() {
        global $is_admin;
        return $is_admin;
    }
}

class ElementorTest extends TestCase
{
    private $forms;


    protected function setUp(): void
    {
        parent::setUp();
        M::setUp();
        $this->forms = new Forms();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        M::tearDown();
    }

    public function testGetAdcaptchaName()
    {
       
        // Use reflection to access the protected method
        $reflection = new \ReflectionMethod(Forms::class, 'get_adcaptcha_name');
        $reflection->setAccessible(true); // Make the method accessible

        // Call the method statically since it's a static method
        $result = $reflection->invoke(null); // null because it's static

        // Assert the result is 'adCAPTCHA'
        $this->assertEquals('adCAPTCHA', $result);
        // Check if the method exists in the Forms class
        $this->assertTrue(method_exists($this->forms, 'get_adcaptcha_name'), 'Method get_adcaptcha_name does not exist in Forms class');
    }

    public function testGetSetupMessage()
    {
        $result = $this->forms->get_setup_message();
        $this->assertEquals('Please enter your adCAPTCHA API Key and Placement ID in the adCAPTCHA settings.', $result);
        $this->assertTrue(method_exists($this->forms, 'get_setup_message'), 'Method get_setup_message does not exist in Forms class');
    }
}
