<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Wallet;
use App\Repository\WalletRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportWalletsCommand extends Command
{
    protected static $defaultName = 'import-wallets';

    private WalletRepository $walletRepository;
    private const WALLETS = [
        0 => [
            'name' => 'Doyle',
            'address' => '0x977223ef93b8490e8e6d2dc28567360f489a3ee1',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        1 => [
            'name' => 'StonedPipes',
            'address' => '0x4a8adfc511f800df904b0536354c4038dd3bb74a',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
    ];

    public function __construct(WalletRepository $walletRepository)
    {
        parent::__construct();
        $this->walletRepository = $walletRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        exec('bin/console d:d:c');
        exec('bin/console d:m:m --no-interaction');
        $output->writeln('<info>Importing wallets...</info>');

        foreach (self::WALLETS as $wallet) {
            $output->writeln('Importing wallet: ' . $wallet['name']);
            $this->importWallet($wallet);
        }

        $output->writeln('<info>Wallets imported.</info>');

        return 0;
    }

    private function importWallet(array $data): void
    {
        $wallet = new Wallet();
        $wallet->setName($data['name']);
        $wallet->setAddress($data['address']);
        $wallet->setAutoBuy($data['autoBuy']);
        $wallet->setToSnipe($data['toSnipe']);

        $this->walletRepository->save($wallet);
    }
}
