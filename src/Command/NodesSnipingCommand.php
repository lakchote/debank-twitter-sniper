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
    private const MAX_RETRIES = 3;
    private const SLEEP_LOAD_DEBANK = 10;
    private const SLEEP_ERROR_RETRY = 300;
    private const SLEEP_SNIPE_RETRY = 600;

    private const HISTORY_TABLE_DATA = [
        'main'             => '.History_table__9zhFG',
        'line'             => '.History_tableLine__3dtlF',
        'line_tx_time'     => '.History_sinceTime__3JN2E',
        'line_tx_input'    => '.History_ellipsis__rfBNq',
        'line_tx_token'    => '.History_tokenChangeItem__3NN7B',
        'line_tx_approval' => '.History_interAddressExplain__2VXp7',
        'line_tx_url'      => '.History_txStatus__2PzNQ a',
        'line_tx_network'  => '.History_rowChain__eo4NT',
    ];
    private const TX_TYPE_VISUALS_MAPPING = [
        'mint' => '🖼️',
        'node' => '💰',
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

        while (1) {
            // Init Chrome driver
            $driver = $this->getDriver();
            foreach ($wallets as $wallet) {
                $url = sprintf(self::URL, $wallet->getAddress());
                $output->writeln(
                    sprintf('<info>[%s] Connecting to %s wallet...</info>', $this->getDateTime(), $wallet->getName())
                );
                $driver->get($url);

                $output->writeln(
                    sprintf('<info>[%s] 💤 Waiting for page to load by sleeping %s...</info>', $this->getDateTime(), self::SLEEP_LOAD_DEBANK)
                );
                sleep(self::SLEEP_LOAD_DEBANK);

                $lines = $driver->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line']));
                if (count($lines) === 0) {
                    $output->writeln(
                        sprintf('<error>[%s] No transactions found.</error>', $this->getDateTime())
                    );
                    $this->chatter->send(
                        new ChatMessage(
                            sprintf('[%s] ⚠️ No transactions found for %s', $this->getDateTime(), $wallet->getName())
                        )
                    );

                    ++$cntNoTransactions;
                    $this->chatter->send(new ChatMessage(
                        sprintf('[%s] 💤 Sleeping for %s seconds, attempts : %d/%d ...',
                            $this->getDateTime(), self::SLEEP_ERROR_RETRY, $cntNoTransactions, self::MAX_RETRIES)
                    ));
                    sleep(self::SLEEP_ERROR_RETRY);

                    // Max retries reached
                    if ($cntNoTransactions === self::MAX_RETRIES) {
                        $output->writeln(
                            sprintf('<error>[%s] No transactions found for 3 times in a row. Exiting...</error>', $this->getDateTime())
                        );
                        $this->chatter->send(
                            new ChatMessage(
                                sprintf('<error>[%s] No transactions found for 3 times in a row. Exiting...</error>', $this->getDateTime())
                            )
                        );
                        $driver->quit();

                        return 1;
                    }

                    // Retry
                    break;
                }
                $output->writeln('<info>Extracting data...</info>');
                try {
                    $this->extractData($wallet, $output, $lines);
                } catch (\Exception $e) {
                    $output->writeln(
                        sprintf('[%s] ❌ Error occured : %s'.$e->getMessage())
                    );
                    $this->chatter->send(
                        new ChatMessage(
                            sprintf('[%s] ❌ Error occured : %s'.$e->getMessage())
                        )
                    );

                    return 1;
                }
            }
            $output->writeln('<info>Done!</info>');
            $driver->quit();
            sleep(self::SLEEP_SNIPE_RETRY);
        }

        return 0;
    }

    private function checkForTxType(string $txInput): string
    {
        switch ($txInput) {
            case stripos($txInput, 'mint') !== false:
                return 'mint';
            case stripos($txInput, 'node') !== false:
                return 'node';
            default:
                return 'other';
        }
    }

    private function getDateTime(): string
    {
        return (new \DateTime())->format('Y-m-d H:i:s');
    }

    private function getDriver(): RemoteWebDriver
    {
        $capabilities = DesiredCapabilities::chrome();
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments(['--headless']);
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

        return  RemoteWebDriver::create(self::HOST, $capabilities);
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

            // Check for tx type
            $txType = $this->checkForTxType($txInput);
            if ($txType === 'other') {
                continue;
            }
            $this->handleMultipleTxs($wallet, $line, $txNetwork, $txType);
        }

        $this->walletRepository->flush();
    }

    private function handleMultipleTxs(Wallet $wallet, RemoteWebElement $line, string $txNetwork, string $txType): void
    {
        $nfts = $wallet->getNfts();
        $nodes = $wallet->getNodes();
        $walletName = $wallet->getName();

        $txToken = $line->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_token']));

        if (count($txToken) === 1 && $txType === 'node') {
            $title = $txToken[0]->getAttribute('title');
            if (!in_array($title, $nodes)) {
                $wallet->addNode($title);
                $this->walletRepository->persist($wallet);

            }
            $this->sendSingleTxChatMessage(
                $txToken[0]->getDomProperty('textContent'),
                $txType,
                $txNetwork,
                $walletName
            );

            return;
        }

        $isNew = false;
        foreach ($txToken as $k => $tx) {
            if ($k === 0) {
                $outText = $tx->getDomProperty('textContent');
            } else {
                $title = $tx->getAttribute('title');
                $inText[] = $tx->getDomProperty('textContent');
                if ($txType === 'mint' && !in_array($title, $nfts)) {
                    $isNew = true;
                    $wallet->addNft($title);
                    $this->walletRepository->persist($wallet);
                } elseif ($txType === 'node' && !in_array($title, $nodes)) {
                    $isNew = true;
                    $wallet->addNode($title);
                    $this->walletRepository->persist($wallet);
                } else {
                    $this->sendMultipleTxChatMessage($outText, implode("\n", $inText), $txType, $txNetwork, $walletName); // swap

                    return;
                }
            }
        }

        if ($isNew) {
            $this->sendMultipleTxChatMessage($outText, implode("\n", $inText), $txType, $txNetwork, $walletName);
        }
    }

    private function sendSingleTxChatMessage(string $outText, string $txType, string $txNetwork, string $walletName): void
    {
        $this->chatter->send(
            new ChatMessage(
                sprintf("%s [%s] New %s transaction found for (%s) : \n\n ⬇️ : \n %s",
                self::TX_TYPE_VISUALS_MAPPING[$txType], $txType, $txNetwork, $walletName, $outText)
            )
        );
    }

    private function sendMultipleTxChatMessage(string $outText, string $inText, string $txType, string $txNetwork, string $walletName): void
    {
        $this->chatter->send(
            new ChatMessage(
                sprintf("%s [%s] New %s transaction found for (%s) : \n\n ⬇️ : \n %s \n ⬆️ : %s",
                    self::TX_TYPE_VISUALS_MAPPING[$txType], $txType, $txNetwork, $walletName, $outText, $inText)
            )
        );
    }
}
