<?php

namespace Persona\Managers;

use Illuminate\Support\Manager;
use Persona\Drivers\EmailContactDriver;
use Persona\Drivers\PhoneContactDriver;

class ContactDriverManager extends Manager
{
    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return 'email';
    }

    /**
     * Create an instance of the Email Contact driver.
     *
     * @return \Persona\Contracts\ContactDriverContract
     */
    protected function createEmailDriver()
    {
        return $this->container->make(EmailContactDriver::class);
    }

    /**
     * Create an instance of the Phone Contact driver.
     *
     * @return \Persona\Contracts\ContactDriverContract
     */
    protected function createPhoneDriver()
    {
        return $this->container->make(PhoneContactDriver::class);
    }
}
