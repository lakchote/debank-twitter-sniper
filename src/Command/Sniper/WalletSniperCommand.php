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
    ) {
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
                sprintf('❌ An error occured : %s, backtrace : %s', $e->getMessage(), $e->getTraceAsString())
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
            case stripos($txInput, 'buy') !== false:
                return 'buy';
            case (stripos($txInput, 'unstake') !== false):
                return 'unstake';
            case (stripos($txInput, 'stake') !== false):
                return 'stake';
            case (stripos($txInput, 'swap') !== false) || (stripos($txInput, 'multicall') !== false) || (stripos($txInput, 'sell') !== false):
                return 'swap';
            case (stripos($txInput, 'contract') !== false):
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
        $chromeOptions->addArguments(['--headless', '--disable-dev-shm-usage', '--no-sandbox']);
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

        return RemoteWebDriver::create(self::HOST, $capabilities, 3600000, 3600000);
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
        $walletLabel = $wallet->getLabel();

        $txToken = $line->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_token']));

        // Single-sided transactions
        if (count($txToken) === 1 && $txType === 'stake') {
            $title = $txToken[0]->getAttribute('title');
            $this->addTransaction($wallet, $walletNetWorth, $txType, $txUrl, $title, $txNetwork);
            if (in_array($title, $wallet->getStakes())) {
                $wallet->addStake($title);
                $this->walletRepository->persist($wallet);
                $this->appendToGoogleSheet(
                    false,
                    $walletName,
                    $walletLabel,
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
        $titles = null;
        $title = null;
        // Multi-sided transactions
        foreach ($txToken as $k => $tx) {
            if ($k === 0) {
                $outText = $tx->getDomProperty('textContent');
            } else {
                $titleUnfiltered = $tx->getAttribute('title');
                $inText[] = $tx->getDomProperty('textContent');
                $title = $this->getSanitizedTokenName($titleUnfiltered);
                $titles[] = $title;
                $this->handleTxType($wallet, $title, $txType, $isNew);
            }
            $this->walletRepository->persist($wallet);
            $this->walletRepository->flush();
        }

        if (($titles) && count($titles) === 1) {
            $this->addTransaction($wallet, $walletNetWorth, $txType, $txUrl, $title, $txNetwork);
        } elseif (($titles) && count($titles) > 1) {
            $this->addTransaction($wallet, $walletNetWorth, $txType, $txUrl, null, $txNetwork, $titles);
        }

        if ($isNew) {
            $this->appendToGoogleSheet(
                true,
                $walletName,
                $walletLabel,
                $txNetwork,
                $txType,
                $txUrl,
                $walletNetWorth,
                $outText,
                $inText,
            );
        }
    }

    private function addTransaction(Wallet $wallet, string $walletNetWorth, string $txType, string $txUrl, ?string $token, string $txNetwork, ?array $titles = null): void
    {
        $isTxExist = $this->transactionRepository->findOneBy(['txUrl' => $txUrl]);
        if ($isTxExist) {
            return;
        }

        $hasWalletAlreadyBought = null;
        $walletId = $wallet->getId();
        $colors = null;
        if ($titles) {
            foreach ($titles as $title) {
                if (ctype_space($title)) {
                    continue;
                }
                $title = $this->getSanitizedTokenName($title);
                if (!$hasWalletAlreadyBought) {
                    $hasWalletAlreadyBought = $this->transactionRepository->hasWalletAlreadyBoughtToken($title, $walletId, $txUrl);
                    $hasOtherWalletsAlreadyBought = $this->transactionRepository->hasOtherWalletsAlreadyBoughtToken($title, $walletId);
                }
                $this->createPersistTransaction($wallet, $walletNetWorth, $txType, $txUrl, $title);
            }
        } else {
            if (ctype_space($token)) {
                return;
            }
            $token = $this->getSanitizedTokenName($token);
            $hasWalletAlreadyBought = $this->transactionRepository->hasWalletAlreadyBoughtToken($token, $walletId, $txUrl);
            $hasOtherWalletsAlreadyBought = $this->transactionRepository->hasOtherWalletsAlreadyBoughtToken($token, $walletId);
            $this->createPersistTransaction($wallet, $walletNetWorth, $txType, $txUrl, $token);
        }

        if ($hasWalletAlreadyBought) {
            $colors[0] = 0.0;
            $colors[1] = 0.0;
            $colors[2] = 2.0;
        }
        if ($hasOtherWalletsAlreadyBought) {
            $colors[0] = 2.0;
            $colors[1] = 0.0;
            $colors[2] = 0.0;
        }

        $this->appendToGoogleSheet(
            false,
            $wallet->getName(),
            $wallet->getLabel(),
            $txNetwork,
            $txType,
            $txUrl,
            $walletNetWorth,
            ($titles) ? implode("\n", $titles) : $token,
            null,
            $colors,
        );
    }

    private function createPersistTransaction(Wallet $wallet, string $walletNetWorth, string $txType, string $txUrl, string $token): Transaction
    {
        $transaction = new Transaction();

        $transaction->setWalletNetWorth($walletNetWorth);
        $transaction->setType($txType);
        $transaction->setToken($token);
        $transaction->setTxUrl($txUrl);
        $transaction->setDate(new \DateTime());

        $wallet->addTransaction($transaction);
        $this->transactionRepository->persist($transaction);
        $this->transactionRepository->flush();

        return $transaction;
    }

    private function appendToGoogleSheet(
        bool $isNew,
        string $walletName,
        string $walletLabel,
        string $txNetwork,
        string $txType,
        string $txUrl,
        string $walletNetWorth,
        string $outText,
        ?array $inText = null,
        ?array $colors = null
    ): void {
        $inputData = ($inText) ? $outText . "\n" . implode("\n", $inText) : $outText;
        $inputData = str_replace(['+', '-'], [' +', ' -'], $inputData);
        $this->googleSheetService->appendWalletValues(
            $isNew,
            $walletLabel,
            [
                [
                    (new \DateTime())->format('Y-m-d'),
                    $walletName,
                    $txNetwork,
                    self::TX_TYPE_VISUALS_MAPPING[$txType] . '' . $txType,
                    $inputData,
                    $txUrl,
                    $walletNetWorth
                ],
            ],
            $colors
        );
    }

    private function getSanitizedTokenName(string $token): string
    {
        $titleUnfiltered = ucfirst(strtolower($token));

        return RegexHelper::sanitizeTxInput($titleUnfiltered);
    }

    private function handleTxType(Wallet $wallet, string $title, string $txType, bool &$isNew): void
    {
        switch ($txType) {
            case $txType === 'mint':
                if (!in_array($title, $wallet->getNfts())) {
                    $isNew = true;
                    $wallet->addNft($title);
                }
                break;
            case $txType === 'buy':
                if (!in_array($title, $wallet->getBuys())) {
                    $isNew = true;
                    $wallet->addBuy($title);
                }
                break;
            case $txType === 'stake':
                if (!in_array($title, $wallet->getStakes())) {
                    $isNew = true;
                    $wallet->addStake($title);
                }
                break;
            case $txType === 'unstake':
                if (!in_array($title, $wallet->getUnstakes())) {
                    $isNew = true;
                    $wallet->addUnstake($title);
                }
                break;
            case $txType === 'swap':
                if (!in_array($title, $wallet->getSwaps())) {
                    $isNew = true;
                    $wallet->addSwap($title);
                }
                break;
            case $txType === 'contract':
                if (!in_array($title, $wallet->getContracts())) {
                    $isNew = true;
                    if (!ctype_space($title)) {
                        $wallet->addContract($title);
                    }
                }
                break;
            default:
                throw new \LogicException(sprintf('Unknown tx type: %s', $txType));
        }
    }
}
