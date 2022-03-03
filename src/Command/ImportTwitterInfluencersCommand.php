<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TwitterInfluencer;
use App\Repository\TwitterInfluencerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImportTwitterInfluencersCommand extends Command
{
    protected static $defaultName = 'import-twitter-influencers';

    private const TWITTER_API_USERID_BY_USERNAME = 'https://api.twitter.com/2/users/by/username/%s';
    private const INFLUENCERS_USERNAME_TO_FOLLOW = [
        'NodeBaron',
        'Doy1eee',
        'Stonedpipez',
        'TheBreadMakerr',
        'GakiBash',
    ];

    private string $bearerToken;
    private HttpClientInterface $httpClient;
    private TwitterInfluencerRepository $twitterInfluencerRepository;

    public function __construct(TwitterInfluencerRepository $twitterInfluencerRepository, HttpClientInterface $httpClient, string $bearerToken)
    {
        parent::__construct();

        $this->twitterInfluencerRepository = $twitterInfluencerRepository;
        $this->bearerToken = $bearerToken;
        $this->httpClient = $httpClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Importing Twitter influencers</info>');

        foreach (self::INFLUENCERS_USERNAME_TO_FOLLOW as $username) {
            $isUserExists = $this->twitterInfluencerRepository->findOneBy(['username' => $username]);
            if ($isUserExists) {
                continue;
            }
            $twitterInfluencer = new TwitterInfluencer();
            $twitterInfluencer->setUsername($username);
            $twitterInfluencer->setUserId($this->getUserId($username));
            $this->twitterInfluencerRepository->persist($twitterInfluencer);
        }
        $this->twitterInfluencerRepository->flush();

        $output->writeln('<info>Twitter influencers imported.</info>');

        return 0;
    }

    private function getUserId(string $username): string
    {
        $response = $this->httpClient->request('GET', sprintf(self::TWITTER_API_USERID_BY_USERNAME, $username), [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->bearerToken),
            ],
        ])->getContent();
        $json = json_decode($response, true);
        $this->throwOnInvalidJson($json);

        return $json['data']['id'];
    }

    private function throwOnInvalidJson(array $data): void
    {
        if (array_key_exists('errors', $data)) {
            throw new \RuntimeException(sprintf('The JSON response contains errors, data : %s', json_encode($data)));
        }
    }
}
