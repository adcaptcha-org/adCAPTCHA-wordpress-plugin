<?php
/**
 * ContactForm7Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\ContactForm7;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\ContactForm7\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class ContactForm7Test extends TestCase {
    private $forms;
    private $verifyMock;

    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];

        $this->forms = new Forms();
    }

    public function tearDown(): void {
        global $mocked_filters;
        $mocked_filters = [];
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testSetup() {
        $this->assertTrue(method_exists($this->forms, 'setup'), 'Method setup does not exist');

        global $mocked_actions, $mocked_filters;

        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [$this->forms, 'block_submission'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [$this->forms, 'get_success_token'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [$this->forms, 'reset_captcha_script'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wpcf7_form_elements', 'callback' => [$this->forms, 'captcha_trigger_filter'], 'priority' => 20, 'accepted_args' => 1 ], $mocked_filters);
        $this->assertContains(['hook' => 'wpcf7_form_hidden_fields', 'callback' => [$this->forms, 'add_adcaptcha_response_field'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_filters);
        $this->assertContains(['hook' => 'wpcf7_spam', 'callback' => [$this->forms, 'verify'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_filters);
    }

    public function testVerifyTokenFalse() {
        $this->assertTrue(method_exists($this->forms, 'verify'), 'Method verify does not exist');

        $spam = false;
        $_POST['_wpcf7_adcaptcha_response'] = 'invalid_token';

        $this->verifyMock = $this->createMock(Verify::class);
        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->forms, $this->verifyMock);
        $this->verifyMock->method('verify_token')
            ->willReturn(false);
        global $mocked_filters;

        
        $result = $this->forms->verify($spam);
       
        $this->assertTrue($result, 'Expected verify to return true when spam is true.');
    }

    public function testVerifyTokenTrue()
    {
        $spam = false;
        $_POST['_wpcf7_adcaptcha_response'] = 'valid_token';

        $this->verifyMock = $this->createMock(Verify::class);
        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->forms, $this->verifyMock);
        $this->verifyMock->method('verify_token')
            ->willReturn(true);

        $result = $this->forms->verify($spam);
        $this->assertFalse($result, 'Expected verify to return true when spam is true.');
    }
}
