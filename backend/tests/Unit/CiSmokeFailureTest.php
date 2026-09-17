<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Temporary test used to verify the CI workflow fails the build on a failing
 * assertion. Delete this file once the CI workflow has been confirmed to
 * fail on this branch.
 */
class CiSmokeFailureTest extends TestCase
{
    public function test_intentional_ci_failure(): void
    {
        $this->assertTrue(false, 'Intentional failure to verify CI catches failing tests.');
    }
}
