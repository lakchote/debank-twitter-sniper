<?php

declare(strict_types=1);

namespace App\Command\Sniper;

use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Helper\RegexHelper;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
use App\Service\GoogleSheet;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WalletSniperCommand extends Command
{
    protected static $defaultName = 'sniper:snipe';

    private const URL = 'https://debank.com/profile/%s/history';
    private const HOST = 'http://localhost:1337';
    private const SLEEP_LOAD_DEBANK = 15;
    private const GOOGLE_SHEET_WALLETS = 'Wallets';
    private const GOOGLE_SHEET_TRANSACTIONS = 'Transactions History';

    private const HISTORY_TABLE_DATA = [
        'main'             => '.History_table__9zhFG',
        'net_worth'        => '.HeaderInfo_totalAsset__2noIk',
        'line'             => '.History_tableLine__3dtlF',
        'line_tx_time'     => '.History_sinceTime__3JN2E',
        'line_tx_input'    => '.History_ellipsis__rfBNq',
        'line_tx_token'    => '.History_tokenChangeItem__3NN7B',
        'line_tx_approval' => '.History_interAddressExplain__2VXp7',
        'line_tx_url'      => '.History_txStatus__2PzNQ a',
        'line_tx_network'  => '.History_rowChain__eo4NT',
    ];
    private const TX_TYPE_VISUALS_MAPPING = [
        'mint'     => '🖼️',
        'node'     => '💰',
        'buy'      => '🤑',
        'stake'    => '🪙',
        'unstake'  => '💸',
        'swap'     => '🔄',
        'contract' => '📝',
    ];

    private WalletRepository $walletRepository;
    private TransactionRepository $transactionRepository;
    private GoogleSheet $googleSheetService;

    public function __construct(
        WalletRepository $walletRepository,
        TransactionRepository $transactionRepository,
        GoogleSheet $googleSheetService,
    )
    {
        parent::__construct();

        $this->walletRepository = $walletRepository;
        $this->transactionRepository = $transactionRepository;
        $this->googleSheetService = $googleSheetService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $wallets = $this->walletRepository->findAllToSnipe();

        try {
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
                }

                $output->writeln('<info>Extracting data...</info>');
                $walletNetWorth = $driver->findElement((WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['net_worth'])))->getText();
                $this->extractData($wallet, $lines, $walletNetWorth);
            }
            $output->writeln(
                sprintf('<info>[%s] Done in %s secs</info>', $this->getDateTime(), sprintf('%0.2f', (microtime(true) - $startTime)))
            );
            $driver->quit();

            return 0;

        } catch (\Throwable $e) {
            $output->writeln(
                sprintf('❌ An error occured : %s, line : %s', $e->getMessage(), $e->getLine())
            );

            $driver->quit();

            return 1;
        }
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
            case (stripos($txInput, 'unstake') !== false):
                return 'unstake';
            case (stripos($txInput, 'stake') !== false):
                return 'stake';
            case (stripos($txInput, 'swap') !== false):
                return 'swap';
            case (stripos($txInput, 'contract') !== false) || (stripos($txInput, 'multicall') !== false):
                return 'contract';
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

        return RemoteWebDriver::create(self::HOST, $capabilities, 3600000,3600000);
    }

    private function extractData(Wallet $wallet, array $lines, string $walletNetWorth): void
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
            $this->handleMultipleTxs($wallet, $line, $walletNetWorth, $txNetwork, $txUrl, $txType);
        }

        $this->walletRepository->flush();
    }

    private function handleMultipleTxs(Wallet $wallet, RemoteWebElement $line, string $walletNetWorth, string $txNetwork, string $txUrl, string $txType): void
    {
        $walletName = $wallet->getName();

        $txToken = $line->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_token']));

        // Single-sided transactions
        if (count($txToken) === 1 && $txType === 'node') {
            $title = $txToken[0]->getAttribute('title');
            $this->addTransaction($wallet, $walletNetWorth, $txType, $txUrl, $title, $txNetwork);
            if (!in_array($title, $wallet->getNodes())) {
                $wallet->addNode($title);
                $this->walletRepository->persist($wallet);
                $this->appendToGoogleSheet(
                    self::GOOGLE_SHEET_WALLETS,
                    $walletName,
                    $txNetwork,
                    $txType,
                    $txUrl,
                    $walletNetWorth,
                    $txToken[0]->getDomProperty('textContent')
                );
            }

            return;
        }
        if (count($txToken) === 1 && $txType === 'stake') {
            $title = $txToken[0]->getAttribute('title');
            $this->addTransaction($wallet, $walletNetWorth, $txType, $txUrl, $title, $txNetwork);
            if ((!$wallet->getStakes()) || !in_array($title, $wallet->getStakes())) {
                $wallet->addStake($title);
                $this->walletRepository->persist($wallet);
                $this->appendToGoogleSheet(
                    self::GOOGLE_SHEET_WALLETS,
                    $walletName,
                    $txNetwork,
                    $txType,
                    $txUrl,
                    $walletNetWorth,
                    $txToken[0]->getDomProperty('textContent')
                );
            }

            return;
        }

        $isNew = false;
        $inText = null;
        // Multi-sided transactions
        foreach ($txToken as $k => $tx) {
            if ($k === 0) {
                $outText = $tx->getDomProperty('textContent');
            } else {
                $title = $tx->getAttribute('title');
                $inText[] = $tx->getDomProperty('textContent');
                if ($txType === 'mint') {
                    if ((!$wallet->getNfts()) || !in_array($title, $wallet->getNfts())) {
                        $isNew = true;
                        $wallet->addNft($title);
                    }
                } elseif ($txType === 'node') {
                    if ((!$wallet->getNodes()) || !in_array($title, $wallet->getNodes())) {
                        $isNew = true;
                        $wallet->addNode($title);
                    }
                } elseif ($txType === 'buy') {
                    if ((!$wallet->getBuys()) || !in_array($title, $wallet->getBuys())) {
                        $isNew = true;
                        $wallet->addBuy($title);
                    }
                } elseif ($txType === 'stake') {
                    if ((!$wallet->getStakes()) || !in_array($title, $wallet->getStakes())) {
                        $isNew = true;
                        $wallet->addStake($title);
                    }
                } elseif ($txType === 'unstake') {
                    if ((!$wallet->getUnstakes()) || !in_array($title, $wallet->getUnstakes())) {
                        $isNew = true;
                        $wallet->addUnstake($title);
                    }
                } elseif ($txType === 'swap') {
                    if ($this->isSwapOutTextSkippable($title)) {
                        continue;
                    }

                    if ((!$wallet->getSwaps()) || !in_array($title, $wallet->getSwaps())) {
                        $isNew = true;
                        $wallet->addSwap($title);
                    }
                } elseif ($txType === 'contract') {
                    if ($this->isSwapOutTextSkippable($title)) {
                        continue;
                    }
                    $contractName = RegexHelper::getContractName($title);
                    if ((!$wallet->getContracts()) || !in_array($contractName, $wallet->getContracts())) {
                        $isNew = true;
                        $wallet->addContract($contractName);

                    }
                }
                $this->addTransaction($wallet, $walletNetWorth, $txType, $txUrl, $title, $txNetwork);
                $this->walletRepository->persist($wallet);
            }
        }

        if ($isNew) {
            $this->appendToGoogleSheet(
                self::GOOGLE_SHEET_WALLETS,
                $walletName,
                $txNetwork,
                $txType,
                $txUrl,
                $walletNetWorth,
                $outText,
                $inText,
            );
        }
    }

    private function addTransaction(Wallet $wallet, string $walletNetWorth, string $txType, string $txUrl, string $token, string $txNetwork): void
    {
        $isTxExist = $this->transactionRepository->findOneBy(['txUrl' => $txUrl]);
        if ($isTxExist) {
            return;
        }

        $transaction = new Transaction();
        $transaction->setWalletNetWorth($walletNetWorth);
        $transaction->setType($txType);
        $transaction->setToken($token);
        $transaction->setTxUrl($txUrl);
        $transaction->setDate(new \DateTime());
        $wallet->addTransaction($transaction);
        $this->transactionRepository->persist($transaction);
        $this->appendToGoogleSheet(
            self::GOOGLE_SHEET_TRANSACTIONS,
            $wallet->getName(),
            $txNetwork,
            $txType,
            $txUrl,
            $walletNetWorth,
            $token
        );
    }

    private function isSwapOutTextSkippable(string $outText) : bool
    {
        $outTextToSkip = [
            'ETH',
            'AVAX',
            'BNB',
            'USD',
            'DAI',
            'CRO',
            'FTM',
            'METIS',
            'MIM',
            'UST',
            'ELK',
            'MATIC'
        ];

        foreach ($outTextToSkip as $text) {
            if (stripos($outText, $text) !== false) {
                return true;
            }
        }

        return false;
    }

    private function appendToGoogleSheet(string $sheetName, string $walletName, string $txNetwork, string $txType, string $txUrl, string $walletNetWorth, string $outText, ?array $inText = null): void
    {
        $inputData = ($inText) ? $outText . "\n" . implode("\n", $inText) : $outText;
        $inputData = str_replace(['+', '-'], [' +', ' -'], $inputData);
        $this->googleSheetService->appendValues(
            $sheetName,
            [
                [
                    $walletName,
                    $txNetwork,
                    self::TX_TYPE_VISUALS_MAPPING[$txType] . '' . $txType,
                    $inputData,
                    $txUrl,
                    $walletNetWorth
                ]
            ]
        );
        sleep(1);
    }
}
