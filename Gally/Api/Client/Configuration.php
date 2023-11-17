<?php

namespace Gally\ElasticsuiteBridge\Gally\Api\Client;

class Configuration
{
    private $email = "admin@example.com";

    private $password = "apassword";

    //private $host = "https://ec2-34-243-11-163.eu-west-1.compute.amazonaws.com/";
    private $host = "https://gally.localhost/";

    public function getEmail()
    {
        return $this->email;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getHost()
    {
        return $this->host;
    }
}
