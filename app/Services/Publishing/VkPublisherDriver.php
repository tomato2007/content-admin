<?php

declare(strict_types=1);

namespace App\Services\Publishing;

class VkPublisherDriver extends NullPublisherDriver
{
    protected function driverKey(): string
    {
        return 'vk_stub';
    }

    protected function successMessage(): string
    {
        return 'Stub VK publish completed. Replace VkPublisherDriver with a real VK driver.';
    }
}
