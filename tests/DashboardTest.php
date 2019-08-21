<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardTest extends WebTestCase
{
    public function testSomething()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertContains('Velire Controller', $crawler->filter('title')->text());
    }
}
