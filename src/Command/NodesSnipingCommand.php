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
    private const SLEEP_LOAD_DEBANK = 15;

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
        'buy'  => '🤑',
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
        $driver = $this->getDriver();
        $startTime = microtime(true);

        foreach ($wallets as $wallet) {
            $url = sprintf(self::URL, $wallet->getAddress());
            $output->writeln(
                sprintf('<info>[%s] Connecting to %s wallet...</info>', $this->getDateTime(), $wallet->getName())
            );
            $driver->get($url);

            $output->writeln(
                sprintf('<info>[%s] 💤 Waiting for page to load by sleeping %s secs...</info>', $this->getDateTime(), self::SLEEP_LOAD_DEBANK)
            );
            sleep(self::SLEEP_LOAD_DEBANK);

            $lines = $driver->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line']));
            if (count($lines) === 0) {
                $output->writeln(
                    sprintf('<info>[%s] No transactions found.</info>', $this->getDateTime())
                );

                $this->chatter->send(
                    new ChatMessage(
                        sprintf('⚠️ No transactions found for %s', $wallet->getName())
                    )
                );
            }

            $output->writeln('<info>Extracting data...</info>');
            try {
                $this->extractData($wallet, $output, $lines);
            } catch (\Exception $e) {
                $output->writeln(
                    sprintf('[%s] ❌ An error occured : %s', $this->getDateTime(), $e->getMessage())
                );

                $this->chatter->send(
                    new ChatMessage(
                        sprintf('❌ An error occured : %s', $e->getMessage())
                    )
                );

                $driver->quit();
                
                return 1;
            }
        }

        $output->writeln(
            sprintf('<info>[%s] Done in %s secs</info>', $this->getDateTime(), sprintf('%0.2f', (microtime(true) - $startTime)))
        );

        $driver->quit();

        return 0;
    }

    private function checkForTxType(string $txInput): string
    {
        switch ($txInput) {
            case stripos($txInput, 'mint') !== false:
                return 'mint';
            case stripos($txInput, 'node') !== false:
                return 'node';
            case stripos($txInput, 'buy') !== false:
                return 'buy';
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

        return RemoteWebDriver::create(self::HOST, $capabilities);
    }

    private function extractData(Wallet $wallet, OutputInterface $output, array $lines): void
    {
        /** @var RemoteWebElement $line */
        foreach ($lines as $line) {
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
            $this->handleMultipleTxs($wallet, $line, $txNetwork, $txUrl, $txType);
        }

        $this->walletRepository->flush();
    }

    private function handleMultipleTxs(Wallet $wallet, RemoteWebElement $line, string $txNetwork, string $txUrl, string $txType): void
    {
        $nfts = $wallet->getNfts();
        $nodes = $wallet->getNodes();
        $buys = $wallet->getBuys();
        $walletName = $wallet->getName();

        $txToken = $line->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_token']));

        if (count($txToken) === 1 && $txType === 'node') {
            $title = $txToken[0]->getAttribute('title');
            if (!in_array($title, $nodes)) {
                $wallet->addNode($title);
                $this->walletRepository->persist($wallet);
                $this->sendSingleTxChatMessage(
                    $txToken[0]->getDomProperty('textContent'),
                    $txType,
                    $txNetwork,
                    $txUrl,
                    $walletName
                );
            }

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
                } elseif ($txType === 'buy' && ((!$buys) || !in_array($title, $buys))) {
                    $isNew = true;
                    $wallet->addBuy($title);
                    $this->walletRepository->persist($wallet);
                }
            }
        }

        if ($isNew) {
            $this->sendMultipleTxChatMessage($outText, implode("\n", $inText), $txType, $txNetwork, $txUrl, $walletName);
        }
    }

    private function sendSingleTxChatMessage(string $outText, string $txType, string $txNetwork, string $txUrl, string $walletName): void
    {
        $this->chatter->send(
            new ChatMessage(
                sprintf(
                    "%s [%s] New %s transaction found for (%s) : \n\n ⬇️ : \n %s \n URL : %s",
                    self::TX_TYPE_VISUALS_MAPPING[$txType],
                    $txType,
                    $txNetwork,
                    $walletName,
                    $outText,
                    $txUrl
                )
            )
        );
    }

    private function sendMultipleTxChatMessage(string $outText, string $inText, string $txType, string $txNetwork, string $txUrl, string $walletName): void
    {
        $this->chatter->send(
            new ChatMessage(
                sprintf(
                    "%s [%s] New %s transaction found for (%s) : \n\n ⬇️ : \n %s \n ⬆️ : %s \n URL : %s",
                    self::TX_TYPE_VISUALS_MAPPING[$txType],
                    $txType,
                    $txNetwork,
                    $walletName,
                    $outText,
                    $inText,
                    $txUrl
                )
            )
        );
        sleep(1);
    }
}
