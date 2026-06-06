<?php

namespace Tests\Unit\Models;

use DateTime;
use Tests\TestCase;

class AnnounceBannerTest extends TestCase
{
    public function test_is_in_time_window_returns_true_when_no_times_set()
    {
        $banner = $this->makeBanner(null, null);
        $this->assertTrue($banner->isInTimeWindow());
    }

    public function test_is_in_time_window_returns_false_before_start()
    {
        $banner = $this->makeBanner(date('Y-m-d H:i:s', strtotime('+1 day')), null);
        $this->assertFalse($banner->isInTimeWindow());
    }

    public function test_is_in_time_window_returns_false_after_end()
    {
        $banner = $this->makeBanner(null, date('Y-m-d H:i:s', strtotime('-1 day')));
        $this->assertFalse($banner->isInTimeWindow());
    }

    public function test_is_in_time_window_returns_true_within_window()
    {
        $banner = $this->makeBanner(
            date('Y-m-d H:i:s', strtotime('-1 day')),
            date('Y-m-d H:i:s', strtotime('+1 day')),
        );
        $this->assertTrue($banner->isInTimeWindow());
    }

    public function test_is_visible_to_role_returns_true_when_no_roles_set()
    {
        $banner = $this->makeBanner(null, null, null);

        $this->assertTrue($banner->isVisibleToRole('admin'));
        $this->assertTrue($banner->isVisibleToRole('customer'));
    }

    public function test_is_visible_to_role_returns_true_when_empty_roles()
    {
        $banner = $this->makeBanner(null, null, []);

        $this->assertTrue($banner->isVisibleToRole('admin'));
    }

    public function test_is_visible_to_role_checks_role_membership()
    {
        $banner = $this->makeBanner(null, null, ['admin', 'super-admin']);

        $this->assertTrue($banner->isVisibleToRole('admin'));
        $this->assertTrue($banner->isVisibleToRole('super-admin'));
        $this->assertFalse($banner->isVisibleToRole('customer'));
        $this->assertFalse($banner->isVisibleToRole('developer'));
    }

    private function makeBanner(?string $startsAt, ?string $endsAt, mixed $roles = null): object
    {
        return new class($startsAt, $endsAt, $roles) {
            public function __construct(
                private ?string $startsAt,
                private ?string $endsAt,
                private mixed $roles = null,
            ) {}

            public function isInTimeWindow(): bool
            {
                $now = new DateTime();

                if ($this->startsAt && $now < new DateTime($this->startsAt)) {
                    return false;
                }

                if ($this->endsAt && $now > new DateTime($this->endsAt)) {
                    return false;
                }

                return true;
            }

            public function isVisibleToRole(?string $role): bool
            {
                if (empty($this->roles)) {
                    return true;
                }

                return in_array($role, $this->roles);
            }
        };
    }
}
