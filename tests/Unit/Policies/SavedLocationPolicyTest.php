<?php

namespace Tests\Unit\Policies;

use App\Models\SavedLocation;
use App\Models\User;
use App\Policies\SavedLocationPolicy;
use PHPUnit\Framework\TestCase;

class SavedLocationPolicyTest extends TestCase
{
    public function test_allows_the_owner_to_delete_their_location(): void
    {
        $owner = (new User)->forceFill(['id' => 1]);
        $savedLocation = (new SavedLocation)->forceFill(['user_id' => 1]);

        $response = (new SavedLocationPolicy)->delete($owner, $savedLocation);

        $this->assertTrue($response->allowed());
    }

    public function test_denies_another_user_as_not_found(): void
    {
        $otherUser = (new User)->forceFill(['id' => 2]);
        $savedLocation = (new SavedLocation)->forceFill(['user_id' => 1]);

        $response = (new SavedLocationPolicy)->delete($otherUser, $savedLocation);

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }
}
