<?php

namespace Tests\Feature;

use App\Models\SignupAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SignupAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email, string $ip = '192.0.2.10'): SignupAssessment
    {
        Auth::logout();
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/register', [
            'name' => 'Signup Test', 'email' => $email,
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect('/account');
        $this->assertAuthenticated();

        return User::where('email', $email)->firstOrFail()->signupAssessment;
    }

    public function test_captures_local_request_information_without_enrichment(): void
    {
        $this->freezeTime();
        $this->withHeaders(['User-Agent' => 'Signup Browser', 'Accept-Language' => 'en-GB,en;q=0.9', 'Referer' => 'https://blogshed.uk/about']);
        $assessment = $this->register('test@example.com');
        $this->assertSame('192.0.2.10', $assessment->registration_ip);
        $this->assertSame('Signup Browser', $assessment->user_agent);
        $this->assertSame('en-GB,en;q=0.9', $assessment->accept_language);
        $this->assertSame('https://blogshed.uk/about', $assessment->referrer);
        $this->assertTrue($assessment->registered_at->equalTo(now()->startOfSecond()));
        $this->assertSame('example.com', $assessment->email_domain);
        $this->assertSame(0, $assessment->previous_ip_registrations);
    }

    public function test_repeated_ip_is_counted_but_never_blocks_registration(): void
    {
        foreach ([0, 1, 2] as $previous) {
            $assessment = $this->register("test{$previous}@example.com");
            $this->assertSame($previous, $assessment->previous_ip_registrations);
        }
        $other = $this->register('other@example.com', '192.0.2.11');
        $this->assertSame(0, $other->previous_ip_registrations);
        $this->assertDatabaseCount('users', 4);
    }

    public function test_equivalent_ipv6_addresses_share_a_count_and_optional_headers_can_be_absent(): void
    {
        $first = $this->register('first@example.com', '2001:0db8:0000:0000:0000:0000:0000:0001');
        $second = $this->register('second@example.com', '2001:db8::1');
        $this->assertSame('2001:db8::1', $first->registration_ip);
        $this->assertSame(1, $second->previous_ip_registrations);
        $this->assertNull($second->referrer);
    }

    public function test_untrusted_forwarded_headers_cannot_spoof_signup_ip(): void
    {
        config(['trustedproxy.proxies' => []]);
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.42', 'CF-Connecting-IP' => '203.0.113.42']);
        $this->assertSame('192.0.2.10', $this->register('test@example.com')->registration_ip);
    }

    public function test_explicit_trusted_proxy_chain_resolves_original_ip(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.10', '192.0.2.0/24']]);
        $this->withHeaders(['X-Forwarded-For' => '2001:db8::42, 192.0.2.20']);
        $this->assertSame('2001:db8::42', $this->register('test@example.com', '10.0.0.10')->registration_ip);
    }

    public function test_admin_security_details_are_escaped_and_private(): void
    {
        $this->withHeaders(['User-Agent' => '<script>alert(1)</script>']);
        $assessment = $this->register('test@example.com');
        $this->get('/admin/users')->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/admin/users')->assertOk()
            ->assertSee('Signup / Security')->assertSee('192.0.2.10')
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Risk flags')->assertDontSee('IP intelligence provider')
            ->assertDontSee('Not checked')->assertSee('No signup information recorded');
        $assessment->user->delete();
        $this->assertDatabaseMissing('signup_assessments', ['id' => $assessment->id]);
    }
}
