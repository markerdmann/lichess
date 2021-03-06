<?php

namespace Bundle\LichessBundle\Tests\Controller;

use Bundle\LichessBundle\Document\Game;

class GameControllerTest extends AbstractControllerTest
{
    public function testViewCurrentGames()
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/games');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals('Games being played right now', $crawler->filter('.title')->text());
        $this->assertGreaterThan(4, $crawler->filter('a.parse_fen')->count());
    }

    public function testViewAllGames()
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/games/all');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertRegexp('#^All games.+$#', $crawler->filter('.title')->text());
        $this->assertGreaterThan(4, $crawler->filter('div.game_row')->count());
    }

    public function testViewMateGames()
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/games/checkmate');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $nbMates = min(10, $client->getContainer()->get('lichess.repository.game')->getNbMates());
        $this->assertEquals($nbMates, $crawler->filter('div.game_row')->count());
    }

    public function testInviteAiAsWhite()
    {
        $this->inviteAiAs('white');
    }

    public function testInviteAiAsBlack()
    {
        $this->inviteAiAs('black');
    }

    protected function inviteAiAs($color)
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');
        $crawler = $client->click($crawler->selectLink('Play with the machine')->link());
        $this->assertTrue($client->getResponse()->isSuccessful());
        $url = $crawler->filter('div.game_config_form form')->attr('action');
        $client->request('POST', $url, array('config' => array(
            'color' => $color,
            'variant' => Game::VARIANT_STANDARD,
            'level' => 1
        )));
        $this->assertTrue($client->getResponse()->isRedirect());
        $crawler = $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(1, $crawler->filter('div.lichess_opponent:contains("Crafty A.I.")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_player:contains("Your turn")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_player div.king.'.$color)->count());
        $this->assertEquals(0, $crawler->filter('div.lichess_chat')->count());
    }

    public function testInviteFriendAsWhite()
    {
        return $this->inviteFriendAs('white');
    }

    public function testInviteFriendAsBlack()
    {
        return $this->inviteFriendAs('black');
    }

    public function testInviteFriendAsRandom()
    {
        list($client, $crawler) = $this->inviteFriend('random');

        $selector = 'div.lichess_game_not_started.waiting_opponent div.lichess_overboard input';
        $this->assertEquals(1, $crawler->filter($selector)->count());

        $inviteUrl = $crawler->filter($selector)->attr('value');
        $this->assertRegexp('#^http://.*/[\w-]{8}$#', $inviteUrl);

        $syncUrl = str_replace(array('\\', '9999999'), array('', '0'), preg_replace('#.+"sync":"([^"]+)".+#s', '$1', $client->getResponse()->getContent()));

        $friend = self::createClient();
        $crawler = $friend->request('GET', $inviteUrl);
        $redirectUrl = $crawler->filter('a.join_redirect_url')->attr('href');
        $friend->request('GET', $redirectUrl);
        $this->assertTrue($friend->getResponse()->isRedirect());
        $crawler = $friend->followRedirect();
        $this->assertTrue($friend->getResponse()->isSuccessful());
        $this->assertEquals(1, $crawler->filter('div.lichess_opponent:contains("Anonymous")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_player:contains("Waiting")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_chat')->count());

        $client->reload();
        $crawler = $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(1, $crawler->filter('div.lichess_opponent:contains("Anonymous")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_player:contains("Your turn")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_chat')->count());
    }

    protected function inviteFriendAs($color)
    {
        list($client, $crawler) = $this->inviteFriend($color);

        $selector = 'div.lichess_game_not_started.waiting_opponent div.lichess_overboard input';
        $this->assertEquals(1, $crawler->filter($selector)->count());

        $inviteUrl = $crawler->filter($selector)->attr('value');
        $this->assertRegexp('#^http://.*/[\w-]{8}$#', $inviteUrl);

        $syncUrl = str_replace(array('\\', '9999999'), array('', '0'), preg_replace('#.+"sync":"([^"]+)".+#s', '$1', $client->getResponse()->getContent()));
        $this->assertRegexp('#^/sync/[\w-]{8}/'.$color.'/0/[\w-]{12}#', $syncUrl);

        $friend = self::createClient();
        $crawler = $friend->request('GET', $inviteUrl);
        $redirectUrl = $crawler->filter('a.join_redirect_url')->attr('href');
        $friend->request('GET', $redirectUrl);
        $this->assertTrue($friend->getResponse()->isRedirect());
        $crawler = $friend->followRedirect();
        $this->assertTrue($friend->getResponse()->isSuccessful());
        $this->assertEquals(1, $crawler->filter('div.lichess_opponent:contains("Anonymous")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_player:contains("Waiting")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_player div.king.'.$color)->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_chat')->count());

        $client->reload();
        $crawler = $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(1, $crawler->filter('div.lichess_opponent:contains("Anonymous")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_player:contains("Your turn")')->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_player div.king.'.$color)->count());
        $this->assertEquals(1, $crawler->filter('div.lichess_chat')->count());
    }

    public function testWatchGame()
    {
        list($client, $crawler) = $this->inviteFriend();

        $selector = 'div.lichess_game_not_started.waiting_opponent div.lichess_overboard input';
        $inviteUrl = $crawler->filter($selector)->attr('value');

        $friend = self::createClient();
        $crawler = $friend->request('GET', $inviteUrl);
        $redirectUrl = $crawler->filter('a.join_redirect_url')->attr('href');
        $friend->request('GET', $redirectUrl);
        $crawler = $friend->followRedirect();

        $spectator = self::createClient();
        $crawler = $spectator->request('GET', $inviteUrl);
        $this->assertTrue($spectator->getResponse()->isSuccessful());
        $this->assertRegexp('#You are viewing this game as a spectator.#', $spectator->getResponse()->getContent());
        $this->assertEquals(0, $crawler->filter('div.lichess_chat')->count());
    }

    public function testShowHead()
    {
        $client = self::createClient();
        $id = $this->getAnyGameId($client);
        $crawler = $client->request('HEAD', '/'.$id);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals('', $client->getResponse()->getContent());
    }

    public function testJoinHead()
    {
        $client = self::createClient();
        $id = $this->getAnyGameId($client);
        $crawler = $client->request('HEAD', '/join/'.$id);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals('', $client->getResponse()->getContent());
    }

    protected function getAnyGameId($client)
    {
        return $client->getContainer()->get('lichess.repository.game')->findOneBy(array())->getId();
    }
}
