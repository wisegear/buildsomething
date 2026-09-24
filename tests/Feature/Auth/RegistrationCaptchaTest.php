<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationCaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['captcha.enabled' => true, 'captcha.sitekey' => 'test-site-key', 'captcha.secret' => 'test-secret']);
        Http::preventStrayRequests();
    }

    private function signup(array $extra = [])
    {
        return $this->post('/register', array_merge(['name' => 'Person', 'email' => 'person@example.com', 'password' => 'password', 'password_confirmation' => 'password'], $extra));
    }

    public function test_widget_is_shown_without_exposing_secret(): void
    {
        $this->get('/register')->assertOk()->assertSee('https://js.hcaptcha.com/1/api.js', false)->assertSee('test-site-key')->assertDontSee('test-secret');
    }

    public function test_widget_displays_with_site_key_while_secret_is_pending(): void
    {
        config(['captcha.secret' => null]);
        $this->get('/register')->assertOk()
            ->assertSee('https://js.hcaptcha.com/1/api.js', false)
            ->assertSee('test-site-key')
            ->assertDontSee('The security check is temporarily unavailable.');
    }

    public function test_missing_token_is_rejected_before_account_creation(): void
    {
        Http::fake();
        $this->signup()->assertSessionHasErrors('h-captcha-response');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('signup_assessments', 0);
        Http::assertNothingSent();
    }

    public function test_verified_token_allows_signup_and_sends_expected_parameters(): void
    {
        Http::fake(['api.hcaptcha.com/siteverify' => Http::response(['success' => true])]);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::5']);
        $this->signup(['h-captcha-response' => 'valid-token'])->assertRedirect('/account');
        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.hcaptcha.com/siteverify' && $request['secret'] === 'test-secret' && $request['sitekey'] === 'test-site-key' && $request['response'] === 'valid-token' && $request['remoteip'] === '2001:db8::5');
    }

    public function test_invalid_token_and_provider_errors_do_not_create_accounts(): void
    {
        foreach ([[['success' => false], 200], [['success' => true], 503], [[], 200]] as [$body, $status]) {
            Http::fake(['api.hcaptcha.com/siteverify' => Http::response($body, $status)]);
            $this->signup(['h-captcha-response' => 'bad-token'])->assertSessionHasErrors('h-captcha-response');
            $this->assertDatabaseCount('users', 0);
            $this->assertGuest();
        }
    }

    public function test_network_failure_returns_a_retry_message(): void
    {
        Http::fake(fn () => throw new ConnectionException('Unavailable'));
        $this->signup(['h-captcha-response' => 'token'])->assertSessionHasErrors('h-captcha-response');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_configuration_fails_closed(): void
    {
        config(['captcha.secret' => null]);
        Http::fake();
        $this->signup(['h-captcha-response' => 'token'])->assertSessionHasErrors('h-captcha-response');
        $this->assertDatabaseCount('users', 0);
        Http::assertNothingSent();
    }

    public function test_email_existence_is_not_disclosed_before_captcha(): void
    {
        User::factory()->create(['email' => 'person@example.com']);
        Http::fake(['api.hcaptcha.com/siteverify' => Http::response(['success' => false])]);
        $this->signup(['h-captcha-response' => 'invalid-token'])
            ->assertSessionHasErrors('h-captcha-response')->assertSessionDoesntHaveErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_is_throttled_before_captcha_requests(): void
    {
        Http::fake(['api.hcaptcha.com/siteverify' => Http::response(['success' => false])]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->signup(['h-captcha-response' => 'invalid-token'])->assertRedirect();
        }
        $this->signup(['h-captcha-response' => 'invalid-token'])->assertStatus(429);
        Http::assertSentCount(5);
        $this->assertDatabaseCount('users', 0);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50']);
        $this->signup(['h-captcha-response' => 'invalid-token'])->assertRedirect();
    }
}
