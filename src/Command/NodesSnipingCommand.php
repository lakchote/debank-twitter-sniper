<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Wallet;
use App\Repository\WalletRepository;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class NodesSnipingCommand extends Command
{
    protected static $defaultName = 'snipe';

    private const URL = 'https://debank.com/profile/%s/history';
    private const HOST = 'http://localhost:1337';

    private const HISTORY_TABLE_DATA = [
        'main' => '.History_table__9zhFG',
        'line' => '.History_tableLine__3dtlF',
        'line_tx_time' => '.History_sinceTime__3JN2E',
        'line_tx_input' => '.History_ellipsis__rfBNq',
        'line_tx_token' => '.History_tokenChangeItem__3NN7B',
        'line_tx_approval' => '.History_interAddressExplain__2VXp7',
        'line_tx_url' => '.History_txStatus__2PzNQ a',
        'line_tx_network' => '.History_rowChain__eo4NT',
    ];

    private ChatterInterface $chatter;
    private WalletRepository $walletRepository;

    public function __construct(ChatterInterface $chatter, WalletRepository $walletRepository)
    {
        parent::__construct();

        $this->chatter = $chatter;
        $this->walletRepository = $walletRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $wallets = $this->walletRepository->findAllToSnipe();

        $cntNoTransactions = 0;
        $capabilities = DesiredCapabilities::chrome();
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments(['--headless']);
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);
        $driver = RemoteWebDriver::create(self::HOST, $capabilities);

        while (1) {
            foreach ($wallets as $wallet) {
                $url = sprintf(self::URL, $wallet->getAddress());
                $output->writeln('<info>Connecting to '.$url.'...</info>');
                $driver->get($url);

                $output->writeln('<info>Waiting for page to load...</info>');
                sleep(10);

                $lines = $driver->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line']));
                if (count($lines) === 0) {
                    $output->writeln('<error>No transactions found.</error>');
                    $this->chatter->send(new ChatMessage('⚠️ No transactions found for '.$wallet->getName()));

                    ++$cntNoTransactions;
                    $this->chatter->send(new ChatMessage('⚠️ No transactions found for 3 times in a row. Exiting...'));
                    sleep(300);
                    if ($cntNoTransactions === 3) {
                        $this->chatter->send(new ChatMessage('⚠️ No transactions found for 3 times in a row. Exiting...'));
                        $driver->close();
                        return 1;
                    }
                    break;
                }
                $output->writeln('<info>Extracting data...</info>');
                try {
                    $this->extractData($wallet, $output, $lines);
                } catch (\Exception $e) {
                    $this->chatter->send(new ChatMessage('❌ Error occured : '.$e->getMessage()));
                    break;
                }
            }
            $output->writeln('<info>Done!</info>');
            sleep(600);
        }

        return 0;
    }

    private function checkForNft(string $txInput): bool
    {
        return stripos($txInput, 'mint') !== false;
    }

    private function checkForNode(string $txInput): bool
    {
        return stripos($txInput, 'node') !== false;
    }

    private function extractData(Wallet $wallet, OutputInterface $output, array $lines): void
    {
        /** @var RemoteWebElement $line */
        foreach ($lines as $line) {
            // Get the time of the last transaction and see if we have it already
            $txTime = $line->findElement(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_time']))->getText();
            // Get the tx URL
            $txUrl = $line->findElement(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_url']))->getAttribute('href');
            // Get the tx network
            $txNetwork = strtoupper(
                $line->findElement(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_network']))->getAttribute('alt')
            );

            // Get subject of the transaction if there is one
            try {
                $txInput = $line->findElement(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_input']))->getText();
            } catch (\Exception $e) {
                continue;
            }

            // if there is no node or nft mint in the transaction, skip it
            $hasNft = $this->checkForNft($txInput);
            $hasNode = $this->checkForNode($txInput);
            if (!$hasNode && !$hasNft) {
                continue;
            }

            $this->handleMultipleTxs($wallet, $line, $txNetwork, ($hasNft));
        }

        $this->walletRepository->flush();
    }

    private function handleMultipleTxs(Wallet $wallet, RemoteWebElement $line, string $txNetwork, bool $isNft): void
    {
        $nfts = $wallet->getNfts();
        $nodes = $wallet->getNodes();

        $txToken = $line->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_token']));
        if (count($txToken) === 1 && !$isNft) {
            $title = $txToken[0]->getAttribute('title');
            if (!in_array($title, $nodes)) {
                $wallet->addNode($title);
                $this->walletRepository->persist($wallet);
                $this->chatter->send(
                    new ChatMessage(
                        '💰'.' ['.$txNetwork.'] New node transaction found for'.' ('.$wallet->getName().') :'
                        ."\n\n"
                        .'⬇️ : '
                        .PHP_EOL
                        .$txToken[0]->getDomProperty('textContent')
                    )
                );
            }

            return;
        }

        $outText = null;
        $inText = [];
        $isNew = false;
        foreach ($txToken as $k => $tx) {
            if ($k === 0) {
                $outText = $tx->getDomProperty('textContent');
            } else {
                $title = $tx->getAttribute('title');
                if ($isNft && !in_array($title, $nfts)) {
                    $isNew = true;
                    $wallet->addNft($title);
                    $this->walletRepository->persist($wallet);
                } elseif (!$isNft && !in_array($title, $nodes)) {
                    $isNew = true;
                    $wallet->addNode($title);
                    $this->walletRepository->persist($wallet);
                }
                $inText[] = $tx->getDomProperty('textContent');
            }
        }

        $nftMessage = new ChatMessage(
            '🖼️'.' ['.$txNetwork.'] New NFT transaction found for'.' ('.$wallet->getName().') :'
            ."\n\n"
            .'⬇️ : '
            .PHP_EOL
            .$outText
            .PHP_EOL
            .'⬆️ : '
            .PHP_EOL
            .implode("\n", $inText)
        );
        $nodeMessage = new ChatMessage(
            '💰'.' ['.$txNetwork.'] New node transaction found for'.' ('.$wallet->getName().') :'
            ."\n\n"
            .'⬇️ : '
            .PHP_EOL
            .$outText
            .PHP_EOL
            .'⬆️ : '
            .PHP_EOL
            .implode("\n", $inText)
        );

        if ($isNew) {
            $this->chatter->send($isNft ? $nftMessage : $nodeMessage);
        }
    }

    private function initChromeDriver(): RemoteWebDriver
    {
        $capabilities = DesiredCapabilities::chrome();
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments(['--headless']);
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

        return RemoteWebDriver::create(self::HOST, $capabilities);
    }
}
