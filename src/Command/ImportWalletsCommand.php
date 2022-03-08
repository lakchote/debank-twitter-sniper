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
        2 => [
            'name' => 'StonedPipes',
            'address' => '0x4a8adfc511f800df904b0536354c4038dd3bb74a',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        3 => [
            'name' => 'Big Farmer 800',
            'address' => '0x9ad3a5e6b77aa5dc13e0bcce358a8fb7cbe73966',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        4 => [
            'name' => 'Noder #1',
            'address' => '0x075c9873273b025fb00b52f31453d2eaa334b71e',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        5 => [
            'name' => 'Noder #2',
            'address' => '0x7f41fbcb064878058278e8077ae9e039b7f48ff8',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        6 => [
            'name' => 'Noder #3',
            'address' => '0xc2142b3855f3f68c4880e0537a8a273805b8d441',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        7 => [
            'name' => 'Coffee Block',
            'address' => '0x72b2b9d02fa390285adce0beb219900cc749c938',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        8 => [
            'name' => 'Strong OG #1',
            'address' => '0xc0e636564f4e3ea4d85276b250176622b139c975',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        9 => [
            'name' => 'Strong OG #2',
            'address' => '0x7a04ea27fd2876cd489e5c8baeb61a6e485dead8',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        10 => [
            'name' => 'Noder #4',
            'address' => '0xafdc797a2a97708d580ea1e30c8479cac4df9226',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        11 => [
            'name' => 'Noder #5',
            'address' => '0xb04d5f3a7a124472cc5b0b5af4ecce8d9630a664',
            'autoBuy' => false,
            'toSnipe' => true,
        ],
        12 => [
            'name' => 'Noder #6',
            'address' => '0x889c97ad4157b802b1e34b0120b49a6edf6c6f0a',
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
        $output->writeln('<info>Importing wallets...</info>');

        foreach (self::WALLETS as $wallet) {
            $isWalletExists = $this->walletRepository->findOneBy(['address' => $wallet['address']]);
            if ($isWalletExists) {
                continue;
            }
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
