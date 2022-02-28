<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\TwitterInfluencerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TheOracleCommand extends Command
{
    protected static $defaultName = 'the-oracle';

    private const TWITTER_API_FOLLOWING_BY_USERID = 'https://api.twitter.com/2/users/%s/following';

    private string $bearerToken;
    private HttpClientInterface $httpClient;
    private TwitterInfluencerRepository $twitterInfluencerRepository;
    private ChatterInterface $chatter;

    public function __construct(HttpClientInterface $httpClient, TwitterInfluencerRepository $twitterInfluencerRepository, ChatterInterface $chatter, string $bearerToken)
    {
        parent::__construct();

        $this->httpClient = $httpClient;
        $this->bearerToken = $bearerToken;
        $this->twitterInfluencerRepository = $twitterInfluencerRepository;
        $this->chatter = $chatter;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('<info>[%s] Running The Oracle...</info>', $this->getDateTime()));

        $twitterInfluencers = $this->twitterInfluencerRepository->findAll();
        foreach ($twitterInfluencers as $twitterInfluencer) {
            $userId = $twitterInfluencer->getUserId();
            $username = $twitterInfluencer->getUsername();
            $userFollowing = $twitterInfluencer->getFollowing();

            $output->writeln(sprintf('<info>[%s] Looking up %s following...</info>', $this->getDateTime(), $username));

            $nextToken = null;
            $queryParams['user.fields'] = 'description,created_at';
            try {
                do {
                    if ($nextToken) {
                        $queryParams['pagination_token'] = $nextToken;
                    }
                    $response = $this->httpClient->request('GET', sprintf(self::TWITTER_API_FOLLOWING_BY_USERID, $userId), [
                        'headers' => [
                            'Authorization' => sprintf('Bearer %s', $this->bearerToken),
                        ],
                        'query' => $queryParams,
                    ])->getContent();
                    $json = json_decode($response, true);
                    $this->throwOnInvalidJson($json);

                    foreach ($json['data'] as $userFollowed) {
                        if (!in_array($userFollowed['username'], $userFollowing)) {
                            $twitterInfluencer->addFollowing($userFollowed['username']);
                            if (count($userFollowing) !== 0) {
                                $this->sendNewFollowingMessage(
                                    $username,
                                    $userFollowed['username'],
                                    $userFollowed['description'],
                                    $userFollowed['created_at'],
                                );
                            }
                            $this->twitterInfluencerRepository->persist($twitterInfluencer);
                        }
                    }
                } while (array_key_exists('next_token', $json));
            } catch (\Exception $e) {
                $this->chatter->send(new ChatMessage(sprintf('🐦 An error occured : %s', $e->getMessage())));

                throw new \RuntimeException(sprintf('[%s] An error occured : %s', $this->getDateTime(), $e->getMessage()));
            }

            $this->twitterInfluencerRepository->flush();
        }

        $output->writeln(sprintf('<info>[%s] The Oracle is done!</info>', $this->getDateTime()));

        return 0;
    }

    private function getDateTime(): string
    {
        return (new \DateTime())->format('Y-m-d H:i:s');
    }

    private function sendNewFollowingMessage(
        string $twitterInfluencerUsername,
        string $usernameFollowed,
        string $usernameFollowedDescription,
        string $usernameFollowedCreatedAt
    ): void
    {
        $options = new TelegramOptions(['chat_id' => '-771321845']);

        $this->chatter->send(
            new ChatMessage(
                sprintf("🐦 %s just followed %s\n\nUser description:\n %s\n\nTwitter account created the %s\nURL: https://twitter.com/%s",
                    $twitterInfluencerUsername,
                    $usernameFollowed,
                    $usernameFollowedDescription,
                    strstr($usernameFollowedCreatedAt, 'T', true),
                    $usernameFollowed
                ), $options
            )
        );
    }

    private function throwOnInvalidJson(array $data): void
    {
        if (array_key_exists('errors', $data)) {
            $this->chatter->send(new ChatMessage(sprintf('🐦 The JSON response contains errors, data : %s', json_encode($data))));

            throw new \RuntimeException(sprintf('[%s] The JSON response contains errors, data : %s', $this->getDateTime(), json_encode($data)));
        }
    }
}
