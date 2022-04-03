<?php

declare(strict_types=1);

namespace App\Command\Sniper;

use App\Repository\WalletRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class DeleteWalletCommand extends Command
{
    protected static $defaultName = 'sniper:wallet:delete';

    private WalletRepository $walletRepository;

    public function __construct(WalletRepository $walletRepository)
    {
        parent::__construct();

        $this->walletRepository = $walletRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $allWalletNames = array_column($this->walletRepository->findAllWalletNames(), 'name');

        $output->writeln('<info>Wallet names available to delete :</info>');
        $output->writeln(implode(PHP_EOL, $allWalletNames));

        $question = new Question("Which wallet name you want to delete ?\n");
        $question
            ->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('You must enter at least one wallet name');
                }

                return $value;
            })
            ->setAutocompleterValues($allWalletNames);
        $answer = $this->getHelper('question')->ask($input, $output, $question);
        $walletNames = array_map('trim', explode("\n", $answer));
        $this->walletRepository->deleteWalletNamesInArray($walletNames);

        $output->writeln('<info>Wallet name specified got deleted.</info>');

        return 0;
    }
}
