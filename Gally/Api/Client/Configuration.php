<?php

namespace Gally\ElasticsuiteBridge\Gally\Api\Client;

class Configuration
{
    private $email = "admin@example.com";

    private $password = "apassword";

    private $host = "https://llm.localhost/";
//    private $host = "https://ec2-34-245-123-117.eu-west-1.compute.amazonaws.com/"; // Gally llm
//    private $host = "https://ec2-3-252-126-71.eu-west-1.compute.amazonaws.com/"; // Gally llm gpu

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
