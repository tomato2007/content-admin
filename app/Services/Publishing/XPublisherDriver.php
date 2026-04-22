<?php

declare(strict_types=1);

namespace App\Services\Publishing;

class XPublisherDriver extends NullPublisherDriver
{
    protected function driverKey(): string
    {
        return 'x_stub';
    }

    protected function successMessage(): string
    {
        return 'Stub X publish completed. Replace XPublisherDriver with a real X driver.';
    }
}
