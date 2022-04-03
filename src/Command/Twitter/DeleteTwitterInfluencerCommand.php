<?php

declare(strict_types=1);

namespace App\Command\Twitter;

use App\Repository\TwitterInfluencerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class DeleteTwitterInfluencerCommand extends Command
{
    protected static $defaultName = 'twitter:influencer:delete';

    private TwitterInfluencerRepository $twitterInfluencerRepository;

    public function __construct(TwitterInfluencerRepository $twitterInfluencerRepository)
    {
        parent::__construct();

        $this->twitterInfluencerRepository = $twitterInfluencerRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $allTwitterInfluencerNames = array_column($this->twitterInfluencerRepository->findAllTwitterInfluencerNames(), 'username');
        $output->writeln('<info>Twitter handles available to delete :</info>');
        $output->writeln(implode(PHP_EOL, $allTwitterInfluencerNames));

        $question = new Question("Which Twitter handle do you want to delete ?\n");
        $question
            ->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('You must enter at least one Twitter handle');
                }

                return $value;
            })
            ->setAutocompleterValues($allTwitterInfluencerNames);

        $answer = $this->getHelper('question')->ask($input, $output, $question);
        $twitterHandles = array_map('trim', explode("\n", $answer));
        $this->twitterInfluencerRepository->deleteUsersInArray($twitterHandles);

        $output->writeln('<info>Twitter influencer specified deleted.</info>');

        return 0;
    }
}
