<?php

declare(strict_types=1);

namespace App\Command\Sniper;

use App\Entity\Wallet;
use App\Repository\WalletRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ImportWalletsCommand extends Command
{
    protected static $defaultName = 'sniper:import';

    private WalletRepository $walletRepository;

    public function __construct(WalletRepository $walletRepository)
    {
        parent::__construct();
        $this->walletRepository = $walletRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Importing wallet...</info>');

        $question = new Question('Which wallets do you want to follow ?' . PHP_EOL . 'Example : 0xe990f34d8303e038f435455a8b85c481b41a8b2d,labelForWallet' . PHP_EOL);
        $question
            ->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('You must enter at least one wallet address');
                }

                return $value;
            });
        $question->setMultiline(true);
        $answer = $this->getHelper('question')->ask($input, $output, $question);
        $wallets = array_map('trim', explode("\n", $answer));

        foreach ($wallets as $wallet) {
            $fields = explode(',', $wallet);
            if (count($fields) !== 3) {
                throw new \RuntimeException('Wallet address, name, and wallet label must be separated by a comma');
            }

            $isWalletExists = $this->walletRepository->findOneBy(['address' => $fields[0]]);
            if ($isWalletExists) {
                continue;
            }
            $output->writeln('Importing wallet: ' . $fields[1]);
            $this->importWallet($fields);
        }

        $output->writeln('<info>Wallets imported.</info>');

        return 0;
    }

    private function importWallet(array $data): void
    {
        $data = array_map('trim', $data);
        $wallet = new Wallet();
        $wallet->setName($data[1]);
        $wallet->setLabel($data[2]);
        $wallet->setAddress($data[0]);
        $wallet->setAutoBuy(false);
        $wallet->setToSnipe(true);

        $this->walletRepository->save($wallet);
    }
}
